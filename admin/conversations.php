<?php
declare(strict_types=1);
$pageTitle = 'Conversations';
require __DIR__ . '/partials/layout-top.php';

$admin = admin_user();
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_verify_csrf($_POST['csrf'] ?? null);
    $action = $_POST['action'] ?? '';

    if ($action === 'reply') {
        $conversationId = (int)($_POST['conversation_id'] ?? 0);
        $message = trim((string)($_POST['message'] ?? ''));

        if ($conversationId > 0 && $message !== '') {
            $stmt = $pdo->prepare("SELECT * FROM conversations WHERE id = ? LIMIT 1");
            $stmt->execute([$conversationId]);
            $conversation = $stmt->fetch();

            if ($conversation) {
                $pdo->beginTransaction();
                try {
                    $pdo->prepare("
                        INSERT INTO messages
                            (conversation_id, sender_type, sender_id, message, message_type, is_ai_generated)
                        VALUES (?, 'staff', ?, ?, 'text', 0)
                    ")->execute([$conversationId, (int)$admin['id'], $message]);

                    $pdo->prepare("
                        UPDATE conversations
                        SET assigned_staff_id = ?, status = 'staff_active', last_message_at = NOW()
                        WHERE id = ?
                    ")->execute([(int)$admin['id'], $conversationId]);

                    if ($conversation['department_id'] !== null && $conversation['assigned_staff_id'] === null) {
                        $pdo->prepare("
                            UPDATE escalations
                            SET assigned_staff_id = ?, status = 'in_progress', accepted_at = COALESCE(accepted_at, NOW())
                            WHERE conversation_id = ? AND status IN ('pending','assigned','in_progress')
                            ORDER BY id DESC LIMIT 1
                        ")->execute([(int)$admin['id'], $conversationId]);
                    }

                    $pdo->prepare("
                        INSERT INTO analytics_events (conversation_id, event_type, metadata)
                        VALUES (?, 'staff_response', ?)
                    ")->execute([$conversationId, json_encode(['staff_id' => (int)$admin['id']])]);

                    audit_log((int)$admin['id'], 'reply', 'messages', null, null, ['conversation_id' => $conversationId]);

                    $pdo->commit();
                    flash('success', 'Reply sent.');
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    error_log('Conversation reply: ' . $e->getMessage());
                    flash('error', 'Unable to send reply.');
                }
            }
        }
        admin_redirect('conversations.php?id=' . $conversationId);
    }

    if ($action === 'status') {
        $conversationId = (int)($_POST['conversation_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $allowed = ['ai_active','escalated','waiting_for_staff','staff_active','resolved','closed','reopened'];

        if ($conversationId > 0 && in_array($status, $allowed, true)) {
            $stmt = $pdo->prepare("SELECT status FROM conversations WHERE id = ?");
            $stmt->execute([$conversationId]);
            $old = $stmt->fetchColumn();

            $pdo->prepare("
                UPDATE conversations
                SET status = ?,
                    resolved_at = CASE WHEN ? IN ('resolved','closed') THEN COALESCE(resolved_at, NOW()) ELSE NULL END,
                    closed_at = CASE WHEN ? = 'closed' THEN COALESCE(closed_at, NOW()) ELSE NULL END
                WHERE id = ?
            ")->execute([$status, $status, $status, $conversationId]);

            $event = $status === 'resolved' ? 'conversation_resolved' : ($status === 'closed' ? 'conversation_closed' : ($status === 'reopened' ? 'conversation_reopened' : null));
            if ($event) {
                $pdo->prepare("INSERT INTO analytics_events (conversation_id, event_type, metadata) VALUES (?, ?, ?)")
                    ->execute([$conversationId, $event, json_encode(['staff_id' => (int)$admin['id']])]);
            }

            audit_log((int)$admin['id'], 'status_change', 'conversations', $conversationId, ['status' => $old], ['status' => $status]);
            flash('success', 'Conversation status updated.');
        }
        admin_redirect('conversations.php?id=' . $conversationId);
    }
}

$selected = null;
$messages = [];
if ($id > 0) {
    $stmt = $pdo->prepare("
        SELECT c.*, cv.name AS visitor_name, cv.email AS visitor_email, cv.user_type, cv.visitor_uuid,
               d.name AS department_name, u.name AS staff_name
        FROM conversations c
        LEFT JOIN chat_visitors cv ON cv.id = c.visitor_id
        LEFT JOIN departments d ON d.id = c.department_id
        LEFT JOIN users u ON u.id = c.assigned_staff_id
        WHERE c.id = ?
    ");
    $stmt->execute([$id]);
    $selected = $stmt->fetch();

    if ($selected) {
        $stmt = $pdo->prepare("SELECT m.*, u.name AS staff_name FROM messages m LEFT JOIN users u ON u.id = m.sender_id WHERE m.conversation_id = ? ORDER BY m.id ASC");
        $stmt->execute([$id]);
        $messages = $stmt->fetchAll();
    }
}

$statusFilter = trim((string)($_GET['status'] ?? ''));
$where = [];
$params = [];
if ($statusFilter !== '') {
    $allowedStatuses = ['ai_active','escalated','waiting_for_staff','staff_active','resolved','closed','reopened'];
    if (in_array($statusFilter, $allowedStatuses, true)) {
        $where[] = 'c.status = ?';
        $params[] = $statusFilter;
    }
}
$q = trim((string)($_GET['q'] ?? ''));
if ($q !== '') {
    $where[] = '(cv.name LIKE ? OR cv.email LIKE ? OR c.subject LIKE ? OR EXISTS (SELECT 1 FROM messages mx WHERE mx.conversation_id = c.id AND mx.message LIKE ?))';
    array_push($params, "%$q%", "%$q%", "%$q%", "%$q%");
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT c.*, cv.name AS visitor_name, cv.user_type, d.name AS department_name,
           u.name AS staff_name,
           (SELECT message FROM messages m2 WHERE m2.conversation_id = c.id ORDER BY m2.id DESC LIMIT 1) AS last_message
    FROM conversations c
    LEFT JOIN chat_visitors cv ON cv.id = c.visitor_id
    LEFT JOIN departments d ON d.id = c.department_id
    LEFT JOIN users u ON u.id = c.assigned_staff_id
    {$whereSql}
    ORDER BY COALESCE(c.last_message_at, c.created_at) DESC
    LIMIT 100
");
$stmt->execute($params);
$conversations = $stmt->fetchAll();
?>
<div class="grid gap-6 xl:grid-cols-[390px_1fr]">
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 p-4">
            <form class="flex gap-2">
                <input name="q" value="<?= admin_e($q) ?>" placeholder="Search conversations..." class="min-w-0 flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-emerald-500">
                <button class="rounded-xl bg-[#123b2b] px-4 text-sm font-semibold text-white">Search</button>
            </form>
            <div class="mt-3 flex gap-2 overflow-x-auto pb-1 text-xs">
                <?php foreach (['' => 'All','waiting_for_staff' => 'Waiting','staff_active' => 'Active','resolved' => 'Resolved'] as $key => $label): ?>
                    <a href="<?= page_url('conversations.php', $key ? ['status' => $key] : []) ?>" class="whitespace-nowrap rounded-full px-3 py-1.5 <?= $statusFilter === $key ? 'bg-emerald-700 text-white' : 'bg-slate-100 text-slate-600' ?>"><?= $label ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="max-h-[720px] overflow-y-auto divide-y divide-slate-100">
            <?php foreach ($conversations as $c): ?>
                <a href="conversations.php?id=<?= (int)$c['id'] ?><?= $statusFilter ? '&status='.urlencode($statusFilter) : '' ?>" class="block p-4 <?= $id === (int)$c['id'] ? 'bg-emerald-50' : 'hover:bg-slate-50' ?>">
                    <div class="flex gap-3">
                        <div class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-emerald-100 font-bold text-emerald-800">
                            <?= admin_e(strtoupper(substr($c['visitor_name'] ?: 'V', 0, 1))) ?>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <span class="truncate text-sm font-semibold"><?= admin_e($c['visitor_name'] ?: 'Visitor') ?></span>
                                <span class="text-[10px] text-slate-400"><?= admin_e(date('M j', strtotime($c['last_message_at'] ?: $c['created_at']))) ?></span>
                            </div>
                            <div class="mt-1 flex gap-1">
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px]"><?= admin_e(str_replace('_',' ',$c['status'])) ?></span>
                                <?php if ($c['department_name']): ?><span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] text-emerald-800"><?= admin_e($c['department_name']) ?></span><?php endif; ?>
                            </div>
                            <p class="mt-2 truncate text-xs text-slate-500"><?= admin_e($c['last_message'] ?: 'No messages') ?></p>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
            <?php if (!$conversations): ?><div class="p-8 text-center text-sm text-slate-500">No conversations found.</div><?php endif; ?>
        </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <?php if (!$selected): ?>
            <div class="grid min-h-[650px] place-items-center p-8 text-center">
                <div>
                    <div class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-emerald-100 text-2xl">☵</div>
                    <h2 class="mt-4 font-bold">Select a conversation</h2>
                    <p class="mt-2 text-sm text-slate-500">Choose a conversation to inspect its history and reply.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="flex items-center justify-between border-b border-slate-100 p-5">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="font-bold"><?= admin_e($selected['visitor_name'] ?: 'Visitor') ?></h2>
                        <span class="rounded-full bg-slate-100 px-2 py-1 text-xs"><?= admin_e(str_replace('_',' ',$selected['status'])) ?></span>
                    </div>
                    <div class="mt-1 text-xs text-slate-500">
                        <?= admin_e($selected['user_type']) ?>
                        <?php if ($selected['department_name']): ?> · <?= admin_e($selected['department_name']) ?><?php endif; ?>
                        <?php if ($selected['staff_name']): ?> · <?= admin_e($selected['staff_name']) ?><?php endif; ?>
                    </div>
                </div>
                <form method="post" class="flex gap-2">
                    <input type="hidden" name="csrf" value="<?= admin_e(admin_csrf_token()) ?>">
                    <input type="hidden" name="action" value="status">
                    <input type="hidden" name="conversation_id" value="<?= (int)$selected['id'] ?>">
                    <select name="status" onchange="this.form.submit()" class="rounded-xl border border-slate-200 px-3 py-2 text-xs">
                        <?php foreach (['ai_active','escalated','waiting_for_staff','staff_active','resolved','closed','reopened'] as $s): ?>
                            <option value="<?= $s ?>" <?= $selected['status'] === $s ? 'selected' : '' ?>><?= ucwords(str_replace('_',' ', $s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <div class="flex min-h-[520px] flex-col bg-[#f6f8f7]">
                <div class="flex-1 space-y-4 overflow-y-auto p-5">
                    <?php foreach ($messages as $m): ?>
                        <?php $mine = $m['sender_type'] === 'staff'; ?>
                        <div class="flex <?= $mine ? 'justify-end' : 'justify-start' ?>">
                            <div class="max-w-[78%]">
                                <div class="mb-1 px-1 text-[10px] font-semibold uppercase tracking-wider text-slate-400">
                                    <?= admin_e($mine ? ($m['staff_name'] ?: 'Staff') : ucfirst($m['sender_type'])) ?>
                                    <?php if ($m['confidence_score'] !== null): ?> · confidence <?= number_format((float)$m['confidence_score'], 2) ?><?php endif; ?>
                                </div>
                                <div class="rounded-2xl px-4 py-3 text-sm leading-6 <?= $mine ? 'rounded-br-md bg-[#123b2b] text-white' : 'rounded-bl-md border border-slate-200 bg-white text-slate-700' ?>">
                                    <?= nl2br(admin_e($m['message'])) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <form method="post" class="border-t border-slate-200 bg-white p-4">
                    <input type="hidden" name="csrf" value="<?= admin_e(admin_csrf_token()) ?>">
                    <input type="hidden" name="action" value="reply">
                    <input type="hidden" name="conversation_id" value="<?= (int)$selected['id'] ?>">
                    <textarea name="message" required rows="3" placeholder="Type a response to the visitor..." class="w-full resize-none rounded-xl border border-slate-200 p-3 text-sm outline-none focus:border-emerald-500"></textarea>
                    <div class="mt-3 flex justify-end">
                        <button class="rounded-xl bg-[#123b2b] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#0d2f23]">Send reply</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
