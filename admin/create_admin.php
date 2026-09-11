<?php
declare(strict_types=1);

/*
 * RUN ONCE, THEN DELETE THIS FILE.
 * Open: /admin/create_admin.php
 */
require_once __DIR__ . '/admin_config.php';

$created = false;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_verify_csrf($_POST['csrf'] ?? null);

    $name = trim((string)($_POST['name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        $error = 'Enter a name, valid email, and password of at least 8 characters.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO users (name,email,password,role,status,availability) VALUES (?,?,?,'admin','active','offline')");
            $stmt->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
            $created = true;
        } catch (Throwable $e) {
            $error = 'Could not create account. The email may already exist.';
        }
    }
}
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Create UV-ASSIST Admin</title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="min-h-screen bg-slate-100 p-6">
<div class="mx-auto mt-16 max-w-md rounded-2xl bg-white p-7 shadow-xl">
<h1 class="text-2xl font-bold">Create first administrator</h1>
<p class="mt-2 text-sm text-slate-500">Use this only for initial setup, then delete <code>create_admin.php</code>.</p>
<?php if($created): ?><div class="mt-5 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800">Administrator created. Delete this file, then sign in through <a class="font-bold underline" href="login.php">login.php</a>.</div><?php else: ?>
<?php if($error): ?><div class="mt-5 rounded-xl bg-red-50 p-4 text-sm text-red-700"><?= admin_e($error) ?></div><?php endif; ?>
<form method="post" class="mt-6 space-y-4"><input type="hidden" name="csrf" value="<?= admin_e(admin_csrf_token()) ?>"><input name="name" required placeholder="Administrator name" class="w-full rounded-xl border px-3 py-3"><input type="email" name="email" required placeholder="admin@example.com" class="w-full rounded-xl border px-3 py-3"><input type="password" name="password" required minlength="8" placeholder="Password" class="w-full rounded-xl border px-3 py-3"><button class="w-full rounded-xl bg-[#123b2b] py-3 font-semibold text-white">Create administrator</button></form>
<?php endif; ?>
</div></body></html>
