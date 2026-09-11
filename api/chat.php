<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| UV-ASSIST Chat API
|--------------------------------------------------------------------------
| Database-grounded campus helpdesk chatbot.
|
| Flow:
|
| Visitor
|    ↓
| Intent Detection
|    ↓
| Knowledge Base Retrieval
|    ↓
| Relevance / Confidence
|    ↓
| >= 0.70 → Answer from database
| < 0.70  → Human escalation
|
| IMPORTANT:
| UV-ASSIST DOES NOT INVENT ANSWERS.
|
| The following tables are the source of truth:
|
| departments
| knowledge_categories
| knowledge_documents
| knowledge_chunks
|
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../config.php';

date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| CONFIGURATION
|--------------------------------------------------------------------------
*/

const CONFIDENCE_THRESHOLD = 0.70;
const MAX_MESSAGE_LENGTH = 2000;
const MAX_RETRIEVAL_RESULTS = 5;
const MAX_KNOWLEDGE_ROWS = 500;


/*
|--------------------------------------------------------------------------
| JSON RESPONSE
|--------------------------------------------------------------------------
*/

function jsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| UUID
|--------------------------------------------------------------------------
*/

function generateUuid(): string
{
    $data = random_bytes(16);

    $data[6] = chr(
        (ord($data[6]) & 0x0f) | 0x40
    );

    $data[8] = chr(
        (ord($data[8]) & 0x3f) | 0x80
    );

    return vsprintf(
        '%s%s-%s-%s-%s-%s%s%s',
        str_split(bin2hex($data), 4)
    );
}


/*
|--------------------------------------------------------------------------
| TEXT HELPERS
|--------------------------------------------------------------------------
*/

function lowerText(string $text): string
{
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($text, 'UTF-8');
    }

    return strtolower($text);
}


function textLength(string $text): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($text, 'UTF-8');
    }

    return strlen($text);
}


function textSubstring(
    string $text,
    int $start,
    int $length
): string {
    if (function_exists('mb_substr')) {
        return mb_substr(
            $text,
            $start,
            $length,
            'UTF-8'
        );
    }

    return substr(
        $text,
        $start,
        $length
    );
}


/*
|--------------------------------------------------------------------------
| NORMALIZE TEXT
|--------------------------------------------------------------------------
*/

function normalizeText(string $text): string
{
    $text = lowerText($text);

    $text = preg_replace(
        '/[^\p{L}\p{N}\s]/u',
        ' ',
        $text
    ) ?? $text;

    $text = preg_replace(
        '/\s+/u',
        ' ',
        $text
    ) ?? $text;

    return trim($text);
}


/*
|--------------------------------------------------------------------------
| TOKENIZE
|--------------------------------------------------------------------------
*/

function tokenize(string $text): array
{
    $text = normalizeText($text);

    if ($text === '') {
        return [];
    }

    $words = preg_split(
        '/\s+/u',
        $text,
        -1,
        PREG_SPLIT_NO_EMPTY
    );

    if (!is_array($words)) {
        return [];
    }

    /*
    |--------------------------------------------------------------------------
    | Stop words
    |--------------------------------------------------------------------------
    */

    $stopWords = [

        // English

        'a',
        'an',
        'and',
        'are',
        'as',
        'at',
        'be',
        'can',
        'could',
        'do',
        'does',
        'did',
        'for',
        'from',
        'how',
        'i',
        'if',
        'in',
        'is',
        'it',
        'me',
        'my',
        'of',
        'on',
        'or',
        'please',
        'the',
        'to',
        'what',
        'when',
        'where',
        'which',
        'who',
        'why',
        'with',
        'would',
        'you',
        'your',

        // Filipino / Cebuano

        'ang',
        'at',
        'ay',
        'ba',
        'dili',
        'di',
        'ako',
        'ko',
        'mo',
        'ni',
        'na',
        'ng',
        'mga',
        'sa',
        'ug',
        'og',
        'kung',
        'unsa',
        'asa',
        'kanus',
        'kanus-a',
        'pwede',
        'pila',
    ];

    $filtered = [];

    foreach ($words as $word) {

        if (textLength($word) < 2) {
            continue;
        }

        if (in_array($word, $stopWords, true)) {
            continue;
        }

        $filtered[] = $word;
    }

    return array_values(
        array_unique($filtered)
    );
}


/*
|--------------------------------------------------------------------------
| DATABASE HELPERS
|--------------------------------------------------------------------------
*/

function getDepartmentIdByCode(
    PDO $pdo,
    string $code
): ?int {
    $stmt = $pdo->prepare("
        SELECT id
        FROM departments
        WHERE code = :code
        AND status = 'active'
        LIMIT 1
    ");

    $stmt->execute([
        'code' => $code
    ]);

    $id = $stmt->fetchColumn();

    if ($id === false) {
        return null;
    }

    return (int) $id;
}


function getDepartmentName(
    PDO $pdo,
    ?int $departmentId
): ?string {
    if ($departmentId === null) {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT name
        FROM departments
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        'id' => $departmentId
    ]);

    $name = $stmt->fetchColumn();

    if ($name === false) {
        return null;
    }

    return (string) $name;
}


