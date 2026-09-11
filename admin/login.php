<?php
declare(strict_types=1);
require_once __DIR__ . '/admin_config.php';

if (admin_user()) {
    admin_redirect('dashboard.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_verify_csrf($_POST['csrf'] ?? null);

    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Enter a valid email address and password.';
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT u.*, d.name AS department_name
                FROM users u
                LEFT JOIN departments d ON d.id = u.department_id
                WHERE u.email = ?
                LIMIT 1
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || ($user['status'] ?? '') !== 'active' || ($user['role'] ?? '') !== 'admin' || !password_verify($password, $user['password'])) {
                $error = 'Invalid administrator credentials.';
            } else {
                session_regenerate_id(true);
                $_SESSION[ADMIN_SESSION_KEY] = (int)$user['id'];
                $_SESSION[ADMIN_ROLE_KEY] = $user['role'];
                admin_csrf_token();

                $pdo->prepare("UPDATE users SET last_seen_at = NOW() WHERE id = ?")->execute([(int)$user['id']]);
                audit_log((int)$user['id'], 'login', 'users', (int)$user['id'], null, ['login_at' => date('c')]);

                admin_redirect('dashboard.php');
            }
        } catch (Throwable $e) {
            error_log('UV-ASSIST admin login: ' . $e->getMessage());
            $error = 'Unable to sign in right now.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Login — UV-ASSIST</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="min-h-screen bg-[#123b2b] font-sans">
<div class="grid min-h-screen lg:grid-cols-2">
    <div class="hidden items-center justify-center bg-[#0d2f23] p-12 lg:flex">
        <div class="max-w-md text-white">
            <div class="mb-6 grid h-16 w-16 place-items-center rounded-2xl bg-white/10 text-xl font-extrabold">UV</div>
            <div class="mb-2 text-sm font-semibold uppercase tracking-[.2em] text-emerald-200">University of the Visayas</div>
            <h1 class="text-5xl font-extrabold tracking-tight">UV-ASSIST</h1>
            <p class="mt-5 text-lg leading-8 text-emerald-50/70">Intelligent campus helpdesk administration, knowledge management, escalation handling, and service analytics.</p>
        </div>
    </div>

    <div class="flex items-center justify-center bg-[#f7faf8] p-6">
        <div class="w-full max-w-md">
            <div class="mb-8 lg:hidden">
                <div class="text-sm font-semibold uppercase tracking-[.18em] text-emerald-700">University of the Visayas</div>
                <h1 class="mt-1 text-3xl font-extrabold text-slate-900">UV-ASSIST</h1>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-7 shadow-xl shadow-slate-900/5 sm:p-9">
                <h2 class="text-2xl font-bold">Administrator sign in</h2>
                <p class="mt-2 text-sm text-slate-500">Access the UV-ASSIST management console.</p>

                <?php if ($error): ?>
                    <div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= admin_e($error) ?></div>
                <?php endif; ?>

                <form method="post" class="mt-7 space-y-5">
                    <input type="hidden" name="csrf" value="<?= admin_e(admin_csrf_token()) ?>">
                    <div>
                        <label class="mb-2 block text-sm font-semibold">Email</label>
                        <input type="email" name="email" required autocomplete="email" class="w-full rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-emerald-600 focus:ring-4 focus:ring-emerald-100" placeholder="admin@uv.edu.ph">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-semibold">Password</label>
                        <input type="password" name="password" required autocomplete="current-password" class="w-full rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-emerald-600 focus:ring-4 focus:ring-emerald-100" placeholder="••••••••">
                    </div>
                    <button class="w-full rounded-xl bg-[#123b2b] px-4 py-3.5 font-semibold text-white transition hover:bg-[#0d2f23]">Sign in</button>
                </form>
            </div>

            <p class="mt-6 text-center text-xs text-slate-500">UV-ASSIST Administration Console</p>
        </div>
    </div>
</div>
</body>
</html>
