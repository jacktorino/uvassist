<?php
declare(strict_types=1);
$pageTitle = 'Staff / Users';
require __DIR__ . '/partials/layout-top.php';

$admin = admin_user();
$editId = (int)($_GET['edit'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_verify_csrf($_POST['csrf'] ?? null);
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        $role = $_POST['role'] ?? 'staff';
        $departmentId = (int)($_POST['department_id'] ?? 0) ?: null;
        $status = $_POST['status'] ?? 'active';
        $availability = $_POST['availability'] ?? 'offline';

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role, ['admin','staff'], true) || !in_array($status, ['active','inactive','suspended'], true) || !in_array($availability, ['online','away','offline'], true)) {
            flash('error', 'Please complete the required staff fields.');
        } elseif ($id === 0 && strlen($password) < 8) {
            flash('error', 'A new account requires a password of at least 8 characters.');
        } else {
            try {
                if ($id > 0) {
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
                    $stmt->execute([$id]);
                    $old = $stmt->fetch();
                    if (!$old) throw new RuntimeException('User not found.');

                    if ($password !== '') {
                        $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, password=?, role=?, department_id=?, status=?, availability=? WHERE id=?");
                        $stmt->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT),$role,$departmentId,$status,$availability,$id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, role=?, department_id=?, status=?, availability=? WHERE id=?");
                        $stmt->execute([$name,$email,$role,$departmentId,$status,$availability,$id]);
                    }
                } else {
                    $stmt = $pdo->prepare("INSERT INTO users (department_id,name,email,password,role,status,availability) VALUES (?,?,?,?,?,?,?)");
                    $stmt->execute([$departmentId,$name,$email,password_hash($password,PASSWORD_DEFAULT),$role,$status,$availability]);
                    $id = (int)$pdo->lastInsertId();
                    $old = null;
                }

                audit_log((int)$admin['id'], $old ? 'update' : 'create', 'users', $id, $old ?? null, [
                    'name'=>$name,'email'=>$email,'role'=>$role,'department_id'=>$departmentId,'status'=>$status,'availability'=>$availability
                ]);
                flash('success', 'User account saved.');
                admin_redirect('staff.php');
            } catch (Throwable $e) {
                error_log('Staff save: ' . $e->getMessage());
                flash('error', 'Unable to save user. Email may already be in use.');
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)$admin['id']) {
            flash('error', 'You cannot delete your own account.');
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
            $stmt->execute([$id]);
            $old = $stmt->fetch();
            if ($old) {
                try {
                    $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
                    audit_log((int)$admin['id'], 'delete', 'users', $id, $old, null);
                    flash('success', 'User deleted.');
                } catch (Throwable $e) {
                    flash('error', 'User cannot be deleted because existing records reference this account. Set the account inactive instead.');
                }
            }
        }
        admin_redirect('staff.php');
    }
}

$departments = $pdo->query("SELECT * FROM departments WHERE status='active' ORDER BY name")->fetchAll();

$edit = null;
if ($editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $stmt->execute([$editId]);
    $edit = $stmt->fetch();
}

