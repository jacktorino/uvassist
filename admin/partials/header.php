<?php
$flash = consume_flash();
$admin = admin_user();
?>
<header class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur">
    <div class="flex h-20 items-center justify-between px-4 sm:px-6 lg:px-8">
        <div class="flex items-center gap-3">
            <button id="menuButton" class="grid h-10 w-10 place-items-center rounded-xl border border-slate-200 text-slate-700 lg:hidden">☰</button>
            <div>
                <div class="text-xs font-semibold uppercase tracking-[.18em] text-emerald-700">University of the Visayas</div>
                <h1 class="text-xl font-bold tracking-tight text-slate-900"><?= admin_e($pageTitle ?? 'Admin') ?></h1>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <a href="../index.php" target="_blank" class="hidden rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 sm:block">Open Chat</a>
            <div class="hidden text-right md:block">
                <div class="text-sm font-semibold text-slate-800"><?= admin_e($admin['name'] ?? '') ?></div>
                <div class="text-xs text-slate-500"><?= admin_e(ucfirst($admin['role'] ?? '')) ?></div>
            </div>
            <div class="grid h-10 w-10 place-items-center rounded-full bg-emerald-100 font-bold text-emerald-800">
                <?= admin_e(strtoupper(substr($admin['name'] ?? 'A', 0, 1))) ?>
            </div>
        </div>
    </div>
</header>

<?php if ($flash): ?>
<div class="mx-4 mt-4 rounded-xl border px-4 py-3 text-sm <?= $flash['type'] === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-800' ?>">
    <?= admin_e($flash['message']) ?>
</div>
<?php endif; ?>
