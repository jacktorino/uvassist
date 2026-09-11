<?php
declare(strict_types=1);
$pageTitle = 'Analytics';
require __DIR__ . '/partials/layout-top.php';

$totalMessages = count_rows('messages');
$userMessages = count_rows('messages', "sender_type='user'");
$aiMessages = count_rows('messages', "sender_type='ai'");
$staffMessages = count_rows('messages', "sender_type='staff'");
$escalated = count_rows('escalations');
$resolvedEscalations = count_rows('escalations', "status='resolved'");

$avgConfidence = (float)($pdo->query("SELECT COALESCE(AVG(confidence_score),0) FROM messages WHERE sender_type='ai' AND confidence_score IS NOT NULL")->fetchColumn() ?: 0);
$aiResolutionRate = $totalMessages > 0 ? ($aiMessages / max(1, $userMessages)) * 100 : 0;
$escalationRate = $userMessages > 0 ? ($escalated / $userMessages) * 100 : 0;

$intents = $pdo->query("
    SELECT COALESCE(intent,'unclassified') AS intent, COUNT(*) AS total
    FROM messages
    WHERE sender_type='user'
    GROUP BY intent
    ORDER BY total DESC
    LIMIT 12
")->fetchAll();

$departments = $pdo->query("
    SELECT COALESCE(d.name,'General') AS department_name, COUNT(*) AS total
    FROM conversations c
    LEFT JOIN departments d ON d.id=c.department_id
    GROUP BY c.department_id
    ORDER BY total DESC
")->fetchAll();

$events = $pdo->query("
    SELECT event_type, COUNT(*) AS total
    FROM analytics_events
    GROUP BY event_type
    ORDER BY total DESC
")->fetchAll();

$daily = $pdo->query("
    SELECT DATE(created_at) day, COUNT(*) total
    FROM messages
    WHERE sender_type='user' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
    GROUP BY DATE(created_at)
    ORDER BY day
")->fetchAll();
$dailyMap = [];
foreach ($daily as $r) $dailyMap[$r['day']] = (int)$r['total'];
?>
<div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
<?php foreach([
    ['Average AI confidence', number_format($avgConfidence,2), 'Across AI messages'],
    ['Escalation rate', number_format($escalationRate,1).'%', 'Escalations vs. user messages'],
    ['AI replies', number_format($aiMessages), 'Automated responses recorded'],
    ['Staff replies', number_format($staffMessages), 'Human responses recorded'],
] as [$label,$value,$hint]): ?>
<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="text-sm text-slate-500"><?= $label ?></div><div class="mt-2 text-3xl font-extrabold"><?= $value ?></div><div class="mt-2 text-xs text-slate-400"><?= $hint ?></div></div>
<?php endforeach; ?>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
<section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
<h2 class="font-bold">User queries — last 30 days</h2>
<div class="mt-7 space-y-2">
<?php for($i=29;$i>=0;$i--): $day=date('Y-m-d',strtotime("-$i days")); $total=$dailyMap[$day]??0; ?>
<div class="flex items-center gap-3"><div class="w-20 text-xs text-slate-400"><?= date('M j',strtotime($day)) ?></div><div class="h-5 flex-1 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-emerald-700" style="width:<?= min(100,$total*10) ?>%"></div></div><div class="w-8 text-right text-xs font-semibold"><?= $total ?></div></div>
<?php endfor; ?>
</div>
</section>

<section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
<h2 class="font-bold">Top intents</h2>
<div class="mt-5 space-y-3"><?php foreach($intents as $r): ?><div class="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3"><span class="text-sm"><?= admin_e(str_replace('_',' ',$r['intent'])) ?></span><b><?= (int)$r['total'] ?></b></div><?php endforeach; ?><?php if(!$intents): ?><div class="text-sm text-slate-500">No intent data yet.</div><?php endif; ?></div>
</section>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
<section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><h2 class="font-bold">Conversations by department</h2><div class="mt-5 space-y-3"><?php foreach($departments as $r): ?><div class="flex items-center justify-between border-b border-slate-100 pb-3"><span><?= admin_e($r['department_name']) ?></span><b><?= (int)$r['total'] ?></b></div><?php endforeach; ?></div></section>
<section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><h2 class="font-bold">System events</h2><div class="mt-5 space-y-3"><?php foreach($events as $r): ?><div class="flex items-center justify-between border-b border-slate-100 pb-3"><span class="text-sm"><?= admin_e(str_replace('_',' ',$r['event_type'])) ?></span><b><?= (int)$r['total'] ?></b></div><?php endforeach; ?></div></section>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
