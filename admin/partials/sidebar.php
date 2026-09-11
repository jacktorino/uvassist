<?php
$admin = admin_user();
$page = current_admin_page();

$nav = [
    ['dashboard.php', 'Dashboard', '⌂'],
    ['conversations.php', 'Conversations', '☵'],
    ['knowledge-base.php', 'Knowledge Base', '▤'],
    ['departments.php', 'Departments', '▦'],
    ['staff.php', 'Staff / Users', '♙'],
    ['escalations.php', 'Escalations', '⚑'],
    ['analytics.php', 'Analytics', '◒'],
    ['audit-logs.php', 'Audit Logs', '◫'],
];
?>
<aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-72 -translate-x-full border-r border-emerald-950/10 bg-[#123b2b] text-white transition-transform duration-200 lg:translate-x-0">
    <div class="flex h-20 items-center gap-3 border-b border-white/10 px-6">
        <div class="grid h-10 w-10 place-items-center rounded-xl bg-white/10 text-lg font-bold">UV</div>
        <div>
            <div class="font-bold tracking-tight">UV-ASSIST</div>
            <div class="text-xs text-emerald-100/70">Administration</div>
        </div>
    </div>

    <div class="px-4 py-5">
        <div class="mb-3 px-3 text-[11px] font-semibold uppercase tracking-[.18em] text-emerald-100/50">Workspace</div>
        <nav class="space-y-1">
            <?php foreach ($nav as [$href, $label, $icon]): ?>
                <a href="<?= admin_e($href) ?>"
                   class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition <?= $page === $href ? 'bg-white text-[#123b2b] shadow-sm' : 'text-emerald-50/80 hover:bg-white/10 hover:text-white' ?>">
                    <span class="grid h-7 w-7 place-items-center rounded-lg <?= $page === $href ? 'bg-[#e9f3ee]' : 'bg-white/5' ?>"><?= $icon ?></span>
                    <?= admin_e($label) ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>

    <div class="absolute bottom-0 left-0 right-0 border-t border-white/10 p-4">
        <div class="mb-3 flex items-center gap-3 rounded-xl bg-white/5 p-3">
            <div class="grid h-9 w-9 place-items-center rounded-full bg-emerald-200 font-bold text-[#123b2b]">
                <?= admin_e(strtoupper(substr($admin['name'] ?? 'A', 0, 1))) ?>
            </div>
            <div class="min-w-0 flex-1">
                <div class="truncate text-sm font-semibold"><?= admin_e($admin['name'] ?? 'Administrator') ?></div>
                <div class="truncate text-xs text-emerald-100/60"><?= admin_e($admin['email'] ?? '') ?></div>
            </div>
        </div>
        <a href="logout.php" class="flex items-center justify-center rounded-xl border border-white/10 px-3 py-2 text-sm text-emerald-50/80 hover:bg-white/10 hover:text-white">
            Sign out
        </a>
    </div>
</aside>
<div id="sidebarBackdrop" class="fixed inset-0 z-40 hidden bg-black/40 lg:hidden"></div>
