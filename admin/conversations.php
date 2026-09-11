<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| UV-ASSIST Admin — Conversations
|--------------------------------------------------------------------------
|
| IMPORTANT:
| Authentication and POST processing happen BEFORE layout-top.php.
| This prevents "headers already sent" errors caused by the sidebar.
|
*/

require_once __DIR__ . '/admin_config.php';

date_default_timezone_set('Asia/Manila');

/*
|--------------------------------------------------------------------------
| Require Authentication BEFORE ANY HTML OUTPUT
|--------------------------------------------------------------------------
*/

$admin = require_admin_or_staff();

$adminId = (int) ($admin['id'] ?? 0);

if ($adminId <= 0) {
    admin_redirect('login.php');
}

$pageTitle = 'Conversations';

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function conversation_status_label(string $status): string
{
    return ucwords(str_replace('_', ' ', $status));
}

function conversation_status_class(string $status): string
{
    return match ($status) {
        'ai_active' => 'bg-blue-100 text-blue-700',
        'escalated' => 'bg-red-100 text-red-700',
        'waiting_for_staff' => 'bg-amber-100 text-amber-700',
        'staff_active' => 'bg-emerald-100 text-emerald-700',
        'resolved' => 'bg-green-100 text-green-700',
        'closed' => 'bg-slate-200 text-slate-600',
        'reopened' => 'bg-purple-100 text-purple-700',
        default => 'bg-slate-100 text-slate-600',
    };
}

function conversation_sender_label(array $message): string
{
    $senderType = $message['sender_type'] ?? '';

    if ($senderType === 'staff') {
        return $message['staff_name'] ?: 'Staff';
    }

    if ($senderType === 'ai') {
        return 'UV-ASSIST';
    }

    if ($senderType === 'system') {
        return 'System';
    }

    return 'Visitor';
}