$users = $pdo->query("
    SELECT u.*, d.name AS department_name
    FROM users u LEFT JOIN departments d ON d.id=u.department_id
    ORDER BY FIELD(u.role,'admin','staff'), u.name
")->fetchAll();
?>
<div class="grid gap-6 xl:grid-cols-[1fr_460px]">
<section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-100 p-5"><h2 class="font-bold">Staff accounts</h2><p class="mt-1 text-sm text-slate-500">Manage administrators and department helpdesk staff.</p></div>
    <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">User</th><th class="px-5 py-3">Department</th><th class="px-5 py-3">Role</th><th class="px-5 py-3">Status</th><th class="px-5 py-3"></th></tr></thead><tbody class="divide-y divide-slate-100">
    <?php foreach($users as $u): ?>
    <tr><td class="px-5 py-4"><div class="font-semibold"><?= admin_e($u['name']) ?></div><div class="text-xs text-slate-400"><?= admin_e($u['email']) ?></div></td><td class="px-5 py-4"><?= admin_e($u['department_name'] ?: 'All / General') ?></td><td class="px-5 py-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs"><?= admin_e($u['role']) ?></span></td><td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs <?= $u['status']==='active'?'bg-emerald-100 text-emerald-800':'bg-slate-100 text-slate-500' ?>"><?= admin_e($u['status']) ?></span></td><td class="px-5 py-4 text-right"><a href="staff.php?edit=<?= (int)$u['id'] ?>" class="font-semibold text-emerald-700">Edit</a></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</section>

<section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
<h2 class="font-bold"><?= $edit ? 'Edit user' : 'Add user' ?></h2>
<form method="post" class="mt-5 space-y-4">
<input type="hidden" name="csrf" value="<?= admin_e(admin_csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
<div><label class="mb-1.5 block text-sm font-semibold">Full name</label><input name="name" required value="<?= admin_e($edit['name'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5"></div>
<div><label class="mb-1.5 block text-sm font-semibold">Email</label><input type="email" name="email" required value="<?= admin_e($edit['email'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5"></div>
<div><label class="mb-1.5 block text-sm font-semibold">Password <?= $edit ? '(leave blank to keep current)' : '' ?></label><input type="password" name="password" <?= $edit ? '' : 'required' ?> minlength="8" class="w-full rounded-xl border border-slate-200 px-3 py-2.5"></div>
<div class="grid gap-4 sm:grid-cols-2"><div><label class="mb-1.5 block text-sm font-semibold">Role</label><select name="role" class="w-full rounded-xl border border-slate-200 px-3 py-2.5"><option value="admin" <?= ($edit['role'] ?? '')==='admin'?'selected':'' ?>>Admin</option><option value="staff" <?= ($edit['role'] ?? 'staff')==='staff'?'selected':'' ?>>Staff</option></select></div><div><label class="mb-1.5 block text-sm font-semibold">Department</label><select name="department_id" class="w-full rounded-xl border border-slate-200 px-3 py-2.5"><option value="">General / All</option><?php foreach($departments as $d): ?><option value="<?= (int)$d['id'] ?>" <?= ((int)($edit['department_id'] ?? 0)===(int)$d['id'])?'selected':'' ?>><?= admin_e($d['name']) ?></option><?php endforeach; ?></select></div></div>
<div class="grid gap-4 sm:grid-cols-2"><div><label class="mb-1.5 block text-sm font-semibold">Status</label><select name="status" class="w-full rounded-xl border border-slate-200 px-3 py-2.5"><?php foreach(['active','inactive','suspended'] as $v): ?><option value="<?= $v ?>" <?= ($edit['status'] ?? 'active')===$v?'selected':'' ?>><?= ucfirst($v) ?></option><?php endforeach; ?></select></div><div><label class="mb-1.5 block text-sm font-semibold">Availability</label><select name="availability" class="w-full rounded-xl border border-slate-200 px-3 py-2.5"><?php foreach(['online','away','offline'] as $v): ?><option value="<?= $v ?>" <?= ($edit['availability'] ?? 'offline')===$v?'selected':'' ?>><?= ucfirst($v) ?></option><?php endforeach; ?></select></div></div>
<button class="w-full rounded-xl bg-[#123b2b] px-4 py-3 font-semibold text-white">Save account</button>
</form>
<?php if($edit && (int)$edit['id'] !== (int)$admin['id']): ?><form method="post" class="mt-3" onsubmit="return confirm('Delete this user?')"><input type="hidden" name="csrf" value="<?= admin_e(admin_csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><button class="w-full rounded-xl border border-red-200 px-4 py-3 font-semibold text-red-700">Delete user</button></form><?php endif; ?>
</section>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
