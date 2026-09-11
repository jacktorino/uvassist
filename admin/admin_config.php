<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';

date_default_timezone_set('Asia/Manila');

/*
|--------------------------------------------------------------------------
| Session
|--------------------------------------------------------------------------
*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Admin Session Keys
|--------------------------------------------------------------------------
*/

const ADMIN_SESSION_KEY = 'uv_assist_admin_id';
const ADMIN_ROLE_KEY = 'uv_assist_admin_role';
const ADMIN_CSRF_KEY = 'uv_assist_admin_csrf';

/*
|--------------------------------------------------------------------------
| HTML Escape
|--------------------------------------------------------------------------
*/

function admin_e(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}

/*
|--------------------------------------------------------------------------
| Redirect
|--------------------------------------------------------------------------
*/

function admin_redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/*
|--------------------------------------------------------------------------
| CSRF Token
|--------------------------------------------------------------------------
*/

function admin_csrf_token(): string
{
    if (
        empty($_SESSION[ADMIN_CSRF_KEY])
        || !is_string($_SESSION[ADMIN_CSRF_KEY])
    ) {
        $_SESSION[ADMIN_CSRF_KEY] = bin2hex(random_bytes(32));
    }

    return $_SESSION[ADMIN_CSRF_KEY];
}

/*
|--------------------------------------------------------------------------
| Verify CSRF Token
|--------------------------------------------------------------------------
*/

function admin_verify_csrf(?string $token): void
{
    $sessionToken = $_SESSION[ADMIN_CSRF_KEY] ?? '';

    if (
        !is_string($sessionToken)
        || !$token
        || !hash_equals($sessionToken, $token)
    ) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }
}

/*
|--------------------------------------------------------------------------
| Current Admin User
|--------------------------------------------------------------------------
*/

function admin_user(): ?array
{
    global $pdo;

    static $userLoaded = false;
    static $user = null;

    if ($userLoaded) {
        return $user;
    }

    $userLoaded = true;

    $id = (int) ($_SESSION[ADMIN_SESSION_KEY] ?? 0);

    if ($id <= 0) {
        return null;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT
                u.*,
                d.name AS department_name,
                d.code AS department_code
            FROM users u
            LEFT JOIN departments d
                ON d.id = u.department_id
            WHERE u.id = ?
            LIMIT 1
        ");

        $stmt->execute([$id]);

        $user = $stmt->fetch() ?: null;

        /*
        |--------------------------------------------------------------------------
        | Validate User
        |--------------------------------------------------------------------------
        */

        if (
            !$user
            || ($user['status'] ?? '') !== 'active'
        ) {
            unset(
                $_SESSION[ADMIN_SESSION_KEY],
                $_SESSION[ADMIN_ROLE_KEY]
            );

            $user = null;
        }

        return $user;
    } catch (Throwable $e) {

        error_log(
            'UV-ASSIST admin_user error: ' .
                $e->getMessage()
        );

        return null;
    }
}

/*
|--------------------------------------------------------------------------
| Require Admin
|--------------------------------------------------------------------------
*/

function require_admin(): array
{
    $user = admin_user();

    if (
        !$user
        || !in_array(
            $user['role'] ?? '',
            ['admin'],
            true
        )
    ) {
        admin_redirect('login.php');
    }

    return $user;
}

/*
|--------------------------------------------------------------------------
| Require Admin or Staff
|--------------------------------------------------------------------------
*/

function require_admin_or_staff(): array
{
    $user = admin_user();

    if (
        !$user
        || !in_array(
            $user['role'] ?? '',
            ['admin', 'staff'],
            true
        )
    ) {
        admin_redirect('login.php');
    }

    return $user;
}

/*
|--------------------------------------------------------------------------
| Audit Log
|--------------------------------------------------------------------------
*/

function audit_log(
    int $userId,
    string $action,
    string $tableName,
    ?int $recordId = null,
    ?array $oldValues = null,
    ?array $newValues = null
): void {
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs
            (
                user_id,
                action,
                table_name,
                record_id,
                old_values,
                new_values,
                ip_address
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?
            )
        ");

        $stmt->execute([
            $userId,
            $action,
            $tableName,
            $recordId,

            $oldValues !== null
                ? json_encode(
                    $oldValues,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                )
                : null,

            $newValues !== null
                ? json_encode(
                    $newValues,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                )
                : null,

            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable $e) {

        /*
        |--------------------------------------------------------------------------
        | Audit logging should never break the admin application
        |--------------------------------------------------------------------------
        */

        error_log(
            'UV-ASSIST audit log error: ' .
                $e->getMessage()
        );
    }
}

/*
|--------------------------------------------------------------------------
| Flash Message
|--------------------------------------------------------------------------
*/

function flash(
    string $type,
    string $message
): void {
    $_SESSION['uv_assist_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

/*
|--------------------------------------------------------------------------
| Consume Flash Message
|--------------------------------------------------------------------------
*/

function consume_flash(): ?array
{
    $flash = $_SESSION['uv_assist_flash'] ?? null;

    unset($_SESSION['uv_assist_flash']);

    return is_array($flash)
        ? $flash
        : null;
}

/*
|--------------------------------------------------------------------------
| Current Admin Page
|--------------------------------------------------------------------------
*/

function current_admin_page(): string
{
    return basename(
        $_SERVER['PHP_SELF'] ?? ''
    );
}

/*
|--------------------------------------------------------------------------
| Page URL
|--------------------------------------------------------------------------
*/

function page_url(
    string $page,
    array $params = []
): string {
    if (!$params) {
        return $page;
    }

    return $page . '?' . http_build_query($params);
}

/*
|--------------------------------------------------------------------------
| Count Rows
|--------------------------------------------------------------------------
*/

function count_rows(
    string $table,
    string $where = '1=1',
    array $params = []
): int {
    global $pdo;

    $allowed = [
        'conversations',
        'messages',
        'knowledge_documents',
        'knowledge_chunks',
        'departments',
        'users',
        'escalations',
        'analytics_events',
        'audit_logs',
    ];

    if (!in_array($table, $allowed, true)) {
        throw new InvalidArgumentException(
            'Invalid table.'
        );
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE {$where}"
    );

    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}