/*
|--------------------------------------------------------------------------
| Handle POST Actions
|--------------------------------------------------------------------------
|
| This MUST happen before layout-top.php.
|
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    admin_verify_csrf($_POST['csrf'] ?? null);

    $action = trim((string) ($_POST['action'] ?? ''));

    /*
    |--------------------------------------------------------------------------
    | STAFF REPLY
    |--------------------------------------------------------------------------
    */

    if ($action === 'reply') {

        $conversationId = (int) ($_POST['conversation_id'] ?? 0);
        $message = trim((string) ($_POST['message'] ?? ''));

        if ($conversationId <= 0) {
            flash('error', 'Invalid conversation.');
            admin_redirect('conversations.php');
        }

        if ($message === '') {
            flash('error', 'Please enter a message.');
            admin_redirect('conversations.php?id=' . $conversationId);
        }

        if (mb_strlen($message) > 5000) {
            flash('error', 'Message is too long. Maximum length is 5,000 characters.');
            admin_redirect('conversations.php?id=' . $conversationId);
        }

        /*
        |--------------------------------------------------------------------------
        | Get Conversation
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT *
            FROM conversations
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$conversationId]);

        $conversation = $stmt->fetch();

        if (!$conversation) {
            flash('error', 'Conversation not found.');
            admin_redirect('conversations.php');
        }

        /*
        |--------------------------------------------------------------------------
        | Transaction
        |--------------------------------------------------------------------------
        */

        try {

            $pdo->beginTransaction();

            /*
            |--------------------------------------------------------------------------
            | Insert Staff Message
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                INSERT INTO messages
                (
                    conversation_id,
                    sender_type,
                    sender_id,
                    message,
                    message_type,
                    is_ai_generated
                )
                VALUES
                (
                    ?,
                    'staff',
                    ?,
                    ?,
                    'text',
                    0
                )
            ");

            $stmt->execute([
                $conversationId,
                $adminId,
                $message,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Assign Staff + Activate Conversation
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                UPDATE conversations
                SET
                    assigned_staff_id = ?,
                    status = 'staff_active',
                    last_message_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([
                $adminId,
                $conversationId,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Update Existing Escalation
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                UPDATE escalations
                SET
                    assigned_staff_id = ?,
                    status = 'in_progress',
                    accepted_at = COALESCE(accepted_at, NOW())
                WHERE conversation_id = ?
                  AND status IN ('pending', 'assigned', 'in_progress')
                  AND id = (
                      SELECT id
                      FROM (
                          SELECT id
                          FROM escalations
                          WHERE conversation_id = ?
                            AND status IN ('pending', 'assigned', 'in_progress')
                          ORDER BY id DESC
                          LIMIT 1
                      ) AS latest_escalation
                  )
            ");

            $stmt->execute([
                $adminId,
                $conversationId,
                $conversationId,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Analytics
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                INSERT INTO analytics_events
                (
                    conversation_id,
                    event_type,
                    metadata
                )
                VALUES
                (
                    ?,
                    'staff_response',
                    ?
                )
            ");

            $stmt->execute([
                $conversationId,
                json_encode(
                    [
                        'staff_id' => $adminId,
                    ],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
            ]);

            /*
            |--------------------------------------------------------------------------
            | Audit Log
            |--------------------------------------------------------------------------
            */

            audit_log(
                $adminId,
                'reply',
                'messages',
                null,
                null,
                [
                    'conversation_id' => $conversationId,
                    'staff_id' => $adminId,
                ]
            );

            $pdo->commit();

            flash('success', 'Reply sent successfully.');
        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'UV-ASSIST conversation reply error: ' .
                    $e->getMessage()
            );

            flash(
                'error',
                'Unable to send the reply. Please try again.'
            );
        }

        admin_redirect(
            'conversations.php?id=' . $conversationId
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CHANGE STATUS
    |--------------------------------------------------------------------------
    */

    if ($action === 'status') {

        $conversationId = (int) ($_POST['conversation_id'] ?? 0);
        $status = trim((string) ($_POST['status'] ?? ''));

        $allowedStatuses = [
            'ai_active',
            'escalated',
            'waiting_for_staff',
            'staff_active',
            'resolved',
            'closed',
            'reopened',
        ];

        if ($conversationId <= 0) {
            flash('error', 'Invalid conversation.');
            admin_redirect('conversations.php');
        }

        if (!in_array($status, $allowedStatuses, true)) {
            flash('error', 'Invalid conversation status.');
            admin_redirect(
                'conversations.php?id=' . $conversationId
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Get Current Status
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT status
            FROM conversations
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$conversationId]);

        $oldStatus = $stmt->fetchColumn();

        if ($oldStatus === false) {
            flash('error', 'Conversation not found.');
            admin_redirect('conversations.php');
        }

        /*
        |--------------------------------------------------------------------------
        | Update Conversation
        |--------------------------------------------------------------------------
        */

        try {

            $pdo->beginTransaction();

            $resolvedAtSql = in_array(
                $status,
                ['resolved', 'closed'],
                true
            )
                ? 'COALESCE(resolved_at, NOW())'
                : 'NULL';

            $closedAtSql = $status === 'closed'
                ? 'COALESCE(closed_at, NOW())'
                : 'NULL';

            $stmt = $pdo->prepare("
                UPDATE conversations
                SET
                    status = ?,
                    resolved_at = {$resolvedAtSql},
                    closed_at = {$closedAtSql}
                WHERE id = ?
            ");

            $stmt->execute([
                $status,
                $conversationId,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Analytics Event
            |--------------------------------------------------------------------------
            */

            $event = match ($status) {
                'resolved' => 'conversation_resolved',
                'closed' => 'conversation_closed',
                'reopened' => 'conversation_reopened',
                default => null,
            };

            if ($event !== null) {

                $stmt = $pdo->prepare("
                    INSERT INTO analytics_events
                    (
                        conversation_id,
                        event_type,
                        metadata
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?
                    )
                ");

                $stmt->execute([
                    $conversationId,
                    $event,
                    json_encode(
                        [
                            'staff_id' => $adminId,
                            'old_status' => $oldStatus,
                            'new_status' => $status,
                        ],
                        JSON_UNESCAPED_UNICODE |
                            JSON_UNESCAPED_SLASHES
                    ),
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Audit
            |--------------------------------------------------------------------------
            */

            audit_log(
                $adminId,
                'status_change',
                'conversations',
                $conversationId,
                [
                    'status' => $oldStatus,
                ],
                [
                    'status' => $status,
                ]
            );

            $pdo->commit();

            flash(
                'success',
                'Conversation status updated.'
            );
        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'UV-ASSIST status update error: ' .
                    $e->getMessage()
            );

            flash(
                'error',
                'Unable to update conversation status.'
            );
        }

        admin_redirect(
            'conversations.php?id=' . $conversationId
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Unknown Action
    |--------------------------------------------------------------------------
    */

    flash('error', 'Unknown action.');
    admin_redirect('conversations.php');
}

/*
|--------------------------------------------------------------------------
| Selected Conversation
|--------------------------------------------------------------------------
*/

$id = (int) ($_GET['id'] ?? 0);

$selected = null;
$messages = [];

if ($id > 0) {

    $stmt = $pdo->prepare("
        SELECT
            c.*,

            cv.name AS visitor_name,
            cv.email AS visitor_email,
            cv.user_type,
            cv.visitor_uuid,
            cv.current_page,

            d.name AS department_name,
            d.code AS department_code,

            u.name AS staff_name,
            u.email AS staff_email

        FROM conversations c

        LEFT JOIN chat_visitors cv
            ON cv.id = c.visitor_id

        LEFT JOIN departments d
            ON d.id = c.department_id

        LEFT JOIN users u
            ON u.id = c.assigned_staff_id

        WHERE c.id = ?

        LIMIT 1
    ");

    $stmt->execute([$id]);

    $selected = $stmt->fetch();

    if ($selected) {

        $stmt = $pdo->prepare("
            SELECT
                m.*,
                u.name AS staff_name,
                u.email AS staff_email
            FROM messages m
            LEFT JOIN users u
                ON u.id = m.sender_id
            WHERE m.conversation_id = ?
            ORDER BY m.id ASC
        ");

        $stmt->execute([$id]);

        $messages = $stmt->fetchAll();
    }
}

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$statusFilter = trim(
    (string) ($_GET['status'] ?? '')
);

$allowedStatuses = [
    'ai_active',
    'escalated',
    'waiting_for_staff',
    'staff_active',
    'resolved',
    'closed',
    'reopened',
];

if (
    $statusFilter !== '' &&
    !in_array($statusFilter, $allowedStatuses, true)
) {
    $statusFilter = '';
}

$q = trim(
    (string) ($_GET['q'] ?? '')
);

$where = [];
$params = [];

/*
|--------------------------------------------------------------------------
| Status Filter
|--------------------------------------------------------------------------
*/

if ($statusFilter !== '') {

    $where[] = 'c.status = ?';

    $params[] = $statusFilter;
}

/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

if ($q !== '') {

    $where[] = "
        (
            cv.name LIKE ?
            OR cv.email LIKE ?
            OR c.subject LIKE ?
            OR EXISTS (
                SELECT 1
                FROM messages mx
                WHERE mx.conversation_id = c.id
                  AND mx.message LIKE ?
            )
        )
    ";

    $searchTerm = '%' . $q . '%';

    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereSql = $where
    ? 'WHERE ' . implode(' AND ', $where)
    : '';

/*
|--------------------------------------------------------------------------
| Conversation List
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        c.*,

        cv.name AS visitor_name,
        cv.email AS visitor_email,
        cv.user_type,

        d.name AS department_name,

        u.name AS staff_name,

        (
            SELECT m2.message
            FROM messages m2
            WHERE m2.conversation_id = c.id
            ORDER BY m2.id DESC
            LIMIT 1
        ) AS last_message

    FROM conversations c

    LEFT JOIN chat_visitors cv
        ON cv.id = c.visitor_id

    LEFT JOIN departments d
        ON d.id = c.department_id

    LEFT JOIN users u
        ON u.id = c.assigned_staff_id

    {$whereSql}

    ORDER BY
        COALESCE(
            c.last_message_at,
            c.created_at
        ) DESC

    LIMIT 100
");

$stmt->execute($params);

$conversations = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Layout Starts ONLY NOW
|--------------------------------------------------------------------------
*/

require __DIR__ . '/partials/layout-top.php';

?>

<div class="grid gap-6 xl:grid-cols-[390px_1fr]">

    <!-- ================================================================
         CONVERSATION LIST
    ================================================================= -->

    <section
        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">

        <!-- Search -->
        <div class="border-b border-slate-100 p-4">

            <form
                method="get"
                action="conversations.php"
                class="flex gap-2">

                <?php if ($statusFilter !== ''): ?>

                    <input
                        type="hidden"
                        name="status"
                        value="<?= admin_e($statusFilter) ?>">

                <?php endif; ?>

                <input
                    type="search"
                    name="q"
                    value="<?= admin_e($q) ?>"
                    placeholder="Search conversations..."
                    class="min-w-0 flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/10">

                <button
                    type="submit"
                    class="rounded-xl bg-[#123b2b] px-4 text-sm font-semibold text-white transition hover:bg-[#0d2f23]">
                    Search
                </button>

            </form>

            <!-- Filters -->
            <div class="mt-3 flex gap-2 overflow-x-auto pb-1 text-xs">

                <?php
                $filters = [
                    '' => 'All',
                    'waiting_for_staff' => 'Waiting',
                    'staff_active' => 'Active',
                    'escalated' => 'Escalated',
                    'resolved' => 'Resolved',
                ];
                ?>

                <?php foreach ($filters as $key => $label): ?>

                    <?php

                    $filterParams = [];

                    if ($key !== '') {
                        $filterParams['status'] = $key;
                    }

                    if ($q !== '') {
                        $filterParams['q'] = $q;
                    }

                    ?>

                    <a
                        href="<?= admin_e(page_url('conversations.php', $filterParams)) ?>"
                        class="whitespace-nowrap rounded-full px-3 py-1.5 font-medium transition <?= $statusFilter === $key
                                                                                                        ? 'bg-emerald-700 text-white'
                                                                                                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                        <?= admin_e($label) ?>
                    </a>

                <?php endforeach; ?>

            </div>

        </div>

        <!-- Conversation Items -->
        <div class="max-h-[720px] divide-y divide-slate-100 overflow-y-auto">

            <?php foreach ($conversations as $conversation): ?>

                <?php

                $conversationId = (int) $conversation['id'];

                $visitorName =
                    trim((string) ($conversation['visitor_name'] ?? ''))
                    ?: 'Visitor';

                $initial = strtoupper(
                    substr($visitorName, 0, 1)
                );

                $status =
                    (string) ($conversation['status'] ?? '');

                $lastMessage =
                    trim((string) ($conversation['last_message'] ?? ''))
                    ?: 'No messages';

                $dateValue =
                    $conversation['last_message_at']
                    ?: $conversation['created_at'];

                $href =
                    'conversations.php?id=' .
                    $conversationId;

                if ($statusFilter !== '') {
                    $href .=
                        '&status=' .
                        urlencode($statusFilter);
                }

                if ($q !== '') {
                    $href .=
                        '&q=' .
                        urlencode($q);
                }

                ?>

                <a
                    href="<?= admin_e($href) ?>"
                    class="block p-4 transition <?= $id === $conversationId
                                                    ? 'bg-emerald-50'
                                                    : 'hover:bg-slate-50' ?>">

                    <div class="flex gap-3">

                        <!-- Avatar -->
                        <div
                            class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-emerald-100 font-bold text-emerald-800">
                            <?= admin_e($initial) ?>
                        </div>

                        <!-- Information -->
                        <div class="min-w-0 flex-1">

                            <div
                                class="flex items-center justify-between gap-2">

                                <span
                                    class="truncate text-sm font-semibold text-slate-800">
                                    <?= admin_e($visitorName) ?>
                                </span>

                                <span
                                    class="shrink-0 text-[10px] text-slate-400">
                                    <?= admin_e(
                                        date(
                                            'M j',
                                            strtotime($dateValue)
                                        )
                                    ) ?>
                                </span>

                            </div>

                            <div class="mt-1 flex flex-wrap gap-1">

                                <span
                                    class="rounded-full px-2 py-0.5 text-[10px] font-medium <?= conversation_status_class($status) ?>">
                                    <?= admin_e(
                                        conversation_status_label($status)
                                    ) ?>
                                </span>

                                <?php if (!empty($conversation['department_name'])): ?>

                                    <span
                                        class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-medium text-emerald-800">
                                        <?= admin_e(
                                            $conversation['department_name']
                                        ) ?>
                                    </span>

                                <?php endif; ?>

                            </div>

                            <p
                                class="mt-2 truncate text-xs text-slate-500">
                                <?= admin_e($lastMessage) ?>
                            </p>

                        </div>

                    </div>

                </a>

            <?php endforeach; ?>

            <?php if (!$conversations): ?>

                <div class="p-8 text-center">

                    <div
                        class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-slate-100 text-xl">
                        💬
                    </div>

                    <p
                        class="mt-3 text-sm font-semibold text-slate-700">
                        No conversations found
                    </p>

                    <p
                        class="mt-1 text-xs text-slate-400">
                        Try another search or filter.
                    </p>

                </div>

            <?php endif; ?>

        </div>

    </section>


    <!-- ================================================================
         CONVERSATION DETAIL
    ================================================================= -->

    <section
        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">

        <?php if (!$selected): ?>

            <div
                class="grid min-h-[650px] place-items-center p-8 text-center">

                <div>

                    <div
                        class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-emerald-100 text-2xl">
                        💬
                    </div>

                    <h2 class="mt-4 font-bold text-slate-800">
                        Select a conversation
                    </h2>

                    <p class="mt-2 text-sm text-slate-500">
                        Choose a conversation to inspect its history and reply.
                    </p>

                </div>

            </div>

        <?php else: ?>

            <?php

            $selectedStatus =
                (string) $selected['status'];

            $selectedVisitor =
                trim(
                    (string) ($selected['visitor_name'] ?? '')
                ) ?: 'Visitor';

            ?>

            <!-- Header -->
            <div
                class="flex flex-col gap-4 border-b border-slate-100 p-5 sm:flex-row sm:items-center sm:justify-between">

                <div class="min-w-0">

                    <div
                        class="flex flex-wrap items-center gap-2">

                        <h2 class="font-bold text-slate-800">
                            <?= admin_e($selectedVisitor) ?>
                        </h2>

                        <span
                            class="rounded-full px-2 py-1 text-xs font-medium <?= conversation_status_class($selectedStatus) ?>">
                            <?= admin_e(
                                conversation_status_label(
                                    $selectedStatus
                                )
                            ) ?>
                        </span>

                    </div>

                    <div
                        class="mt-1 flex flex-wrap gap-1 text-xs text-slate-500">

                        <span>
                            <?= admin_e(
                                ucfirst(
                                    (string) (
                                        $selected['user_type']
                                        ?? 'visitor'
                                    )
                                )
                            ) ?>
                        </span>

                        <?php if (!empty($selected['department_name'])): ?>

                            <span>·</span>

                            <span>
                                <?= admin_e(
                                    $selected['department_name']
                                ) ?>
                            </span>

                        <?php endif; ?>

                        <?php if (!empty($selected['staff_name'])): ?>

                            <span>·</span>

                            <span>
                                <?= admin_e(
                                    $selected['staff_name']
                                ) ?>
                            </span>

                        <?php endif; ?>

                    </div>

                </div>


                <!-- Status -->
                <form
                    method="post"
                    class="flex shrink-0 gap-2">

                    <input
                        type="hidden"
                        name="csrf"
                        value="<?= admin_e(
                                    admin_csrf_token()
                                ) ?>">

                    <input
                        type="hidden"
                        name="action"
                        value="status">

                    <input
                        type="hidden"
                        name="conversation_id"
                        value="<?= (int) $selected['id'] ?>">

                    <select
                        name="status"
                        onchange="this.form.submit()"
                        class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-medium outline-none focus:border-emerald-500">

                        <?php foreach ($allowedStatuses as $statusOption): ?>

                            <option
                                value="<?= admin_e($statusOption) ?>"
                                <?= $selectedStatus === $statusOption
                                    ? 'selected'
                                    : '' ?>>
                                <?= admin_e(
                                    conversation_status_label(
                                        $statusOption
                                    )
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </form>

            </div>


            <!-- Chat -->
            <div
                class="flex min-h-[520px] flex-col bg-[#f6f8f7]">

                <!-- Messages -->
                <div
                    id="conversationMessages"
                    class="flex-1 space-y-4 overflow-y-auto p-5">

                    <?php if (!$messages): ?>

                        <div
                            class="grid h-full min-h-[400px] place-items-center text-center">

                            <div>

                                <div class="text-3xl">
                                    💬
                                </div>

                                <p
                                    class="mt-2 text-sm text-slate-500">
                                    No messages yet.
                                </p>

                            </div>

                        </div>

                    <?php endif; ?>


                    <?php foreach ($messages as $message): ?>

                        <?php

                        $senderType =
                            (string) (
                                $message['sender_type']
                                ?? ''
                            );

                        $mine =
                            $senderType === 'staff';

                        $senderLabel =
                            conversation_sender_label(
                                $message
                            );

                        ?>

                        <div
                            class="flex <?= $mine
                                            ? 'justify-end'
                                            : 'justify-start' ?>">

                            <div
                                class="max-w-[78%]">

                                <!-- Sender -->
                                <div
                                    class="mb-1 px-1 text-[10px] font-semibold uppercase tracking-wider text-slate-400">

                                    <?= admin_e($senderLabel) ?>

                                    <?php if (
                                        $message['confidence_score']
                                        !== null
                                    ): ?>

                                        · confidence
                                        <?= number_format(
                                            (float) $message['confidence_score'],
                                            2
                                        ) ?>

                                    <?php endif; ?>

                                </div>


                                <!-- Bubble -->
                                <div
                                    class="rounded-2xl px-4 py-3 text-sm leading-6 <?= $mine
                                                                                        ? 'rounded-br-md bg-[#123b2b] text-white'
                                                                                        : 'rounded-bl-md border border-slate-200 bg-white text-slate-700 shadow-sm' ?>">

                                    <?= nl2br(
                                        admin_e(
                                            (string) $message['message']
                                        )
                                    ) ?>

                                </div>


                                <!-- Time -->
                                <?php if (!empty($message['created_at'])): ?>

                                    <div
                                        class="mt-1 px-1 text-[10px] text-slate-400 <?= $mine
                                                                                        ? 'text-right'
                                                                                        : 'text-left' ?>">
                                        <?= admin_e(
                                            date(
                                                'M j, Y g:i A',
                                                strtotime(
                                                    $message['created_at']
                                                )
                                            )
                                        ) ?>
                                    </div>

                                <?php endif; ?>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>


                <!-- Reply -->
                <form
                    method="post"
                    class="border-t border-slate-200 bg-white p-4">

                    <input
                        type="hidden"
                        name="csrf"
                        value="<?= admin_e(
                                    admin_csrf_token()
                                ) ?>">

                    <input
                        type="hidden"
                        name="action"
                        value="reply">

                    <input
                        type="hidden"
                        name="conversation_id"
                        value="<?= (int) $selected['id'] ?>">

                    <textarea
                        name="message"
                        required
                        rows="3"
                        maxlength="5000"
                        placeholder="Type a response to the visitor..."
                        class="w-full resize-none rounded-xl border border-slate-200 p-3 text-sm outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/10"></textarea>

                    <div
                        class="mt-3 flex items-center justify-between">

                        <span
                            class="text-xs text-slate-400">
                            Maximum 5,000 characters
                        </span>

                        <button
                            type="submit"
                            class="rounded-xl bg-[#123b2b] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#0d2f23] focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                            Send reply
                        </button>

                    </div>

                </form>

            </div>

        <?php endif; ?>

    </section>

</div>


<script>
    document.addEventListener('DOMContentLoaded', function() {

        const container =
            document.getElementById('conversationMessages');

        if (container) {
            container.scrollTop = container.scrollHeight;
        }

    });
</script>

<?php

require __DIR__ . '/partials/footer.php';
?>