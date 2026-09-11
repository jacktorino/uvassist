<?php
declare(strict_types=1);
require_once __DIR__ . '/admin_config.php';

$user = admin_user();
if ($user) {
    audit_log((int)$user['id'], 'logout', 'users', (int)$user['id'], null, ['logout_at' => date('c')]);
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

header('Location: login.php');
exit;
?>
