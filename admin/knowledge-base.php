<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| UV-ASSIST Admin — Knowledge Base
|--------------------------------------------------------------------------
|
| IMPORTANT:
| Authentication, POST actions, redirects, and database processing
| happen BEFORE layout-top.php outputs any HTML.
|
*/

require_once __DIR__ . '/admin_config.php';

date_default_timezone_set('Asia/Manila');

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

$admin = require_admin_or_staff();

$adminId = (int) ($admin['id'] ?? 0);

if ($adminId <= 0) {
    admin_redirect('login.php');
}

$pageTitle = 'Knowledge Base';

/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function kb_status_class(string $status): string
{
    return match ($status) {
        'published' => 'bg-emerald-100 text-emerald-800',
        'draft' => 'bg-amber-100 text-amber-800',
        'archived' => 'bg-slate-100 text-slate-600',
        default => 'bg-slate-100 text-slate-600',
    };
}

function kb_status_label(string $status): string
{
    return ucfirst($status);
}

/*
|--------------------------------------------------------------------------
| POST ACTIONS
|--------------------------------------------------------------------------
|
| These MUST happen before layout-top.php.
|
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    admin_verify_csrf($_POST['csrf'] ?? null);

    $action = trim((string) ($_POST['action'] ?? ''));

    /*
    |--------------------------------------------------------------------------
    | SAVE DOCUMENT
    |--------------------------------------------------------------------------
    */

    if ($action === 'save') {

        $id = (int) ($_POST['id'] ?? 0);

        $title = trim(
            (string) ($_POST['title'] ?? '')
        );

        $content = trim(
            (string) ($_POST['content'] ?? '')
        );

        $categoryId =
            (int) ($_POST['category_id'] ?? 0);

        $categoryId =
            $categoryId > 0
            ? $categoryId
            : null;

        $departmentId =
            (int) ($_POST['department_id'] ?? 0);

        $departmentId =
            $departmentId > 0
            ? $departmentId
            : null;

        $sourceType =
            trim(
                (string) (
                    $_POST['source_type']
                    ?? 'faq'
                )
            );

        $sourceReference =
            trim(
                (string) (
                    $_POST['source_reference']
                    ?? ''
                )
            );

        $sourceReference =
            $sourceReference !== ''
            ? $sourceReference
            : null;

        $version =
            trim(
                (string) (
                    $_POST['version']
                    ?? ''
                )
            );

        $version =
            $version !== ''
            ? $version
            : null;

        $status =
            trim(
                (string) (
                    $_POST['status']
                    ?? 'draft'
                )
            );

        $effectiveDate =
            trim(
                (string) (
                    $_POST['effective_date']
                    ?? ''
                )
            );

        $effectiveDate =
            $effectiveDate !== ''
            ? $effectiveDate
            : null;

        $expiresAt =
            trim(
                (string) (
                    $_POST['expires_at']
                    ?? ''
                )
            );

        $expiresAt =
            $expiresAt !== ''
            ? $expiresAt
            : null;

        $validSourceTypes = [
            'faq',
            'policy',
            'procedure',
            'announcement',
            'office_information',
            'document',
            'other',
        ];

        $validStatuses = [
            'draft',
            'published',
            'archived',
        ];

        /*
        |--------------------------------------------------------------------------
        | Basic Validation
        |--------------------------------------------------------------------------
        */

        if (
            $title === '' ||
            $content === '' ||
            !in_array(
                $sourceType,
                $validSourceTypes,
                true
            ) ||
            !in_array(
                $status,
                $validStatuses,
                true
            )
        ) {

            flash(
                'error',
                'Title, content, source type, and status are required.'
            );
        } elseif (mb_strlen($title) > 255) {

            flash(
                'error',
                'The document title is too long.'
            );
        } else {

            try {

                $pdo->beginTransaction();

                $old = null;

                /*
                |--------------------------------------------------------------------------
                | UPDATE EXISTING DOCUMENT
                |--------------------------------------------------------------------------
                */

                if ($id > 0) {

                    $stmt = $pdo->prepare("
                        SELECT *
                        FROM knowledge_documents
                        WHERE id = ?
                        LIMIT 1
                        FOR UPDATE
                    ");

                    $stmt->execute([$id]);

                    $old = $stmt->fetch();

                    if (!$old) {
                        throw new RuntimeException(
                            'Knowledge document not found.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Update Document
                    |--------------------------------------------------------------------------
                    */

                    $stmt = $pdo->prepare("
                        UPDATE knowledge_documents
                        SET
                            category_id = ?,
                            department_id = ?,
                            title = ?,
                            content = ?,
                            source_type = ?,
                            source_reference = ?,
                            version = ?,
                            status = ?,
                            effective_date = ?,
                            expires_at = ?,
                            updated_by = ?,
                            published_at =
                                CASE
                                    WHEN ? = 'published'
                                    THEN COALESCE(
                                        published_at,
                                        NOW()
                                    )
                                    ELSE published_at
                                END
                        WHERE id = ?
                    ");

                    $stmt->execute([
                        $categoryId,
                        $departmentId,
                        $title,
                        $content,
                        $sourceType,
                        $sourceReference,
                        $version,
                        $status,
                        $effectiveDate,
                        $expiresAt,
                        $adminId,
                        $status,
                        $id,
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | Rebuild Chunks
                    |--------------------------------------------------------------------------
                    */

                    $stmt = $pdo->prepare("
                        DELETE FROM knowledge_chunks
                        WHERE document_id = ?
                    ");

                    $stmt->execute([$id]);

                    $documentId = $id;

                    /*
                |--------------------------------------------------------------------------
                | CREATE NEW DOCUMENT
                |--------------------------------------------------------------------------
                */
                } else {

                    $stmt = $pdo->prepare("
                        INSERT INTO knowledge_documents
                        (
                            category_id,
                            department_id,
                            title,
                            content,
                            source_type,
                            source_reference,
                            version,
                            status,
                            effective_date,
                            expires_at,
                            created_by,
                            updated_by,
                            published_at
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            CASE
                                WHEN ? = 'published'
                                THEN NOW()
                                ELSE NULL
                            END
                        )
                    ");

                    $stmt->execute([
                        $categoryId,
                        $departmentId,
                        $title,
                        $content,
                        $sourceType,
                        $sourceReference,
                        $version,
                        $status,
                        $effectiveDate,
                        $expiresAt,
                        $adminId,
                        $adminId,
                        $status,
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | Get Newly Created Document ID
                    |--------------------------------------------------------------------------
                    */

                    $documentId =
                        (int) $pdo->lastInsertId();

                    if ($documentId <= 0) {
                        throw new RuntimeException(
                            'Unable to determine the new document ID.'
                        );
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | Generate Knowledge Chunks
                |--------------------------------------------------------------------------
                |
                | We split the content into chunks of approximately 900
                | characters for retrieval.
                |
                */

                $paragraphs = preg_split(
                    '/\R\s*\R/',
                    $content
                ) ?: [$content];

                $chunks = [];

                foreach ($paragraphs as $paragraph) {

                    $paragraph = trim(
                        (string) $paragraph
                    );

                    if ($paragraph === '') {
                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Paragraph Fits Within Chunk
                    |--------------------------------------------------------------------------
                    */

                    if (strlen($paragraph) <= 900) {

                        $chunks[] = $paragraph;

                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Split Large Paragraph By Words
                    |--------------------------------------------------------------------------
                    */

                    $words = preg_split(
                        '/\s+/',
                        $paragraph
                    ) ?: [];

                    $buffer = '';

                    foreach ($words as $word) {

                        $word = trim(
                            (string) $word
                        );

                        if ($word === '') {
                            continue;
                        }

                        $candidate =
                            $buffer === ''
                            ? $word
                            : $buffer . ' ' . $word;

                        if (
                            $buffer !== '' &&
                            strlen($candidate) > 900
                        ) {

                            $chunks[] = $buffer;

                            $buffer = $word;
                        } else {

                            $buffer = $candidate;
                        }
                    }

                    if ($buffer !== '') {
                        $chunks[] = $buffer;
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | Fallback
                |--------------------------------------------------------------------------
                */

                if (!$chunks) {
                    $chunks = [$content];
                }

                /*
                |--------------------------------------------------------------------------
                | Insert Chunks
                |--------------------------------------------------------------------------
                */

                $chunkStmt = $pdo->prepare("
                    INSERT INTO knowledge_chunks
                    (
                        document_id,
                        chunk_index,
                        content,
                        token_count
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?
                    )
                ");

                foreach ($chunks as $index => $chunk) {

                    $chunk = trim(
                        (string) $chunk
                    );

                    if ($chunk === '') {
                        continue;
                    }

                    $tokenParts = preg_split(
                        '/\s+/',
                        $chunk
                    ) ?: [];

                    $tokenParts = array_filter(
                        $tokenParts,
                        static fn($token) =>
                        trim((string) $token) !== ''
                    );

                    $tokenCount =
                        count($tokenParts);

                    $chunkStmt->execute([
                        $documentId,
                        $index + 1,
                        $chunk,
                        $tokenCount,
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Commit
                |--------------------------------------------------------------------------
                */

                $pdo->commit();

                /*
                |--------------------------------------------------------------------------
                | Audit Log
                |--------------------------------------------------------------------------
                */

                audit_log(
                    $adminId,
                    $id > 0
                        ? 'update'
                        : 'create',
                    'knowledge_documents',
                    $documentId,
                    $old,
                    [
                        'title' => $title,
                        'status' => $status,
                        'category_id' => $categoryId,
                        'department_id' => $departmentId,
                        'source_type' => $sourceType,
                        'version' => $version,
                        'chunk_count' => count($chunks),
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | Success
                |--------------------------------------------------------------------------
                */

                flash(
                    'success',
                    $id > 0
                        ? 'Knowledge document updated successfully.'
                        : 'Knowledge document created successfully.'
                );

                /*
                |--------------------------------------------------------------------------
                | Redirect
                |--------------------------------------------------------------------------
                */

                admin_redirect(
                    'knowledge-base.php'
                );
            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log(
                    'UV-ASSIST KB save error: ' .
                        $e->getMessage()
                );

                flash(
                    'error',
                    'Unable to save the knowledge document: ' .
                        $e->getMessage()
                );
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE DOCUMENT
    |--------------------------------------------------------------------------
    */

    if ($action === 'delete') {

        $id = (int) (
            $_POST['id'] ?? 0
        );

        if ($id <= 0) {

            flash(
                'error',
                'Invalid document.'
            );

            admin_redirect(
                'knowledge-base.php'
            );
        }

        try {

            $stmt = $pdo->prepare("
                SELECT *
                FROM knowledge_documents
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([$id]);

            $old = $stmt->fetch();

            if (!$old) {

                flash(
                    'error',
                    'Knowledge document not found.'
                );

                admin_redirect(
                    'knowledge-base.php'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Delete Document
            |--------------------------------------------------------------------------
            |
            | knowledge_chunks should normally be removed automatically if
            | the FK uses ON DELETE CASCADE. If not, delete chunks first.
            |
            */

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                DELETE FROM knowledge_chunks
                WHERE document_id = ?
            ");

            $stmt->execute([$id]);

            $stmt = $pdo->prepare("
                DELETE FROM knowledge_documents
                WHERE id = ?
            ");

            $stmt->execute([$id]);

            $pdo->commit();

            audit_log(
                $adminId,
                'delete',
                'knowledge_documents',
                $id,
                $old,
                null
            );

            flash(
                'success',
                'Knowledge document deleted successfully.'
            );
        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'UV-ASSIST KB delete error: ' .
                    $e->getMessage()
            );

            flash(
                'error',
                'Unable to delete the knowledge document.'
            );
        }

        admin_redirect(
            'knowledge-base.php'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Unknown Action
    |--------------------------------------------------------------------------
    */

    flash(
        'error',
        'Unknown knowledge base action.'
    );

    admin_redirect(
        'knowledge-base.php'
    );
}

/*
|--------------------------------------------------------------------------
| Categories
|--------------------------------------------------------------------------
*/

$categories = $pdo
    ->query("
        SELECT *
        FROM knowledge_categories
        WHERE status = 'active'
        ORDER BY name ASC
    ")
    ->fetchAll();

/*
|--------------------------------------------------------------------------
| Departments
|--------------------------------------------------------------------------
*/

$departments = $pdo
    ->query("
        SELECT *
        FROM departments
        WHERE status = 'active'
        ORDER BY name ASC
    ")
    ->fetchAll();

/*
|--------------------------------------------------------------------------
| Edit Document
|--------------------------------------------------------------------------
*/

$editId = (int) (
    $_GET['edit'] ?? 0
);

$edit = null;

if ($editId > 0) {

    $stmt = $pdo->prepare("
        SELECT *
        FROM knowledge_documents
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$editId]);

    $edit = $stmt->fetch();

    if (!$edit) {

        flash(
            'error',
            'Knowledge document not found.'
        );

        admin_redirect(
            'knowledge-base.php'
        );
    }
}

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$status = trim(
    (string) (
        $_GET['status'] ?? ''
    )
);

$validStatuses = [
    'draft',
    'published',
    'archived',
];

if (
    $status !== '' &&
    !in_array(
        $status,
        $validStatuses,
        true
    )
) {
    $status = '';
}

$q = trim(
    (string) (
        $_GET['q'] ?? ''
    )
);

$where = [];
$params = [];

/*
|--------------------------------------------------------------------------
| Status Filter
|--------------------------------------------------------------------------
*/

if ($status !== '') {

    $where[] = 'kd.status = ?';

    $params[] = $status;
}

/*
|--------------------------------------------------------------------------
| Search Filter
|--------------------------------------------------------------------------
*/

if ($q !== '') {

    $where[] = "
        (
            kd.title LIKE ?
            OR kd.content LIKE ?
        )
    ";

    $search = '%' . $q . '%';

    $params[] = $search;
    $params[] = $search;
}

$whereSql = $where
    ? 'WHERE ' . implode(
        ' AND ',
        $where
    )
    : '';

/*
|--------------------------------------------------------------------------
| Get Documents
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        kd.*,

        kc.name AS category_name,

        d.name AS department_name,

        (
            SELECT COUNT(*)
            FROM knowledge_chunks ch
            WHERE ch.document_id = kd.id
        ) AS chunk_count

    FROM knowledge_documents kd

    LEFT JOIN knowledge_categories kc
        ON kc.id = kd.category_id

    LEFT JOIN departments d
        ON d.id = kd.department_id

    {$whereSql}

    ORDER BY kd.updated_at DESC

    LIMIT 200
");

$stmt->execute($params);

$documents = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| NOW OUTPUT THE LAYOUT
|--------------------------------------------------------------------------
|
| Nothing above this point should output HTML.
|
*/

require __DIR__ . '/partials/layout-top.php';

?>

<div class="grid gap-6 xl:grid-cols-[1fr_460px]">

    <!-- ================================================================
         DOCUMENT LIST
    ================================================================= -->

    <section
        class="rounded-2xl border border-slate-200 bg-white shadow-sm">

        <div class="border-b border-slate-100 p-5">

            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

                <div>

                    <h2 class="font-bold text-slate-800">
                        Knowledge documents
                    </h2>

                    <p class="mt-1 text-sm text-slate-500">
                        Manage the information used by UV-ASSIST retrieval.
                    </p>

                </div>

                <a
                    href="knowledge-base.php"
                    class="rounded-xl bg-[#123b2b] px-4 py-2.5 text-center text-sm font-semibold text-white transition hover:bg-[#0d2f23]">
                    + New document
                </a>

            </div>


            <!-- Search -->
            <form
                method="get"
                action="knowledge-base.php"
                class="mt-4 flex gap-2">

                <?php if ($status !== ''): ?>

                    <input
                        type="hidden"
                        name="status"
                        value="<?= admin_e($status) ?>">

                <?php endif; ?>

                <input
                    type="search"
                    name="q"
                    value="<?= admin_e($q) ?>"
                    placeholder="Search title or content..."
                    class="min-w-0 flex-1 rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/10">

                <button
                    type="submit"
                    class="rounded-xl border border-slate-200 px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    Search
                </button>

            </form>


            <!-- Status filters -->
            <div class="mt-4 flex flex-wrap gap-2">

                <?php

                $statusFilters = [
                    '' => 'All',
                    'published' => 'Published',
                    'draft' => 'Draft',
                    'archived' => 'Archived',
                ];

                ?>

                <?php foreach ($statusFilters as $filterKey => $filterLabel): ?>

                    <?php

                    $filterParams = [];

                    if ($filterKey !== '') {
                        $filterParams['status'] =
                            $filterKey;
                    }

                    if ($q !== '') {
                        $filterParams['q'] = $q;
                    }

                    ?>

                    <a
                        href="<?= admin_e(
                                    page_url(
                                        'knowledge-base.php',
                                        $filterParams
                                    )
                                ) ?>"
                        class="rounded-full px-3 py-1.5 text-xs font-medium transition <?= $status === $filterKey
                                                                                            ? 'bg-emerald-700 text-white'
                                                                                            : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                        <?= admin_e($filterLabel) ?>
                    </a>

                <?php endforeach; ?>

            </div>

        </div>


        <!-- Table -->
        <div class="overflow-x-auto">

            <table class="w-full text-left text-sm">

                <thead
                    class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">

                    <tr>

                        <th class="px-5 py-3">
                            Document
                        </th>

                        <th class="px-5 py-3">
                            Department
                        </th>

                        <th class="px-5 py-3">
                            Status
                        </th>

                        <th class="px-5 py-3">
                            Chunks
                        </th>

                        <th class="px-5 py-3">
                        </th>

                    </tr>

                </thead>


                <tbody
                    class="divide-y divide-slate-100">

                    <?php foreach ($documents as $doc): ?>

                        <tr
                            class="transition hover:bg-slate-50">

                            <!-- Document -->
                            <td class="px-5 py-4">

                                <div
                                    class="font-semibold text-slate-800">
                                    <?= admin_e(
                                        (string) $doc['title']
                                    ) ?>
                                </div>

                                <div
                                    class="mt-1 text-xs text-slate-400">

                                    <?= admin_e(
                                        (string) (
                                            $doc['category_name']
                                            ?: 'Uncategorized'
                                        )
                                    ) ?>

                                    ·

                                    <?= admin_e(
                                        ucwords(
                                            str_replace(
                                                '_',
                                                ' ',
                                                (string) $doc['source_type']
                                            )
                                        )
                                    ) ?>

                                </div>

                            </td>


                            <!-- Department -->
                            <td
                                class="px-5 py-4 text-slate-600">

                                <?= admin_e(
                                    (string) (
                                        $doc['department_name']
                                        ?: 'General'
                                    )
                                ) ?>

                            </td>


                            <!-- Status -->
                            <td class="px-5 py-4">

                                <span
                                    class="rounded-full px-2.5 py-1 text-xs font-medium <?= kb_status_class(
                                                                                            (string) $doc['status']
                                                                                        ) ?>">
                                    <?= admin_e(
                                        kb_status_label(
                                            (string) $doc['status']
                                        )
                                    ) ?>
                                </span>

                            </td>


                            <!-- Chunks -->
                            <td
                                class="px-5 py-4 text-slate-500">
                                <?= (int) $doc['chunk_count'] ?>
                            </td>


                            <!-- Edit -->
                            <td
                                class="px-5 py-4 text-right">

                                <a
                                    href="knowledge-base.php?edit=<?= (int) $doc['id'] ?>"
                                    class="font-semibold text-emerald-700 hover:text-emerald-800">
                                    Edit
                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>


            <?php if (!$documents): ?>

                <div class="p-10 text-center">

                    <div
                        class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-slate-100 text-xl">
                        📚
                    </div>

                    <p
                        class="mt-3 text-sm font-semibold text-slate-700">
                        No documents found
                    </p>

                    <p
                        class="mt-1 text-xs text-slate-400">
                        Create a knowledge document or change your filters.
                    </p>

                </div>

            <?php endif; ?>

        </div>

    </section>


    <!-- ================================================================
         DOCUMENT FORM
    ================================================================= -->

    <section
        class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">

        <div class="flex items-start justify-between gap-4">

            <div>

                <h2 class="font-bold text-slate-800">
                    <?= $edit
                        ? 'Edit document'
                        : 'Add document' ?>
                </h2>

                <p
                    class="mt-1 text-sm text-slate-500">
                    Content is automatically split into retrieval chunks.
                </p>

            </div>

            <?php if ($edit): ?>

                <a
                    href="knowledge-base.php"
                    class="text-sm font-semibold text-slate-500 hover:text-slate-700">
                    Cancel
                </a>

            <?php endif; ?>

        </div>


        <!-- Form -->
        <form
            method="post"
            action="knowledge-base.php<?= $edit
                                            ? '?edit=' . (int) $edit['id']
                                            : '' ?>"
            class="mt-6 space-y-4">

            <input
                type="hidden"
                name="csrf"
                value="<?= admin_e(
                            admin_csrf_token()
                        ) ?>">

            <input
                type="hidden"
                name="action"
                value="save">

            <input
                type="hidden"
                name="id"
                value="<?= (int) (
                            $edit['id'] ?? 0
                        ) ?>">


            <!-- Title -->
            <div>

                <label
                    class="mb-1.5 block text-sm font-semibold text-slate-700">
                    Title
                </label>

                <input
                    type="text"
                    name="title"
                    required
                    maxlength="255"
                    value="<?= admin_e(
                                (string) (
                                    $edit['title'] ?? ''
                                )
                            ) ?>"
                    placeholder="e.g. Transcript of Records Request"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/10">

            </div>


            <!-- Category / Department -->
            <div
                class="grid gap-4 sm:grid-cols-2">

                <!-- Category -->
                <div>

                    <label
                        class="mb-1.5 block text-sm font-semibold text-slate-700">
                        Category
                    </label>

                    <select
                        name="category_id"
                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-emerald-500">

                        <option value="">
                            General
                        </option>

                        <?php foreach ($categories as $category): ?>

                            <?php

                            $categorySelected =
                                (int) (
                                    $edit['category_id']
                                    ?? 0
                                ) ===
                                (int) $category['id'];

                            ?>

                            <option
                                value="<?= (int) $category['id'] ?>"
                                <?= $categorySelected
                                    ? 'selected'
                                    : '' ?>>
                                <?= admin_e(
                                    (string) $category['name']
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- Department -->
                <div>

                    <label
                        class="mb-1.5 block text-sm font-semibold text-slate-700">
                        Department
                    </label>

                    <select
                        name="department_id"
                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-emerald-500">

                        <option value="">
                            General / All
                        </option>

                        <?php foreach ($departments as $department): ?>

                            <?php

                            $departmentSelected =
                                (int) (
                                    $edit['department_id']
                                    ?? 0
                                ) ===
                                (int) $department['id'];

                            ?>

                            <option
                                value="<?= (int) $department['id'] ?>"
                                <?= $departmentSelected
                                    ? 'selected'
                                    : '' ?>>
                                <?= admin_e(
                                    (string) $department['name']
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

            </div>


            <!-- Source Type / Version -->
            <div
                class="grid gap-4 sm:grid-cols-2">

                <!-- Source Type -->
                <div>

                    <label
                        class="mb-1.5 block text-sm font-semibold text-slate-700">
                        Source type
                    </label>

                    <select
                        name="source_type"
                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-emerald-500">

                        <?php

                        $sourceTypes = [
                            'faq',
                            'policy',
                            'procedure',
                            'announcement',
                            'office_information',
                            'document',
                            'other',
                        ];

                        $selectedSource =
                            $edit['source_type']
                            ?? 'faq';

                        ?>

                        <?php foreach ($sourceTypes as $sourceType): ?>

                            <option
                                value="<?= admin_e(
                                            $sourceType
                                        ) ?>"
                                <?= $selectedSource === $sourceType
                                    ? 'selected'
                                    : '' ?>>
                                <?= admin_e(
                                    ucwords(
                                        str_replace(
                                            '_',
                                            ' ',
                                            $sourceType
                                        )
                                    )
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- Version -->
                <div>

                    <label
                        class="mb-1.5 block text-sm font-semibold text-slate-700">
                        Version
                    </label>

                    <input
                        type="text"
                        name="version"
                        maxlength="50"
                        value="<?= admin_e(
                                    (string) (
                                        $edit['version']
                                        ?? '1.0'
                                    )
                                ) ?>"
                        placeholder="1.0"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-emerald-500">

                </div>

            </div>


            <!-- Content -->
            <div>

                <label
                    class="mb-1.5 block text-sm font-semibold text-slate-700">
                    Content
                </label>

                <textarea
                    name="content"
                    required
                    rows="12"
                    placeholder="Enter the official information that UV-ASSIST should use when answering visitors..."
                    class="w-full resize-y rounded-xl border border-slate-200 px-3 py-2.5 text-sm leading-6 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/10"><?= admin_e(
                                                                                                                                                                                                            (string) (
                                                                                                                                                                                                                $edit['content'] ?? ''
                                                                                                                                                                                                            )
                                                                                                                                                                                                        ) ?></textarea>

                <p
                    class="mt-1.5 text-xs text-slate-400">
                    Use clear, official, up-to-date information. The system will split this content into retrieval chunks.
                </p>

            </div>


            <!-- Source Reference -->
            <div>

                <label
                    class="mb-1.5 block text-sm font-semibold text-slate-700">
                    Source reference
                </label>

                <input
                    type="text"
                    name="source_reference"
                    maxlength="500"
                    value="<?= admin_e(
                                (string) (
                                    $edit['source_reference']
                                    ?? ''
                                )
                            ) ?>"
                    placeholder="Office memo, URL, document reference..."
                    class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-emerald-500">

            </div>


            <!-- Dates -->
            <div
                class="grid gap-4 sm:grid-cols-2">

                <!-- Effective -->
                <div>

                    <label
                        class="mb-1.5 block text-sm font-semibold text-slate-700">
                        Effective date
                    </label>

                    <input
                        type="date"
                        name="effective_date"
                        value="<?= admin_e(
                                    (string) (
                                        $edit['effective_date']
                                        ?? ''
                                    )
                                ) ?>"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-emerald-500">

                </div>


                <!-- Expiration -->
                <div>

                    <label
                        class="mb-1.5 block text-sm font-semibold text-slate-700">
                        Expiration date
                    </label>

                    <input
                        type="date"
                        name="expires_at"
                        value="<?= admin_e(
                                    (string) (
                                        $edit['expires_at']
                                        ?? ''
                                    )
                                ) ?>"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-emerald-500">

                </div>

            </div>


            <!-- Status -->
            <div>

                <label
                    class="mb-1.5 block text-sm font-semibold text-slate-700">
                    Status
                </label>

                <select
                    name="status"
                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-emerald-500">

                    <?php

                    $selectedStatus =
                        $edit['status']
                        ?? 'draft';

                    ?>

                    <?php foreach ($validStatuses as $statusOption): ?>

                        <option
                            value="<?= admin_e(
                                        $statusOption
                                    ) ?>"
                            <?= $selectedStatus === $statusOption
                                ? 'selected'
                                : '' ?>>
                            <?= admin_e(
                                ucfirst(
                                    $statusOption
                                )
                            ) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Submit -->
            <button
                type="submit"
                class="w-full rounded-xl bg-[#123b2b] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[#0d2f23] focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                <?= $edit
                    ? 'Update document'
                    : 'Create document' ?>
            </button>

        </form>


        <!-- Delete -->
        <?php if ($edit): ?>

            <form
                method="post"
                action="knowledge-base.php"
                class="mt-3"
                onsubmit="return confirm('Delete this knowledge document? This action cannot be undone.')">

                <input
                    type="hidden"
                    name="csrf"
                    value="<?= admin_e(
                                admin_csrf_token()
                            ) ?>">

                <input
                    type="hidden"
                    name="action"
                    value="delete">

                <input
                    type="hidden"
                    name="id"
                    value="<?= (int) $edit['id'] ?>">

                <button
                    type="submit"
                    class="w-full rounded-xl border border-red-200 px-4 py-3 text-sm font-semibold text-red-700 transition hover:bg-red-50">
                    Delete document
                </button>

            </form>

        <?php endif; ?>

    </section>

</div>


<script>
    document.addEventListener('DOMContentLoaded', function() {

        const content =
            document.querySelector('textarea[name="content"]');

        if (content) {

            content.addEventListener('keydown', function(event) {

                if (
                    (event.ctrlKey || event.metaKey) &&
                    event.key === 'Enter'
                ) {

                    event.preventDefault();

                    const form = content.closest('form');

                    if (form) {
                        form.requestSubmit();
                    }
                }

            });

        }

    });
</script>


<?php

require __DIR__ . '/partials/footer.php';

?>