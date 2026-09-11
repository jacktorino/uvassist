<?php
declare(strict_types=1);
$pageTitle = 'Escalations';
require __DIR__ . '/partials/layout-top.php';

$admin = admin_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_verify_csrf($_POST['csrf'] ?? null);
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM escalations WHERE id=?");
    $stmt->execute([$id]);
    $old = $stmt->fetch();

    if ($old) {
        if ($action === 'assign') {
            $staffId = (int)($_POST['staff_id'] ?? 0);
            $staffStmt = $pdo->prepare("SELECT id, name, department_id FROM users WHERE id=? AND status='active' AND role IN ('admin','staff')");
            $staffStmt->execute([$staffId]);
            $staff = $staffStmt->fetch();

            if ($staff) {
                $pdo->prepare("UPDATE escalations SET assigned_staff_id=?, status='assigned', accepted_at=COALESCE(accepted_at,NOW()) WHERE id=?")->execute([$staffId,$id]);
                $pdo->prepare("UPDATE conversations SET assigned_staff_id=?, status='staff_active', last_message_at=NOW() WHERE id=?")->execute([$staffId,$old['conversation_id']]);
                $pdo->prepare("INSERT INTO analytics_events (conversation_id,event_type,metadata) VALUES (?, 'staff_assigned', ?)")->execute([$old['conversation_id'], json_encode(['staff_id'=>$staffId])]);
                audit_log((int)$admin['id'], 'assign', 'escalations', $id, $old, ['assigned_staff_id'=>$staffId,'status'=>'assigned']);
                flash('success', 'Escalation assigned.');
            }
        }

        if ($action === 'resolve') {
            $notes = trim((string)($_POST['notes'] ?? '')) ?: null;
            $pdo->prepare("UPDATE escalations SET status='resolved', resolved_at=NOW(), notes=? WHERE id=?")->execute([$notes,$id]);
            $pdo->prepare("UPDATE conversations SET status='resolved', resolved_at=COALESCE(resolved_at,NOW()) WHERE id=?")->execute([$old['conversation_id']]);
            $pdo->prepare("INSERT INTO analytics_events (conversation_id,event_type,metadata) VALUES (?, 'conversation_resolved', ?)")->execute([$old['conversation_id'], json_encode(['escalation_id'=>$id])]);
            audit_log((int)$admin['id'], 'resolve', 'escalations', $id, $old, ['status'=>'resolved','notes'=>$notes]);
            flash('success', 'Escalation resolved.');
        }
    }
    admin_redirect('escalations.php');
}

$departments = $pdo->query("SELECT id,name FROM departments WHERE status='active' ORDER BY name")->fetchAll();
$staff = $pdo->query("SELECT id,name,department_id FROM users WHERE status='active' AND role IN ('admin','staff') ORDER BY name")->fetchAll();

$status = $_GET['status'] ?? '';
$where = '';
$params = [];
if (in_array($status, ['pending','assigned','in_progress','resolved','cancelled'], true)) {
    $where = 'WHERE e.status=?';
    $params[] = $status;
}

$stmt = $pdo->prepare("
    SELECT e.*, c.conversation_uuid, c.subject, c.status AS conversation_status,
           cv.name AS visitor_name, cv.email AS visitor_email,
           d.name AS department_name, u.name AS staff_name
    FROM escalations e
    INNER JOIN conversations c ON c.id=e.conversation_id
    LEFT JOIN chat_visitors cv ON cv.id=c.visitor_id
    LEFT JOIN departments d ON d.id=e.department_id
    LEFT JOIN users u ON u.id=e.assigned_staff_id
    {$where}
    ORDER BY FIELD(e.status,'pending','assigned','in_progress','resolved','cancelled'), e.created_at DESC
    LIMIT 200
");
$stmt->execute($params);
$escalations = $stmt->fetchAll();
?>
<section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
<div class="border-b border-slate-100 p-5">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div><h2 class="font-bold">Human escalations</h2><p class="mt-1 text-sm text-slate-500">Queries that require staff attention.</p></div>
        <div class="flex gap-2 overflow-x-auto text-xs"><?php foreach([''=>'All','pending'=>'Pending','assigned'=>'Assigned','in_progress'=>'In progress','resolved'=>'Resolved'] as $key=>$label): ?><a href="escalations.php<?= $key?'?status='.$key:'' ?>" class="whitespace-nowrap rounded-full px-3 py-1.5 <?= $status===$key?'bg-emerald-700 text-white':'bg-slate-100 text-slate-600' ?>"><?= $label ?></a><?php endforeach; ?></div>
    </div>
</div>
<div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Visitor</th><th class="px-5 py-3">Reason</th><th class="px-5 py-3">Department</th><th class="px-5 py-3">Confidence</th><th class="px-5 py-3">Assigned</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Action</th></tr></thead><tbody class="divide-y divide-slate-100">
<?php foreach($escalations as $e): ?>
<tr>
<td class="px-5 py-4"><div class="font-semibold"><?= admin_e($e['visitor_name'] ?: 'Visitor') ?></div><div class="text-xs text-slate-400"><?= admin_e($e['visitor_email'] ?: '') ?></div></td>
<td class="px-5 py-4"><?= admin_e(str_replace('_',' ',$e['reason'])) ?></td>
<td class="px-5 py-4"><?= admin_e($e['department_name'] ?: 'General') ?></td>
<td class="px-5 py-4"><?= $e['confidence_score'] !== null ? number_format((float)$e['confidence_score'],2) : '—' ?></td>
<td class="px-5 py-4"><?= admin_e($e['staff_name'] ?: 'Unassigned') ?></td>
<td class="px-5 py-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs"><?= admin_e($e['status']) ?></span></td>
<td class="px-5 py-4">
<?php if(in_array($e['status'],['pending','assigned','in_progress'],true)): ?>
<form method="post" class="flex min-w-[240px] gap-2"><input type="hidden" name="csrf" value="<?= admin_e(admin_csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$e['id'] ?>"><select name="staff_id" class="rounded-lg border border-slate-200 px-2 py-2 text-xs"><?php foreach($staff as $s): ?><option value="<?= (int)$s['id'] ?>" <?= (int)$s['id']===(int)$e['assigned_staff_id']?'selected':'' ?>><?= admin_e($s['name']) ?></option><?php endforeach; ?></select><button name="action" value="assign" class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">Assign</button><button name="action" value="resolve" class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-semibold text-white">Resolve</button></form>
<?php else: ?><a href="conversations.php?id=<?= (int)$e['conversation_id'] ?>" class="font-semibold text-emerald-700">View</a><?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php if(!$escalations): ?><div class="p-10 text-center text-sm text-slate-500">No escalations found.</div><?php endif; ?>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
