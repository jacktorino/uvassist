<?php

declare(strict_types=1);

require_once __DIR__ . '/admin_config.php';

$user = require_admin_or_staff();

$pageTitle = 'Dashboard';

/*
|--------------------------------------------------------------------------
| Dashboard Statistics
|--------------------------------------------------------------------------
*/

$totalConversations = count_rows('conversations');

$activeConversations = count_rows(
    'conversations',
    "status IN ('ai_active','escalated','waiting_for_staff','staff_active','reopened')"
);

$waiting = count_rows(
    'conversations',
    "status = 'waiting_for_staff'"
);

$resolved = count_rows(
    'conversations',
    "status IN ('resolved','closed')"
);

$kbPublished = count_rows(
    'knowledge_documents',
    "status = 'published'"
);

$kbDrafts = count_rows(
    'knowledge_documents',
    "status = 'draft'"
);

$staffCount = count_rows(
    'users',
    "role = 'staff' AND status = 'active'"
);

/*
|--------------------------------------------------------------------------
| Recent Conversations
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        c.*,
        cv.name AS visitor_name,
        cv.user_type,
        d.name AS department_name,

        (
            SELECT message
            FROM messages m
            WHERE m.conversation_id = c.id
            ORDER BY m.id DESC
            LIMIT 1
        ) AS last_message

    FROM conversations c

    LEFT JOIN chat_visitors cv
        ON cv.id = c.visitor_id

    LEFT JOIN departments d
        ON d.id = c.department_id

    ORDER BY
        COALESCE(c.last_message_at, c.created_at) DESC

    LIMIT 8
");

$recent = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Conversation Activity — Last 7 Days
|--------------------------------------------------------------------------
*/

$daily = $pdo->query("
    SELECT
        DATE(created_at) AS day,
        COUNT(*) AS total

    FROM conversations

    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)

    GROUP BY DATE(created_at)

    ORDER BY day
")->fetchAll();

$chart = [];

for ($i = 6; $i >= 0; $i--) {
    $day = date(
        'Y-m-d',
        strtotime("-{$i} days")
    );

    $chart[$day] = 0;
}

foreach ($daily as $row) {
    $chart[$row['day']] = (int) $row['total'];
}

$maxChart = max(
    1,
    ...array_values($chart)
);

/*
|--------------------------------------------------------------------------
| HTML Layout
|--------------------------------------------------------------------------
|
| IMPORTANT:
| Authentication and all redirects have already happened above.
| It is now safe for layout-top.php/sidebar.php to output HTML.
|
*/

require __DIR__ . '/partials/layout-top.php';

?>

<div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">

    <?php

    $cards = [
        [
            'Total Conversations',
            $totalConversations,
            'All recorded conversations'
        ],
        [
            'Active',
            $activeConversations,
            'Currently open or in progress'
        ],
        [
            'Waiting for Staff',
            $waiting,
            'Need human attention'
        ],
        [
            'Resolved / Closed',
            $resolved,
            'Completed conversations'
        ],
    ];

    foreach ($cards as [$label, $value, $hint]):

    ?>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">

            <div class="text-sm font-medium text-slate-500">
                <?= admin_e($label) ?>
            </div>

            <div class="mt-2 text-3xl font-extrabold">
                <?= number_format($value) ?>
            </div>

            <div class="mt-2 text-xs text-slate-400">
                <?= admin_e($hint) ?>
            </div>

        </div>

    <?php endforeach; ?>

</div>


