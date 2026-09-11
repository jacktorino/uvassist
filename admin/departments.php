<?php
declare(strict_types=1);
$pageTitle = 'Departments';
require __DIR__ . '/partials/layout-top.php';

$admin = admin_user();
$editId = (int)($_GET['edit'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_verify_csrf($_POST['csrf'] ?? null);
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $code = strtoupper(trim((string)($_POST['code'] ?? '')));
        $description = trim((string)($_POST['description'] ?? '')) ?: null;
        $email = trim((string)($_POST['email'] ?? '')) ?: null;
        $phone = trim((string)($_POST['phone'] ?? '')) ?: null;
        $location = trim((string)($_POST['location'] ?? '')) ?: null;
        $status = $_POST['status'] ?? 'active';

        if ($name === '' || $code === '' || !in_array($status, ['active','inactive'], true)) {
            flash('error', 'Department name, code, and status are required.');
        } else {
            try {
                if ($id > 0) {
                    $stmt = $pdo->prepare("SELECT * FROM departments WHERE id=?");
                    $stmt->execute([$id]);
                    $old = $stmt->fetch();
                    $stmt = $pdo->prepare("UPDATE departments SET name=?, code=?, description=?, email=?, phone=?, location=?, status=? WHERE id=?");
                    $stmt->execute([$name,$code,$description,$email,$phone,$location,$status,$id]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO departments (name, code, description, email, phone, location, status) VALUES (?,?,?,?,?,?,?)");
                    $stmt->execute([$name,$code,$description,$email,$phone,$location,$status]);
                    $id = (int)$pdo->lastInsertId();
                    $old = null;
                }
                audit_log((int)$admin['id'], $old ? 'update' : 'create', 'departments', $id, $old ?? null, compact('name','code','description','email','phone','location','status'));
                flash('success', 'Department saved.');
                admin_redirect('departments.php');
            } catch (Throwable $e) {
                flash('error', 'Unable to save department. The code may already exist.');
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM departments WHERE id=?");
        $stmt->execute([$id]);
        $old = $stmt->fetch();
        if ($old) {
            try {
                $pdo->prepare("DELETE FROM departments WHERE id=?")->execute([$id]);
                audit_log((int)$admin['id'], 'delete', 'departments', $id, $old, null);
                flash('success', 'Department deleted.');
            } catch (Throwable $e) {
                flash('error', 'Department cannot be deleted because it is referenced by existing records. Set it inactive instead.');
            }
        }
        admin_redirect('departments.php');
    }
}

$edit = null;
if ($editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM departments WHERE id=?");
    $stmt->execute([$editId]);
    $edit = $stmt->fetch();
}

$departments = $pdo->query("
    SELECT d.*,
           (SELECT COUNT(*) FROM users u WHERE u.department_id=d.id) AS staff_count,
           (SELECT COUNT(*) FROM knowledge_documents kd WHERE kd.department_id=d.id) AS document_count
    FROM departments d
    ORDER BY d.name
")->fetchAll();
?>
<div class="grid gap-6 xl:grid-cols-[1fr_420px]">
<section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-100 p-5"><h2 class="font-bold">University departments</h2><p class="mt-1 text-sm text-slate-500">Departments receive escalated conversations and own knowledge documents.</p></div>
    <div class="divide-y divide-slate-100">
        <?php foreach ($departments as $d): ?>
        <div class="flex items-center gap-4 p-5">
            <div class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-emerald-100 text-xs font-extrabold text-emerald-800"><?= admin_e($d['code']) ?></div>
            <div class="min-w-0 flex-1">
                <div class="font-semibold"><?= admin_e($d['name']) ?></div>
                <div class="mt-1 text-xs text-slate-500"><?= admin_e($d['email'] ?: 'No email') ?> · <?= (int)$d['staff_count'] ?> staff · <?= (int)$d['document_count'] ?> KB docs</div>
            </div>
            <span class="rounded-full px-2.5 py-1 text-xs <?= $d['status']==='active'?'bg-emerald-100 text-emerald-800':'bg-slate-100 text-slate-500' ?>"><?= $d['status'] ?></span>
            <a href="departments.php?edit=<?= (int)$d['id'] ?>" class="text-sm font-semibold text-emerald-700">Edit</a>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <h2 class="font-bold"><?= $edit ? 'Edit department' : 'Add department' ?></h2>
    <form method="post" class="mt-5 space-y-4">
        <input type="hidden" name="csrf" value="<?= admin_e(admin_csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
        <div><label class="mb-1.5 block text-sm font-semibold">Name</label><input name="name" required value="<?= admin_e($edit['name'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5"></div>
        <div><label class="mb-1.5 block text-sm font-semibold">Code</label><input name="code" required value="<?= admin_e($edit['code'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5"></div>
        <div><label class="mb-1.5 block text-sm font-semibold">Description</label><textarea name="description" rows="3" class="w-full rounded-xl border border-slate-200 px-3 py-2.5"><?= admin_e($edit['description'] ?? '') ?></textarea></div>
        <div><label class="mb-1.5 block text-sm font-semibold">Email</label><input type="email" name="email" value="<?= admin_e($edit['email'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5"></div>
        <div><label class="mb-1.5 block text-sm font-semibold">Phone</label><input name="phone" value="<?= admin_e($edit['phone'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5"></div>
        <div><label class="mb-1.5 block text-sm font-semibold">Location</label><input name="location" value="<?= admin_e($edit['location'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5"></div>
        <div><label class="mb-1.5 block text-sm font-semibold">Status</label><select name="status" class="w-full rounded-xl border border-slate-200 px-3 py-2.5"><option value="active" <?= ($edit['status'] ?? 'active')==='active'?'selected':'' ?>>Active</option><option value="inactive" <?= ($edit['status'] ?? '')==='inactive'?'selected':'' ?>>Inactive</option></select></div>
        <button class="w-full rounded-xl bg-[#123b2b] px-4 py-3 font-semibold text-white">Save department</button>
    </form>
    <?php if ($edit): ?>
    <form method="post" class="mt-3" onsubmit="return confirm('Delete this department?')"><input type="hidden" name="csrf" value="<?= admin_e(admin_csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><button class="w-full rounded-xl border border-red-200 px-4 py-3 font-semibold text-red-700">Delete department</button></form>
    <?php endif; ?>
</section>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
