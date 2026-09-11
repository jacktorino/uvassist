<?php
declare(strict_types=1);
$pageTitle = 'Audit Logs';
require __DIR__ . '/partials/layout-top.php';

$q = trim((string)($_GET['q'] ?? ''));
$params = [];
$where = '';
if ($q !== '') {
    $where = "WHERE u.name LIKE ? OR al.action LIKE ? OR al.table_name LIKE ?";
    $like = "%$q%";
    $params = [$like,$like,$like];
}

$stmt = $pdo->prepare("
    SELECT al.*, u.name AS user_name, u.email AS user_email
    FROM audit_logs al
    LEFT JOIN users u ON u.id=al.user_id
    {$where}
    ORDER BY al.id DESC
    LIMIT 300
");
$stmt->execute($params);
$logs = $stmt->fetchAll();
?>
<section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
<div class="border-b border-slate-100 p-5"><div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="font-bold">Audit trail</h2><p class="mt-1 text-sm text-slate-500">Administrative changes and access events.</p></div><form class="flex gap-2"><input name="q" value="<?= admin_e($q) ?>" placeholder="Search user, action, table..." class="rounded-xl border border-slate-200 px-3 py-2 text-sm"><button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Search</button></form></div></div>
<div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Date</th><th class="px-5 py-3">User</th><th class="px-5 py-3">Action</th><th class="px-5 py-3">Table</th><th class="px-5 py-3">Record</th><th class="px-5 py-3">Changes</th></tr></thead><tbody class="divide-y divide-slate-100">
<?php foreach($logs as $l): ?>
<tr class="align-top"><td class="whitespace-nowrap px-5 py-4 text-xs text-slate-500"><?= admin_e(date('M j, Y g:i A',strtotime($l['created_at']))) ?></td><td class="px-5 py-4"><div class="font-semibold"><?= admin_e($l['user_name'] ?: 'System') ?></div><div class="text-xs text-slate-400"><?= admin_e($l['user_email'] ?: '') ?></div></td><td class="px-5 py-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs"><?= admin_e($l['action']) ?></span></td><td class="px-5 py-4 font-mono text-xs"><?= admin_e($l['table_name']) ?></td><td class="px-5 py-4 text-xs"><?= $l['record_id'] !== null ? (int)$l['record_id'] : '—' ?></td><td class="max-w-lg px-5 py-4 text-xs text-slate-500"><details><summary class="cursor-pointer font-semibold text-emerald-700">View details</summary><pre class="mt-2 whitespace-pre-wrap break-all"><?= admin_e(json_encode(['old'=>json_decode($l['old_values'] ?? 'null',true),'new'=>json_decode($l['new_values'] ?? 'null',true)], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre></details></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php if(!$logs): ?><div class="p-10 text-center text-sm text-slate-500">No audit records found.</div><?php endif; ?>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