<div class="mt-6 grid gap-6 xl:grid-cols-[1.5fr_1fr]">

    <!-- Conversation Activity -->

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">

        <div class="flex items-center justify-between">

            <div>

                <h2 class="font-bold">
                    Conversation activity
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    Conversations started during the last 7 days.
                </p>

            </div>

            <a
                href="analytics.php"
                class="text-sm font-semibold text-emerald-700">
                View analytics →
            </a>

        </div>


        <div class="mt-8 flex h-56 items-end gap-3">

            <?php

            foreach ($chart as $day => $total):

                $height = max(
                    5,
                    (int) (($total / $maxChart) * 100)
                );

            ?>

                <div class="flex h-full flex-1 flex-col justify-end gap-2">

                    <div class="text-center text-xs font-semibold text-slate-500">
                        <?= $total ?>
                    </div>

                    <div
                        class="rounded-t-lg bg-emerald-700/90"
                        style="height: <?= $height ?>%"></div>

                    <div class="text-center text-[10px] text-slate-400">
                        <?= date('D', strtotime($day)) ?>
                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    </section>


    <!-- System Overview -->

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">

        <h2 class="font-bold">
            System overview
        </h2>

        <div class="mt-5 space-y-3">

            <a
                href="knowledge-base.php"
                class="flex items-center justify-between rounded-xl bg-slate-50 p-4 hover:bg-emerald-50">
                <span>
                    <b><?= $kbPublished ?></b>
                    published KB documents
                </span>

                <span>→</span>
            </a>


            <a
                href="knowledge-base.php?status=draft"
                class="flex items-center justify-between rounded-xl bg-slate-50 p-4 hover:bg-emerald-50">
                <span>
                    <b><?= $kbDrafts ?></b>
                    draft documents
                </span>

                <span>→</span>
            </a>


            <a
                href="staff.php"
                class="flex items-center justify-between rounded-xl bg-slate-50 p-4 hover:bg-emerald-50">
                <span>
                    <b><?= $staffCount ?></b>
                    active staff
                </span>

                <span>→</span>
            </a>


            <a
                href="escalations.php"
                class="flex items-center justify-between rounded-xl bg-slate-50 p-4 hover:bg-emerald-50">
                <span>
                    <b><?= $waiting ?></b>
                    conversations waiting for staff
                </span>

                <span>→</span>
            </a>

        </div>

    </section>

</div>


<!-- Recent Conversations -->

<section class="mt-6 rounded-2xl border border-slate-200 bg-white shadow-sm">

    <div class="flex items-center justify-between border-b border-slate-100 px-6 py-5">

        <div>

            <h2 class="font-bold">
                Recent conversations
            </h2>

            <p class="mt-1 text-sm text-slate-500">
                Latest activity across the helpdesk.
            </p>

        </div>

        <a
            href="conversations.php"
            class="text-sm font-semibold text-emerald-700">
            View all →
        </a>

    </div>


    <div class="divide-y divide-slate-100">

        <?php if (!$recent): ?>

            <div class="px-6 py-10 text-center text-sm text-slate-500">
                No conversations yet.
            </div>

        <?php else: ?>

            <?php foreach ($recent as $row): ?>

                <a
                    href="conversations.php?id=<?= (int) $row['id'] ?>"
                    class="flex items-center gap-4 px-6 py-4 hover:bg-slate-50">

                    <div class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-emerald-100 font-bold text-emerald-800">

                        <?= admin_e(
                            strtoupper(
                                substr(
                                    $row['visitor_name']
                                        ?: $row['user_type']
                                        ?: 'V',
                                    0,
                                    1
                                )
                            )
                        ) ?>

                    </div>


                    <div class="min-w-0 flex-1">

                        <div class="flex flex-wrap items-center gap-2">

                            <span class="font-semibold">
                                <?= admin_e(
                                    $row['visitor_name']
                                        ?: 'Visitor'
                                ) ?>
                            </span>


                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px]">

                                <?= admin_e(
                                    str_replace(
                                        '_',
                                        ' ',
                                        $row['status']
                                    )
                                ) ?>

                            </span>


                            <?php if ($row['department_name']): ?>

                                <span class="text-xs text-slate-400">

                                    <?= admin_e(
                                        $row['department_name']
                                    ) ?>

                                </span>

                            <?php endif; ?>

                        </div>


                        <div class="mt-1 truncate text-sm text-slate-500">

                            <?= admin_e(
                                $row['last_message']
                                    ?: 'No messages'
                            ) ?>

                        </div>

                    </div>


                    <div class="hidden text-xs text-slate-400 sm:block">

                        <?= admin_e(
                            date(
                                'M j, g:i A',
                                strtotime(
                                    $row['last_message_at']
                                        ?: $row['created_at']
                                )
                            )
                        ) ?>

                    </div>

                </a>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</section>


<?php require __DIR__ . '/partials/footer.php'; ?>