/*
|--------------------------------------------------------------------------
| INTENT DETECTION
|--------------------------------------------------------------------------
*/

function detectIntent(
    PDO $pdo,
    string $message
): array {

    $text = normalizeText($message);

    $groups = [

        'registrar' => [
            'transcript',
            'transcripts',
            'transcript of records',
            'tor',
            'school records',
            'academic record',
            'records',
            'diploma',
            'certificate',
            'certification',
            'registrar',
            'document request',
            'request document',
            'good moral',
            'honorable dismissal',
        ],

        'admissions' => [
            'admission',
            'admissions',
            'apply',
            'application',
            'applicant',
            'applying',
            'entrance',
            'entrance exam',
            'entrance examination',
            'requirements',
            'admission requirements',
            'new student',
            'freshman',
            'first year',
            'transfer',
            'transferee',
            'form 138',
            'birth certificate',
        ],

        'student_affairs' => [
            'guidance',
            'counseling',
            'counselling',
            'counselor',
            'counsellor',
            'student affairs',
            'student organization',
            'organization',
            'student activity',
            'student activities',
            'scholarship',
            'scholarships',
            'financial assistance',
            'student welfare',
        ],

        'it_support' => [
            'it support',
            'technical support',
            'technical',
            'wifi',
            'wi fi',
            'internet',
            'password',
            'forgot password',
            'reset password',
            'login',
            'log in',
            'account',
            'email account',
            'student portal',
            'portal',
            'computer',
            'network',
            'system error',
            'website error',
            'cannot access',
            'cant access',
            'unable to access',
        ],

        'enrollment' => [
            'enroll',
            'enrollment',
            'enrol',
            'enrolment',
            'registration',
            'register',
            'subjects',
            'subject',
            'load',
            'class schedule',
            'schedule',
            'add subject',
            'drop subject',
            'academic registration',
        ],

        'finance' => [
            'tuition',
            'tuition fee',
            'fees',
            'fee',
            'payment',
            'payments',
            'cashier',
            'assessment',
            'school fees',
            'balance',
            'pay',
            'payment deadline',
            'scholarship',
            'scholarships',
        ],

        'general' => [
            'hello',
            'hi',
            'hey',
            'help',
            'uv assist',
            'university',
        ],
    ];

    $scores = [];

    foreach ($groups as $intent => $keywords) {

        $score = 0;

        foreach ($keywords as $keyword) {

            $keyword = normalizeText($keyword);

            if ($keyword === '') {
                continue;
            }

            if (strpos($text, $keyword) !== false) {

                if (str_contains($keyword, ' ')) {
                    $score += 2;
                } else {
                    $score++;
                }
            }
        }

        $scores[$intent] = $score;
    }

    arsort($scores);

    $intent = array_key_first($scores);

    if (
        $intent === null ||
        $scores[$intent] <= 0
    ) {
        return [
            'intent' => 'general_inquiry',
            'department_id' => null,
            'intent_score' => 0,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Department mapping
    |--------------------------------------------------------------------------
    */

    $departmentCodes = [

        'registrar' => 'REG',

        'admissions' => 'ADM',

        'student_affairs' => 'SAG',

        'it_support' => 'IT',

        'enrollment' => 'REG',

        'finance' => null,

        'general' => null,
    ];

    $departmentId = null;

    if (
        isset($departmentCodes[$intent]) &&
        $departmentCodes[$intent] !== null
    ) {
        $departmentId = getDepartmentIdByCode(
            $pdo,
            $departmentCodes[$intent]
        );
    }

    return [
        'intent' => $intent,
        'department_id' => $departmentId,
        'intent_score' => $scores[$intent],
    ];
}


/*
|--------------------------------------------------------------------------
| TOKEN OVERLAP SCORE
|--------------------------------------------------------------------------
*/

function calculateTokenScore(
    string $query,
    string $title,
    string $content
): float {

    $queryTokens = tokenize($query);

    if (count($queryTokens) === 0) {
        return 0.0;
    }

    $titleTokens = tokenize($title);

    $contentTokens = tokenize($content);

    $titleSet = array_flip($titleTokens);

    $contentSet = array_flip($contentTokens);

    $matched = 0;

    $titleMatches = 0;

    foreach ($queryTokens as $token) {

        if (isset($titleSet[$token])) {

            $matched++;

            $titleMatches++;

            continue;
        }

        if (isset($contentSet[$token])) {
            $matched++;
        }
    }

    if ($matched === 0) {
        return 0.0;
    }

    /*
    |--------------------------------------------------------------------------
    | Base relevance
    |--------------------------------------------------------------------------
    */

    $baseScore =
        $matched / count($queryTokens);

    /*
    |--------------------------------------------------------------------------
    | Title bonus
    |--------------------------------------------------------------------------
    */

    $titleBonus = 0.0;

    if ($titleMatches > 0) {

        $titleBonus = min(
            0.25,
            ($titleMatches / count($queryTokens)) * 0.25
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Exact phrase bonus
    |--------------------------------------------------------------------------
    */

    $normalizedQuery = normalizeText($query);

    $normalizedTitle = normalizeText($title);

    $normalizedContent = normalizeText($content);

    $phraseBonus = 0.0;

    if (
        $normalizedQuery !== '' &&
        (
            strpos(
                $normalizedTitle,
                $normalizedQuery
            ) !== false
            ||
            strpos(
                $normalizedContent,
                $normalizedQuery
            ) !== false
        )
    ) {
        $phraseBonus = 0.20;
    }

    /*
    |--------------------------------------------------------------------------
    | Final score
    |--------------------------------------------------------------------------
    */

    $score =
        ($baseScore * 0.65)
        + $titleBonus
        + $phraseBonus;

    return round(
        min(0.99, $score),
        4
    );
}


/*
|--------------------------------------------------------------------------
| SEARCH KNOWLEDGE BASE
|--------------------------------------------------------------------------
|
| Source:
|
| knowledge_documents
|        ↓
| knowledge_chunks
|
| Only published and currently valid documents are considered.
|
|--------------------------------------------------------------------------
*/

function searchKnowledgeBase(
    PDO $pdo,
    string $query,
    ?int $departmentId = null
): array {

    /*
    |--------------------------------------------------------------------------
    | Get valid published knowledge
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            kc.id AS chunk_id,
            kc.document_id,
            kc.chunk_index,
            kc.content AS chunk_content,

            kd.title,
            kd.content AS document_content,
            kd.department_id,
            kd.source_type,
            kd.source_reference,
            kd.version,
            kd.effective_date,
            kd.expires_at,
            kd.updated_at

        FROM knowledge_chunks kc

        INNER JOIN knowledge_documents kd
            ON kd.id = kc.document_id

        WHERE kd.status = 'published'

        AND (
            kd.effective_date IS NULL
            OR kd.effective_date <= CURDATE()
        )

        AND (
            kd.expires_at IS NULL
            OR kd.expires_at >= CURDATE()
        )

        ORDER BY
            kd.updated_at DESC,
            kc.chunk_index ASC

        LIMIT " . MAX_KNOWLEDGE_ROWS;

    $stmt = $pdo->prepare($sql);

    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!is_array($rows)) {
        return [];
    }

    $results = [];

    foreach ($rows as $row) {

        $title = (string) (
            $row['title'] ?? ''
        );

        $chunkContent = (string) (
            $row['chunk_content'] ?? ''
        );

        $documentContent = (string) (
            $row['document_content'] ?? ''
        );

        /*
        |--------------------------------------------------------------------------
        | Score chunk
        |--------------------------------------------------------------------------
        */

        $score = calculateTokenScore(
            $query,
            $title,
            $chunkContent
        );

        /*
        |--------------------------------------------------------------------------
        | If chunk does not match,
        | check entire document
        |--------------------------------------------------------------------------
        */

        if ($score <= 0) {

            $documentScore = calculateTokenScore(
                $query,
                $title,
                $documentContent
            );

            if ($documentScore > 0) {

                $score =
                    $documentScore * 0.90;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Department bonus
        |--------------------------------------------------------------------------
        */

        $rowDepartmentId =
            $row['department_id'] !== null
                ? (int) $row['department_id']
                : null;

        if (
            $departmentId !== null &&
            $rowDepartmentId !== null &&
            $rowDepartmentId === $departmentId &&
            $score > 0
        ) {
            $score += 0.10;
        }

        $score = min(
            0.99,
            round($score, 4)
        );

        if ($score <= 0) {
            continue;
        }

        $row['similarity_score'] = $score;

        $results[] = $row;
    }

    /*
    |--------------------------------------------------------------------------
    | Sort highest relevance first
    |--------------------------------------------------------------------------
    */

    usort(
        $results,
        static function (
            array $a,
            array $b
        ): int {

            return
                (float) $b['similarity_score']
                <=>
                (float) $a['similarity_score'];
        }
    );

    return array_slice(
        $results,
        0,
        MAX_RETRIEVAL_RESULTS
    );
}


/*
|--------------------------------------------------------------------------
| BUILD DATABASE-GROUNDED ANSWER
|--------------------------------------------------------------------------
*/

function buildGroundedResponse(
    array $bestResult
): string {

    $content = trim(
        (string) (
            $bestResult['chunk_content'] ?? ''
        )
    );

    $title = trim(
        (string) (
            $bestResult['title']
            ?? 'University Knowledge Base'
        )
    );

    if ($content === '') {

        return
            'I found a relevant University of the Visayas information source, ' .
            'but the source does not contain enough information to provide ' .
            'a complete answer.';
    }

    /*
    |--------------------------------------------------------------------------
    | DATABASE CONTENT IS THE ANSWER
    |--------------------------------------------------------------------------
    |
    | No external AI generation occurs here.
    |
    */

    return
        $content .
        "\n\n" .
        "Source: " .
        $title;
}


/*
|--------------------------------------------------------------------------
| ANALYTICS
|--------------------------------------------------------------------------
|
| Analytics should never prevent UV-ASSIST from answering a question.
|
|--------------------------------------------------------------------------
*/

function saveAnalyticsEvent(
    PDO $pdo,
    ?int $conversationId,
    string $eventType,
    array $metadata = []
): void {

    try {

        $metadataJson = json_encode(
            $metadata,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

        if ($metadataJson === false) {
            $metadataJson = '{}';
        }

        $stmt = $pdo->prepare("
            INSERT INTO analytics_events
            (
                conversation_id,
                event_type,
                metadata
            )
            VALUES
            (
                :conversation_id,
                :event_type,
                :metadata
            )
        ");

        $stmt->execute([
            'conversation_id' => $conversationId,
            'event_type' => $eventType,
            'metadata' => $metadataJson,
        ]);

    } catch (Throwable $e) {

        /*
        |--------------------------------------------------------------------------
        | Analytics must NOT break the chatbot.
        |--------------------------------------------------------------------------
        */

        error_log(
            "UV-ASSIST ANALYTICS ERROR\n" .
            "Message: " . $e->getMessage() . "\n" .
            "File: " . $e->getFile() . "\n" .
            "Line: " . $e->getLine()
        );
    }
}


/*
|--------------------------------------------------------------------------
| MAIN REQUEST
|--------------------------------------------------------------------------
*/

try {

    /*
    |--------------------------------------------------------------------------
    | REQUEST METHOD
    |--------------------------------------------------------------------------
    */

    $requestMethod =
        strtoupper(
            (string) (
                $_SERVER['REQUEST_METHOD'] ?? 'GET'
            )
        );

    if ($requestMethod !== 'POST') {

        jsonResponse([
            'success' => false,
            'message' =>
                'Only POST requests are allowed.'
        ], 405);
    }


    /*
    |--------------------------------------------------------------------------
    | PDO
    |--------------------------------------------------------------------------
    */

    if (
        !isset($pdo) ||
        !($pdo instanceof PDO)
    ) {

        throw new RuntimeException(
            'PDO connection is not available. Check config.php.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | READ JSON REQUEST
    |--------------------------------------------------------------------------
    */

    $rawInput = file_get_contents(
        'php://input'
    );

    if (
        $rawInput === false ||
        trim($rawInput) === ''
    ) {

        jsonResponse([
            'success' => false,
            'message' =>
                'Request body is empty.'
        ], 400);
    }

    $input = json_decode(
        $rawInput,
        true
    );

    if (
        !is_array($input) ||
        json_last_error() !== JSON_ERROR_NONE
    ) {

        jsonResponse([
            'success' => false,
            'message' =>
                'Invalid JSON request.'
        ], 400);
    }


    /*
    |--------------------------------------------------------------------------
    | MESSAGE
    |--------------------------------------------------------------------------
    */

    $message = trim(
        (string) (
            $input['message'] ?? ''
        )
    );

    if ($message === '') {

        jsonResponse([
            'success' => false,
            'message' =>
                'Please enter a message.'
        ], 422);
    }

    if (
        textLength($message)
        > MAX_MESSAGE_LENGTH
    ) {

        jsonResponse([
            'success' => false,
            'message' =>
                'Your message is too long. ' .
                'Please limit your message to ' .
                MAX_MESSAGE_LENGTH .
                ' characters.'
        ], 422);
    }


    /*
    |--------------------------------------------------------------------------
    | VISITOR UUID
    |--------------------------------------------------------------------------
    */

    $visitorUuid = trim(
        (string) (
            $_SESSION['uv_assist_visitor_uuid']
            ?? ($input['visitor_uuid'] ?? '')
        )
    );

    if (
        $visitorUuid === '' ||
        !preg_match(
            '/^[a-f0-9-]{36}$/i',
            $visitorUuid
        )
    ) {

        $visitorUuid = generateUuid();

        $_SESSION['uv_assist_visitor_uuid'] =
            $visitorUuid;
    }


    /*
    |--------------------------------------------------------------------------
    | VISITOR INFORMATION
    |--------------------------------------------------------------------------
    */

    $sessionId = session_id();

    $visitorName = trim(
        (string) (
            $input['name'] ?? ''
        )
    );

    $visitorEmail = trim(
        (string) (
            $input['email'] ?? ''
        )
    );

    $userType = trim(
        (string) (
            $input['user_type']
            ?? 'unknown'
        )
    );

    $allowedUserTypes = [
        'student',
        'faculty',
        'staff',
        'visitor',
        'unknown',
    ];

    if (
        !in_array(
            $userType,
            $allowedUserTypes,
            true
        )
    ) {
        $userType = 'unknown';
    }

    $currentPage = trim(
        (string) (
            $input['current_page']
            ?? ($_SERVER['HTTP_REFERER'] ?? '')
        )
    );

    $ipAddress =
        $_SERVER['REMOTE_ADDR']
        ?? null;

    $userAgent =
        $_SERVER['HTTP_USER_AGENT']
        ?? null;


    /*
    |--------------------------------------------------------------------------
    | FIND VISITOR
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT id
        FROM chat_visitors
        WHERE visitor_uuid = :visitor_uuid
        LIMIT 1
    ");

    $stmt->execute([
        'visitor_uuid' => $visitorUuid
    ]);

    $visitorId =
        $stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | CREATE VISITOR
    |--------------------------------------------------------------------------
    */

    if ($visitorId === false) {

        $stmt = $pdo->prepare("
            INSERT INTO chat_visitors
            (
                visitor_uuid,
                name,
                email,
                user_type,
                session_id,
                ip_address,
                user_agent,
                current_page,
                last_seen_at
            )
            VALUES
            (
                :visitor_uuid,
                :name,
                :email,
                :user_type,
                :session_id,
                :ip_address,
                :user_agent,
                :current_page,
                NOW()
            )
        ");

        $stmt->execute([
            'visitor_uuid' => $visitorUuid,

            'name' =>
                $visitorName !== ''
                    ? $visitorName
                    : null,

            'email' =>
                $visitorEmail !== ''
                    ? $visitorEmail
                    : null,

            'user_type' => $userType,

            'session_id' => $sessionId,

            'ip_address' => $ipAddress,

            'user_agent' => $userAgent,

            'current_page' =>
                $currentPage !== ''
                    ? $currentPage
                    : null,
        ]);

        $visitorId =
            (int) $pdo->lastInsertId();

    } else {

        $visitorId =
            (int) $visitorId;

        /*
        |--------------------------------------------------------------------------
        | UPDATE VISITOR
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            UPDATE chat_visitors
            SET
                name =
                    CASE
                        WHEN :name_check <> ''
                        THEN :name_value
                        ELSE name
                    END,

                email =
                    CASE
                        WHEN :email_check <> ''
                        THEN :email_value
                        ELSE email
                    END,

                user_type = :user_type,

                session_id = :session_id,

                ip_address = :ip_address,

                user_agent = :user_agent,

                current_page = :current_page,

                last_seen_at = NOW()

            WHERE id = :id
        ");

        $stmt->execute([
            'name_check' =>
                $visitorName,

            'name_value' =>
                $visitorName,

            'email_check' =>
                $visitorEmail,

            'email_value' =>
                $visitorEmail,

            'user_type' =>
                $userType,

            'session_id' =>
                $sessionId,

            'ip_address' =>
                $ipAddress,

            'user_agent' =>
                $userAgent,

            'current_page' =>
                $currentPage !== ''
                    ? $currentPage
                    : null,

            'id' =>
                $visitorId,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | DETECT INTENT
    |--------------------------------------------------------------------------
    */

    $intentData =
        detectIntent(
            $pdo,
            $message
        );

    $intent =
        (string) (
            $intentData['intent']
            ?? 'general_inquiry'
        );

    $detectedDepartmentId =
        $intentData['department_id'] !== null
            ? (int) $intentData['department_id']
            : null;


    /*
    |--------------------------------------------------------------------------
    | FIND ACTIVE CONVERSATION
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            id,
            conversation_uuid,
            department_id,
            assigned_staff_id,
            status

        FROM conversations

        WHERE visitor_id = :visitor_id

        AND status IN
        (
            'ai_active',
            'escalated',
            'waiting_for_staff',
            'staff_active',
            'reopened'
        )

        ORDER BY
            last_message_at DESC,
            id DESC

        LIMIT 1
    ");

    $stmt->execute([
        'visitor_id' => $visitorId
    ]);

    $conversation =
        $stmt->fetch(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | CREATE CONVERSATION
    |--------------------------------------------------------------------------
    */

    if (!$conversation) {

        $conversationUuid =
            generateUuid();

        $departmentId =
            $detectedDepartmentId;

        $subject =
            textSubstring(
                $message,
                0,
                255
            );

        $stmt = $pdo->prepare("
            INSERT INTO conversations
            (
                conversation_uuid,
                visitor_id,
                department_id,
                assigned_staff_id,
                status,
                priority,
                source,
                subject,
                last_message_at
            )
            VALUES
            (
                :conversation_uuid,
                :visitor_id,
                :department_id,
                NULL,
                'ai_active',
                'normal',
                'web_page',
                :subject,
                NOW()
            )
        ");

        $stmt->execute([
            'conversation_uuid' =>
                $conversationUuid,

            'visitor_id' =>
                $visitorId,

            'department_id' =>
                $departmentId,

            'subject' =>
                $subject,
        ]);

        $conversationId =
            (int) $pdo->lastInsertId();

        $conversationStatus =
            'ai_active';

        $conversation = [
            'id' =>
                $conversationId,

            'conversation_uuid' =>
                $conversationUuid,

            'department_id' =>
                $departmentId,

            'assigned_staff_id' =>
                null,

            'status' =>
                $conversationStatus,
        ];

        saveAnalyticsEvent(
            $pdo,
            $conversationId,
            'conversation_started',
            [
                'visitor_id' =>
                    $visitorId,

                'intent' =>
                    $intent,
            ]
        );

    } else {

        $conversationId =
            (int) $conversation['id'];

        $conversationUuid =
            (string) (
                $conversation['conversation_uuid']
                ?? ''
            );

        $conversationStatus =
            (string) (
                $conversation['status']
                ?? 'ai_active'
            );

        /*
        |--------------------------------------------------------------------------
        | Add department when missing
        |--------------------------------------------------------------------------
        */

        if (
            $conversation['department_id'] === null &&
            $detectedDepartmentId !== null
        ) {

            $stmt = $pdo->prepare("
                UPDATE conversations
                SET department_id = :department_id
                WHERE id = :id
            ");

            $stmt->execute([
                'department_id' =>
                    $detectedDepartmentId,

                'id' =>
                    $conversationId,
            ]);

            $conversation['department_id'] =
                $detectedDepartmentId;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | SAVE USER MESSAGE
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
            intent,
            confidence_score,
            is_ai_generated,
            is_read
        )
        VALUES
        (
            :conversation_id,
            'user',
            NULL,
            :message,
            'text',
            :intent,
            NULL,
            0,
            0
        )
    ");

    $stmt->execute([
        'conversation_id' =>
            $conversationId,

        'message' =>
            $message,

        'intent' =>
            $intent,
    ]);

    $userMessageId =
        (int) $pdo->lastInsertId();


    /*
    |--------------------------------------------------------------------------
    | ANALYTICS: MESSAGE
    |--------------------------------------------------------------------------
    */

    saveAnalyticsEvent(
        $pdo,
        $conversationId,
        'message_sent',
        [
            'message_id' =>
                $userMessageId,

            'intent' =>
                $intent,
        ]
    );


    /*
    |--------------------------------------------------------------------------
    | EXISTING STAFF CONVERSATION
    |--------------------------------------------------------------------------
    */

    if (
        in_array(
            $conversationStatus,
            [
                'escalated',
                'waiting_for_staff',
                'staff_active',
            ],
            true
        )
    ) {

        $departmentForResponse =
            $conversation['department_id'] !== null
                ? (int) $conversation['department_id']
                : $detectedDepartmentId;

        $departmentName =
            getDepartmentName(
                $pdo,
                $departmentForResponse
            );

        if ($departmentName) {

            $staffMessage =
                "Thank you for your message. " .
                "Your conversation has already been forwarded to the " .
                $departmentName .
                " for assistance. " .
                "A staff member will respond as soon as possible.";

        } else {

            $staffMessage =
                "Thank you for your message. " .
                "Your conversation has already been forwarded to the appropriate " .
                "University support office for assistance. " .
                "A staff member will respond as soon as possible.";
        }


        /*
        |--------------------------------------------------------------------------
        | SAVE STAFF WAITING MESSAGE
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
                intent,
                confidence_score,
                is_ai_generated,
                is_read
            )
            VALUES
            (
                :conversation_id,
                'ai',
                NULL,
                :message,
                'system',
                :intent,
                :confidence,
                1,
                1
            )
        ");

        $stmt->execute([
            'conversation_id' =>
                $conversationId,

            'message' =>
                $staffMessage,

            'intent' =>
                $intent,

            'confidence' =>
                0.0,
        ]);

        $aiMessageId =
            (int) $pdo->lastInsertId();


        /*
        |--------------------------------------------------------------------------
        | UPDATE CONVERSATION
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            UPDATE conversations
            SET last_message_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' =>
                $conversationId
        ]);


        /*
        |--------------------------------------------------------------------------
        | RESPONSE
        |--------------------------------------------------------------------------
        */

        jsonResponse([
            'success' =>
                true,

            'conversation_id' =>
                $conversationId,

            'conversation_uuid' =>
                $conversationUuid,

            'message_id' =>
                $aiMessageId,

            'sender_type' =>
                'ai',

            'message' =>
                $staffMessage,

            'confidence' =>
                0,

            'threshold' =>
                CONFIDENCE_THRESHOLD,

            'intent' =>
                $intent,

            'department_id' =>
                $departmentForResponse,

            'escalated' =>
                true,

            'waiting_for_staff' =>
                true,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | RETRIEVE FROM DATABASE
    |--------------------------------------------------------------------------
    */

    $knowledgeResults =
        searchKnowledgeBase(
            $pdo,
            $message,
            $detectedDepartmentId
        );


    /*
    |--------------------------------------------------------------------------
    | CONFIDENCE
    |--------------------------------------------------------------------------
    */

    $confidence = 0.0;

    if (!empty($knowledgeResults)) {

        $confidence =
            (float) (
                $knowledgeResults[0]['similarity_score']
                ?? 0
            );
    }


    /*
    |--------------------------------------------------------------------------
    | ANALYTICS: KNOWLEDGE RETRIEVED
    |--------------------------------------------------------------------------
    */

    saveAnalyticsEvent(
        $pdo,
        $conversationId,
        'knowledge_retrieved',
        [
            'message_id' =>
                $userMessageId,

            'result_count' =>
                count($knowledgeResults),

            'top_score' =>
                $confidence,

            'intent' =>
                $intent,
        ]
    );


    /*
    |--------------------------------------------------------------------------
    | HIGH CONFIDENCE
    |--------------------------------------------------------------------------
    |
    | Answer directly from the database.
    |
    |--------------------------------------------------------------------------
    */

    if (
        $confidence >= CONFIDENCE_THRESHOLD &&
        !empty($knowledgeResults)
    ) {

        $bestResult =
            $knowledgeResults[0];


        /*
        |--------------------------------------------------------------------------
        | DATABASE-GROUNDED ANSWER
        |--------------------------------------------------------------------------
        */

        $aiResponse =
            buildGroundedResponse(
                $bestResult
            );


        /*
        |--------------------------------------------------------------------------
        | SAVE AI MESSAGE
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
                intent,
                confidence_score,
                is_ai_generated,
                is_read
            )
            VALUES
            (
                :conversation_id,
                'ai',
                NULL,
                :message,
                'text',
                :intent,
                :confidence,
                1,
                1
            )
        ");

        $stmt->execute([
            'conversation_id' =>
                $conversationId,

            'message' =>
                $aiResponse,

            'intent' =>
                $intent,

            'confidence' =>
                $confidence,
        ]);

        $aiMessageId =
            (int) $pdo->lastInsertId();


        /*
        |--------------------------------------------------------------------------
        | SAVE RETRIEVAL RESULTS
        |--------------------------------------------------------------------------
        */

        $rank = 1;

        foreach (
            $knowledgeResults
            as $result
        ) {

            try {

                $stmt = $pdo->prepare("
                    INSERT INTO retrieval_results
                    (
                        message_id,
                        document_id,
                        chunk_id,
                        similarity_score,
                        result_rank
                    )
                    VALUES
                    (
                        :message_id,
                        :document_id,
                        :chunk_id,
                        :similarity_score,
                        :result_rank
                    )
                ");

                $stmt->execute([
                    'message_id' =>
                        $userMessageId,

                    'document_id' =>
                        (int) (
                            $result['document_id']
                            ?? 0
                        ),

                    'chunk_id' =>
                        (int) (
                            $result['chunk_id']
                            ?? 0
                        ),

                    'similarity_score' =>
                        (float) (
                            $result['similarity_score']
                            ?? 0
                        ),

                    'result_rank' =>
                        $rank,
                ]);

            } catch (Throwable $e) {

                /*
                |--------------------------------------------------------------------------
                | Retrieval logging must not prevent the answer.
                |--------------------------------------------------------------------------
                */

                error_log(
                    "UV-ASSIST RETRIEVAL RESULT ERROR\n" .
                    "Message: " . $e->getMessage()
                );
            }

            $rank++;
        }


        /*
        |--------------------------------------------------------------------------
        | UPDATE CONVERSATION
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            UPDATE conversations
            SET
                status = 'ai_active',
                last_message_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' =>
                $conversationId
        ]);


        /*
        |--------------------------------------------------------------------------
        | ANALYTICS: AI RESPONSE
        |--------------------------------------------------------------------------
        */

        saveAnalyticsEvent(
            $pdo,
            $conversationId,
            'ai_response',
            [
                'message_id' =>
                    $aiMessageId,

                'user_message_id' =>
                    $userMessageId,

                'confidence' =>
                    $confidence,

                'threshold' =>
                    CONFIDENCE_THRESHOLD,
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | SOURCE INFORMATION
        |--------------------------------------------------------------------------
        */

        $source = [

            'document_id' =>
                (int) (
                    $bestResult['document_id']
                    ?? 0
                ),

            'chunk_id' =>
                (int) (
                    $bestResult['chunk_id']
                    ?? 0
                ),

            'title' =>
                (string) (
                    $bestResult['title']
                    ?? ''
                ),

            'source_type' =>
                (string) (
                    $bestResult['source_type']
                    ?? ''
                ),

            'source_reference' =>
                $bestResult['source_reference']
                ?? null,

            'version' =>
                $bestResult['version']
                ?? null,

            'similarity_score' =>
                $confidence,
        ];


        /*
        |--------------------------------------------------------------------------
        | RETURN DATABASE ANSWER
        |--------------------------------------------------------------------------
        */

        jsonResponse([
            'success' =>
                true,

            'conversation_id' =>
                $conversationId,

            'conversation_uuid' =>
                $conversationUuid,

            'message_id' =>
                $aiMessageId,

            'sender_type' =>
                'ai',

            'message' =>
                $aiResponse,

            'confidence' =>
                $confidence,

            'threshold' =>
                CONFIDENCE_THRESHOLD,

            'intent' =>
                $intent,

            'department_id' =>
                $detectedDepartmentId,

            'escalated' =>
                false,

            'waiting_for_staff' =>
                false,

            'source' =>
                $source,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | LOW CONFIDENCE / NO DATABASE ANSWER
    |--------------------------------------------------------------------------
    */

    $departmentId =
        $detectedDepartmentId;

    if (
        $departmentId === null &&
        isset($conversation['department_id']) &&
        $conversation['department_id'] !== null
    ) {

        $departmentId =
            (int) $conversation['department_id'];
    }


    /*
    |--------------------------------------------------------------------------
    | ESCALATION REASON
    |--------------------------------------------------------------------------
    */

    $escalationReason =
        $confidence > 0
            ? 'low_confidence'
            : 'outside_knowledge_base';


    /*
    |--------------------------------------------------------------------------
    | CREATE ESCALATION
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        INSERT INTO escalations
        (
            conversation_id,
            department_id,
            assigned_staff_id,
            reason,
            confidence_score,
            status,
            notes
        )
        VALUES
        (
            :conversation_id,
            :department_id,
            NULL,
            :reason,
            :confidence_score,
            'pending',
            :notes
        )
    ");

    $stmt->execute([
        'conversation_id' =>
            $conversationId,

        'department_id' =>
            $departmentId,

        'reason' =>
            $escalationReason,

        'confidence_score' =>
            $confidence,

        'notes' =>
            'Automatically escalated because the retrieved knowledge-base confidence score was below ' .
            CONFIDENCE_THRESHOLD .
            '.',
    ]);

    $escalationId =
        (int) $pdo->lastInsertId();


    /*
    |--------------------------------------------------------------------------
    | UPDATE CONVERSATION
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        UPDATE conversations
        SET
            department_id =
                COALESCE(
                    :department_id,
                    department_id
                ),

            status =
                'waiting_for_staff',

            last_message_at =
                NOW()

        WHERE id = :id
    ");

    $stmt->execute([
        'department_id' =>
            $departmentId,

        'id' =>
            $conversationId,
    ]);


    /*
    |--------------------------------------------------------------------------
    | BUILD ESCALATION MESSAGE
    |--------------------------------------------------------------------------
    */

    $departmentName =
        getDepartmentName(
            $pdo,
            $departmentId
        );

    if ($departmentName) {

        $aiResponse =
            "I'm not confident that I have enough verified information " .
            "in the UV-ASSIST knowledge base to answer that accurately.\n\n" .

            "I've forwarded your question to the " .
            $departmentName .
            " for assistance. " .

            "A University staff member will respond to your conversation.";

    } else {

        $aiResponse =
            "I'm not confident that I have enough verified information " .
            "in the UV-ASSIST knowledge base to answer that accurately.\n\n" .

            "I've forwarded your question to a University support staff member " .
            "for assistance. Someone will respond to your conversation.";
    }


    /*
    |--------------------------------------------------------------------------
    | SAVE ESCALATION MESSAGE
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
            intent,
            confidence_score,
            is_ai_generated,
            is_read
        )
        VALUES
        (
            :conversation_id,
            'ai',
            NULL,
            :message,
            'escalation',
            :intent,
            :confidence,
            1,
            1
        )
    ");

    $stmt->execute([
        'conversation_id' =>
            $conversationId,

        'message' =>
            $aiResponse,

        'intent' =>
            $intent,

        'confidence' =>
            $confidence,
    ]);

    $aiMessageId =
        (int) $pdo->lastInsertId();


    /*
    |--------------------------------------------------------------------------
    | ANALYTICS: ESCALATED
    |--------------------------------------------------------------------------
    */

    saveAnalyticsEvent(
        $pdo,
        $conversationId,
        'ai_escalated',
        [
            'escalation_id' =>
                $escalationId,

            'message_id' =>
                $userMessageId,

            'confidence' =>
                $confidence,

            'threshold' =>
                CONFIDENCE_THRESHOLD,

            'reason' =>
                $escalationReason,

            'department_id' =>
                $departmentId,
        ]
    );


    /*
    |--------------------------------------------------------------------------
    | FINAL RESPONSE
    |--------------------------------------------------------------------------
    */

    jsonResponse([
        'success' =>
            true,

        'conversation_id' =>
            $conversationId,

        'conversation_uuid' =>
            $conversationUuid,

        'message_id' =>
            $aiMessageId,

        'sender_type' =>
            'ai',

        'message' =>
            $aiResponse,

        'confidence' =>
            $confidence,

        'threshold' =>
            CONFIDENCE_THRESHOLD,

        'intent' =>
            $intent,

        'department_id' =>
            $departmentId,

        'escalation_id' =>
            $escalationId,

        'escalated' =>
            true,

        'waiting_for_staff' =>
            true,
    ]);


/*
|--------------------------------------------------------------------------
| ERROR HANDLING
|--------------------------------------------------------------------------
*/

} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | LOG COMPLETE ERROR
    |--------------------------------------------------------------------------
    */

    error_log(
        "UV-ASSIST CHAT API ERROR\n" .
        "Message: " . $e->getMessage() . "\n" .
        "File: " . $e->getFile() . "\n" .
        "Line: " . $e->getLine() . "\n" .
        "Trace:\n" . $e->getTraceAsString()
    );


    /*
    |--------------------------------------------------------------------------
    | DEVELOPMENT ERROR RESPONSE
    |--------------------------------------------------------------------------
    |
    | This exposes the actual database/PHP error so we can diagnose
    | the current 500 error.
    |
    | Once the system is working, set DEBUG to false.
    |--------------------------------------------------------------------------
    */

    $debug = true;

    $response = [
        'success' =>
            false,

        'message' =>
            'UV-ASSIST encountered a server error.',
    ];

    if ($debug) {

        $response['error'] =
            $e->getMessage();

        $response['file'] =
            basename($e->getFile());

        $response['line'] =
            $e->getLine();
    }

    jsonResponse(
        $response,
        500
    );
}