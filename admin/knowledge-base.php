<?php
declare(strict_types=1);
$pageTitle = 'Knowledge Base';
require __DIR__ . '/partials/layout-top.php';

$admin = admin_user();
$editId = (int)($_GET['edit'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_verify_csrf($_POST['csrf'] ?? null);
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        $content = trim((string)($_POST['content'] ?? ''));
        $categoryId = (int)($_POST['category_id'] ?? 0) ?: null;
        $departmentId = (int)($_POST['department_id'] ?? 0) ?: null;
        $sourceType = $_POST['source_type'] ?? 'faq';
        $sourceReference = trim((string)($_POST['source_reference'] ?? '')) ?: null;
        $version = trim((string)($_POST['version'] ?? '')) ?: null;
        $status = $_POST['status'] ?? 'draft';
        $effectiveDate = trim((string)($_POST['effective_date'] ?? '')) ?: null;
        $expiresAt = trim((string)($_POST['expires_at'] ?? '')) ?: null;

        $validTypes = ['faq','policy','procedure','announcement','office_information','document','other'];
        $validStatuses = ['draft','published','archived'];

        if ($title === '' || $content === '' || !in_array($sourceType, $validTypes, true) || !in_array($status, $validStatuses, true)) {
            flash('error', 'Title, content, source type, and status are required.');
        } else {
            try {
                $pdo->beginTransaction();

                if ($id > 0) {
                    $stmt = $pdo->prepare("SELECT * FROM knowledge_documents WHERE id = ? FOR UPDATE");
                    $stmt->execute([$id]);
                    $old = $stmt->fetch();

                    if (!$old) throw new RuntimeException('Document not found.');

                    $stmt = $pdo->prepare("
                        UPDATE knowledge_documents
                        SET category_id=?, department_id=?, title=?, content=?, source_type=?,
                            source_reference=?, version=?, status=?, effective_date=?, expires_at=?,
                            updated_by=?, published_at=CASE WHEN ?='published' THEN COALESCE(published_at,NOW()) ELSE published_at END
                        WHERE id=?
                    ");
                    $stmt->execute([$categoryId,$departmentId,$title,$content,$sourceType,$sourceReference,$version,$status,$effectiveDate,$expiresAt,(int)$admin['id'],$status,$id]);

                    $pdo->prepare("DELETE FROM knowledge_chunks WHERE document_id = ?")->execute([$id]);
                    $documentId = $id;
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO knowledge_documents
                            (category_id, department_id, title, content, source_type, source_reference, version, status, effective_date, expires_at, created_by, updated_by, published_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CASE WHEN ?='published' THEN NOW() ELSE NULL END)
                    ");
                    $stmt->execute([$categoryId,$departmentId,$title,$content,$sourceType,$sourceReference,$version,$status,$effectiveDate,$expiresAt,(int)$admin['id'],(int)$admin['id'],$status]);
                    $documentId = (int)$pdo->lastInsertId();
                    $old = null;
                }

                $paragraphs = preg_split('/\R\s*\R/', $content) ?: [$content];
                $chunks = [];
                foreach ($paragraphs as $paragraph) {
                    $paragraph = trim($paragraph);
                    if ($paragraph === '') continue;
                    if (strlen($paragraph) <= 900) {
                        $chunks[] = $paragraph;
                    } else {
                        $words = preg_split('/\s+/', $paragraph) ?: [];
                        $buffer = '';
                        foreach ($words as $word) {
                            if ($buffer !== '' && strlen($buffer . ' ' . $word) > 900) {
                                $chunks[] = $buffer;
                                $buffer = $word;
                            } else {
                                $buffer .= ($buffer === '' ? '' : ' ') . $word;
                            }
                        }
                        if ($buffer !== '') $chunks[] = $buffer;
                    }
                }
                if (!$chunks) $chunks = [$content];

                $chunkStmt = $pdo->prepare("INSERT INTO knowledge_chunks (document_id, chunk_index, content, token_count) VALUES (?, ?, ?, ?)");
                foreach ($chunks as $index => $chunk) {
                    $tokens = count(preg_split('/\s+/', trim($chunk)) ?: []);
                    $chunkStmt->execute([$documentId, $index + 1, $chunk, $tokens]);
                }

                $pdo->commit();
                audit_log((int)$admin['id'], $id > 0 ? 'update' : 'create', 'knowledge_documents', $documentId, $old ?? null, [
                    'title' => $title, 'status' => $status, 'category_id' => $categoryId, 'department_id' => $departmentId
                ]);
                flash('success', $id > 0 ? 'Knowledge document updated.' : 'Knowledge document created.');
                admin_redirect('knowledge-base.php');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('KB save: ' . $e->getMessage());
                flash('error', 'Unable to save the knowledge document: ' . $e->getMessage());
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("SELECT * FROM knowledge_documents WHERE id = ?");
            $stmt->execute([$id]);
            $old = $stmt->fetch();
            if ($old) {
                $pdo->prepare("DELETE FROM knowledge_documents WHERE id = ?")->execute([$id]);
                audit_log((int)$admin['id'], 'delete', 'knowledge_documents', $id, $old, null);
                flash('success', 'Knowledge document deleted.');
            }
        }
        admin_redirect('knowledge-base.php');
    }
}

$categories = $pdo->query("SELECT * FROM knowledge_categories WHERE status='active' ORDER BY name")->fetchAll();
$departments = $pdo->query("SELECT * FROM departments WHERE status='active' ORDER BY name")->fetchAll();

$edit = null;
if ($editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM knowledge_documents WHERE id = ?");
    $stmt->execute([$editId]);
    $edit = $stmt->fetch();
}

$status = $_GET['status'] ?? '';
$where = [];
$params = [];
if (in_array($status, ['draft','published','archived'], true)) {
    $where[] = 'kd.status = ?';
    $params[] = $status;
}
$q = trim((string)($_GET['q'] ?? ''));
if ($q !== '') {
    $where[] = '(kd.title LIKE ? OR kd.content LIKE ?)';
    array_push($params, "%$q%", "%$q%");
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT kd.*, kc.name AS category_name, d.name AS department_name,
           (SELECT COUNT(*) FROM knowledge_chunks ch WHERE ch.document_id=kd.id) AS chunk_count
    FROM knowledge_documents kd
    LEFT JOIN knowledge_categories kc ON kc.id=kd.category_id
    LEFT JOIN departments d ON d.id=kd.department_id
    {$whereSql}
    ORDER BY kd.updated_at DESC
    LIMIT 200
");
$stmt->execute($params);
$documents = $stmt->fetchAll();
?>
<div class="grid gap-6 xl:grid-cols-[1fr_460px]">
    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 p-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="font-bold">Knowledge documents</h2>
                    <p class="mt-1 text-sm text-slate-500">Manage the information used by UV-ASSIST retrieval.</p>
                </div>
                <a href="knowledge-base.php" class="rounded-xl bg-[#123b2b] px-4 py-2.5 text-sm font-semibold text-white">+ New document</a>
            </div>
            <form class="mt-4 flex gap-2">
                <input name="q" value="<?= admin_e($q) ?>" placeholder="Search title or content..." class="min-w-0 flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm">
                <button class="rounded-xl border border-slate-200 px-4 text-sm font-semibold">Search</button>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr><th class="px-5 py-3">Document</th><th class="px-5 py-3">Department</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Chunks</th><th class="px-5 py-3"></th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                <?php foreach ($documents as $doc): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-4">
                            <div class="font-semibold"><?= admin_e($doc['title']) ?></div>
                            <div class="mt-1 text-xs text-slate-400"><?= admin_e($doc['category_name'] ?: 'Uncategorized') ?> · <?= admin_e($doc['source_type']) ?></div>
                        </td>
                        <td class="px-5 py-4 text-slate-600"><?= admin_e($doc['department_name'] ?: 'General') ?></td>
                        <td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs <?= $doc['status']==='published'?'bg-emerald-100 text-emerald-800':($doc['status']==='draft'?'bg-amber-100 text-amber-800':'bg-slate-100 text-slate-600') ?>"><?= admin_e($doc['status']) ?></span></td>
                        <td class="px-5 py-4 text-slate-500"><?= (int)$doc['chunk_count'] ?></td>
                        <td class="px-5 py-4 text-right"><a class="font-semibold text-emerald-700" href="knowledge-base.php?edit=<?= (int)$doc['id'] ?>">Edit</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (!$documents): ?><div class="p-10 text-center text-sm text-slate-500">No documents found.</div><?php endif; ?>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-bold"><?= $edit ? 'Edit document' : 'Add document' ?></h2>
                <p class="mt-1 text-sm text-slate-500">Content is automatically split into retrieval chunks.</p>
            </div>
            <?php if ($edit): ?><a href="knowledge-base.php" class="text-sm font-semibold text-slate-500">Cancel</a><?php endif; ?>
        </div>

        <form method="post" class="mt-6 space-y-4">
            <input type="hidden" name="csrf" value="<?= admin_e(admin_csrf_token()) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">

            <div><label class="mb-1.5 block text-sm font-semibold">Title</label><input name="title" required value="<?= admin_e($edit['title'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5"></div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div><label class="mb-1.5 block text-sm font-semibold">Category</label><select name="category_id" class="w-full rounded-xl border border-slate-200 px-3 py-2.5"><option value="">General</option><?php foreach($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= ((int)($edit['category_id'] ?? 0)===(int)$c['id'])?'selected':'' ?>><?= admin_e($c['name']) ?></option><?php endforeach; ?></select></div>
                <div><label class="mb-1.5 block text-sm font-semibold">Department</label><select name="department_id" class="w-full rounded-xl border border-slate-200 px-3 py-2.5"><option value="">General / All</option><?php foreach($departments as $d): ?><option value="<?= (int)$d['id'] ?>" <?= ((int)($edit['department_id'] ?? 0)===(int)$d['id'])?'selected':'' ?>><?= admin_e($d['name']) ?></option><?php endforeach; ?></select></div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div><label class="mb-1.5 block text-sm font-semibold">Source type</label><select name="source_type" class="w-full rounded-xl border border-slate-200 px-3 py-2.5"><?php foreach(['faq','policy','procedure','announcement','office_information','document','other'] as $v): ?><option value="<?= $v ?>" <?= ($edit['source_type'] ?? 'faq')===$v?'selected':'' ?>><?= ucwords(str_replace('_',' ',$v)) ?></option><?php endforeach; ?></select></div>
                <div><label class="mb-1.5 block text-sm font-semibold">Version</label><input name="version" value="<?= admin_e($edit['version'] ?? '1.0') ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5"></div>
            </div>

            <div><label class="mb-1.5 block text-sm font-semibold">Content</label><textarea name="content" required rows="12" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 leading-6"><?= admin_e($edit['content'] ?? '') ?></textarea></div>

            <div><label class="mb-1.5 block text-sm font-semibold">Source reference</label><input name="source_reference" value="<?= admin_e($edit['source_reference'] ?? '') ?>" placeholder="Office memo, URL, document reference..." class="w-full rounded-xl border border-slate-200 px-3 py-2.5"></div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div><label class="mb-1.5 block text-sm font-semibold">Effective date</label><input type="date" name="effective_date" value="<?= admin_e($edit['effective_date'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5"></div>
                <div><label class="mb-1.5 block text-sm font-semibold">Expiration date</label><input type="date" name="expires_at" value="<?= admin_e($edit['expires_at'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5"></div>
            </div>

            <div><label class="mb-1.5 block text-sm font-semibold">Status</label><select name="status" class="w-full rounded-xl border border-slate-200 px-3 py-2.5"><?php foreach(['draft','published','archived'] as $v): ?><option value="<?= $v ?>" <?= ($edit['status'] ?? 'draft')===$v?'selected':'' ?>><?= ucfirst($v) ?></option><?php endforeach; ?></select></div>

            <button class="w-full rounded-xl bg-[#123b2b] px-4 py-3 font-semibold text-white"><?= $edit ? 'Update document' : 'Create document' ?></button>
        </form>

        <?php if ($edit): ?>
        <form method="post" class="mt-3" onsubmit="return confirm('Delete this knowledge document?')">
            <input type="hidden" name="csrf" value="<?= admin_e(admin_csrf_token()) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
            <button class="w-full rounded-xl border border-red-200 px-4 py-3 font-semibold text-red-700 hover:bg-red-50">Delete document</button>
        </form>
        <?php endif; ?>
    </section>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
