<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

session_start();

date_default_timezone_set('Asia/Manila');


/*
|--------------------------------------------------------------------------
| VISITOR SESSION
|--------------------------------------------------------------------------
|
| Every public visitor receives a unique UUID.
| The visitor does not need to create an account just to use UV-Assist.
|
*/

if (!isset($_SESSION['visitor_uuid'])) {
    $_SESSION['visitor_uuid'] = sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x%04x',
        random_int(0, 0xffff),
        random_int(0, 0xffff),
        random_int(0, 0xffff),
        random_int(0, 0x0fff) | 0x4000,
        random_int(0, 0x3fff) | 0x8000,
        random_int(0, 0xffff),
        random_int(0, 0xffff),
        random_int(0, 0xffff),
        random_int(0, 0xffff)
    );
}

$visitorUuid = $_SESSION['visitor_uuid'];


/*
|--------------------------------------------------------------------------
| REGISTER / UPDATE VISITOR
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare("
        SELECT id
        FROM chat_visitors
        WHERE visitor_uuid = ?
        LIMIT 1
    ");

    $stmt->execute([$visitorUuid]);

    $visitor = $stmt->fetch();

    if (!$visitor) {

        $stmt = $pdo->prepare("
            INSERT INTO chat_visitors
            (
                visitor_uuid,
                user_type,
                session_id,
                current_page,
                last_seen_at
            )
            VALUES
            (
                ?,
                'unknown',
                ?,
                ?,
                NOW()
            )
        ");

        $stmt->execute([
            $visitorUuid,
            session_id(),
            $_SERVER['REQUEST_URI'] ?? '/'
        ]);

    } else {

        $stmt = $pdo->prepare("
            UPDATE chat_visitors
            SET
                session_id = ?,
                current_page = ?,
                last_seen_at = NOW()
            WHERE visitor_uuid = ?
        ");

        $stmt->execute([
            session_id(),
            $_SERVER['REQUEST_URI'] ?? '/',
            $visitorUuid
        ]);
    }

} catch (PDOException $e) {

    error_log(
        'UV-ASSIST Visitor Error: ' . $e->getMessage()
    );

    // Do not expose database errors to the visitor.
}

?>
<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <meta
        name="description"
        content="UV-Assist — Intelligent Campus Helpdesk Chatbot of the University of the Visayas">

    <title>UV-Assist | University of the Visayas</title>


    <!--
    |--------------------------------------------------------------------------
    | Tailwind CSS
    |--------------------------------------------------------------------------
    -->

    <script src="https://cdn.tailwindcss.com"></script>


    <!--
    |--------------------------------------------------------------------------
    | Google Fonts
    |--------------------------------------------------------------------------
    -->

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap"
        rel="stylesheet">


    <style>

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
        }

        .heading {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .chat-scroll::-webkit-scrollbar {
            width: 6px;
        }

        .chat-scroll::-webkit-scrollbar-track {
            background: transparent;
        }

        .chat-scroll::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 999px;
        }

        .chat-scroll::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .message-animation {
            animation: messageIn 0.25s ease-out;
        }

        @keyframes messageIn {
            from {
                opacity: 0;
                transform: translateY(6px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }


        /*
        |--------------------------------------------------------------------------
        | BETSY — the UV-Assist campus companion
        |--------------------------------------------------------------------------
        |
        | A single SVG "orb" character, styled after the visual language
        | of real assistant UIs (Siri / Google Assistant style presence
        | orbs) so it reads as an intentional interface element rather
        | than a mascot bolted onto a chatbot. All motion below is
        | driven by CSS keyframes plus small state classes toggled from
        | JS; nothing here requires a JS animation loop.
        |
        */

        #betsyStage {
            transition: padding 0.4s ease;
        }

        .betsy-wrap {
            animation: betsyFloat 4.5s ease-in-out infinite;
            transform-origin: center;
        }

        .betsy-svg {
            display: block;
            filter: drop-shadow(0 12px 24px rgba(6, 78, 59, 0.25));
            transition:
                width 0.4s ease,
                height 0.4s ease,
                transform 0.3s ease;
        }

        .betsy-glow {
            animation: betsyBreathe 4.5s ease-in-out infinite;
            transform-box: fill-box;
            transform-origin: center;
        }

        .betsy-body {
            transform-box: fill-box;
            transform-origin: center;
            animation: betsyBreathe 4.5s ease-in-out infinite;
        }

        .betsy-eye {
            transform-box: fill-box;
            transform-origin: center;
            animation: betsyBlink 6s ease-in-out infinite;
            transition: transform 0.2s ease;
        }

        .betsy-eye-r {
            animation-delay: -0.15s;
        }

        .orbit-ring {
            transform-box: fill-box;
            transform-origin: center;
            animation: orbitSpin 9s linear infinite;
        }

        .mouth-flat {
            display: none;
        }

        /* Listening: input is focused, Betsy perks up slightly */

        .betsy.state-listening .betsy-svg {
            transform: rotate(-2deg) scale(1.03);
        }

        .betsy.state-listening .betsy-eye {
            animation-play-state: paused;
            transform: scaleY(1.12);
        }

        /* Thinking: waiting on a reply */

        .betsy.state-thinking .orbit-ring {
            animation-duration: 2s;
        }

        .betsy.state-thinking .betsy-eye {
            animation-play-state: paused;
            transform: scaleY(0.35);
        }

        .betsy.state-thinking .mouth-smile {
            display: none;
        }

        .betsy.state-thinking .mouth-flat {
            display: block;
        }

        .betsy.state-thinking .betsy-wrap {
            animation-duration: 1.4s;
        }

        /* Happy: reply just landed */

        .betsy.state-happy .betsy-wrap {
            animation: betsyPop 0.5s ease;
        }

        .betsy-hello {
            transform-box: fill-box;
            transform-origin: bottom center;
        }

        .betsy.greet .betsy-hello {
            animation: wiggleHello 0.9s ease 1;
        }

        @keyframes betsyFloat {
            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-8px);
            }
        }

        @keyframes betsyBreathe {
            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.035);
            }
        }

        @keyframes betsyBlink {
            0%,
            92%,
            100% {
                transform: scaleY(1);
            }

            96% {
                transform: scaleY(0.1);
            }
        }

        @keyframes orbitSpin {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        @keyframes wiggleHello {
            0%,
            100% {
                transform: rotate(0deg);
            }

            20% {
                transform: rotate(-8deg);
            }

            40% {
                transform: rotate(7deg);
            }

            60% {
                transform: rotate(-4deg);
            }

            80% {
                transform: rotate(2deg);
            }
        }

        @keyframes betsyPop {
            0% {
                transform: translateY(0) scale(1);
            }

            35% {
                transform: translateY(-14px) scale(1.08);
            }

            100% {
                transform: translateY(0) scale(1);
            }
        }

        @keyframes bubbleIn {
            from {
                opacity: 0;
                transform: translateY(6px) scale(0.96);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .speech-bubble {
            animation: bubbleIn 0.35s ease-out 0.5s both;
        }

        /* Mini avatar used next to each Betsy message in the chat thread */

        .mini-betsy {
            background: radial-gradient(
                circle at 32% 28%,
                #6ee7b7,
                #0f766e 70%
            );

            box-shadow: inset 0 -3px 6px rgba(0, 0, 0, 0.15);
        }

        .mini-betsy .mini-eye {
            animation: betsyBlink 6s ease-in-out infinite;
        }

        .think-dot {
            animation: thinkDot 1.2s ease-in-out infinite;
        }

        .think-dot:nth-child(2) {
            animation-delay: 0.15s;
        }

        .think-dot:nth-child(3) {
            animation-delay: 0.3s;
        }

        @keyframes thinkDot {
            0%,
            80%,
            100% {
                transform: scale(0.6);
                opacity: 0.4;
            }

            40% {
                transform: scale(1);
                opacity: 1;
            }
        }


        /* Landing <-> chat layout states */

        #chatThread {
            display: none;
        }

        body.mode-chat #chatThread {
            display: flex;
        }

        body.mode-chat #landingCopy {
            display: none;
        }

        body.mode-chat #suggestionRow {
            display: none;
        }

        body.mode-chat #betsyStage {
            padding-top: 0.75rem;
            padding-bottom: 0.25rem;
        }

        body.mode-chat .betsy-svg {
            width: 44px;
            height: 44px;
        }

        body.mode-chat .speech-bubble {
            display: none;
        }

        body.mode-chat #betsyStage {
            flex-direction: row;
            justify-content: flex-start;
            gap: 0.6rem;
            border-bottom: 1px solid #e2e8f0;
        }

        #betsyStage {
            flex-direction: column;
        }

        @media (prefers-reduced-motion: reduce) {

            .betsy-wrap,
            .betsy-glow,
            .betsy-body,
            .betsy-eye,
            .orbit-ring,
            .message-animation,
            .speech-bubble {
                animation: none !important;
            }

        }

    </style>

</head>


<body class="min-h-screen bg-slate-50 text-slate-900 mode-landing">


    <!--
    |--------------------------------------------------------------------------
    | NAVIGATION
    |--------------------------------------------------------------------------
    -->

    <header class="border-b border-slate-200 bg-white">

        <div
            class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">

            <div class="flex items-center gap-3">

                <div
                    class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-900 text-white shadow-sm">

                    <span class="text-lg font-bold">
                        UV
                    </span>

                </div>

                <div>

                    <div class="heading text-lg font-bold text-emerald-950">
                        UV-Assist
                    </div>

                    <div class="text-xs text-slate-500">
                        University of the Visayas
                    </div>

                </div>

            </div>


            <div class="hidden items-center gap-6 text-sm font-medium text-slate-600 md:flex">

                <a
                    href="#features"
                    class="transition hover:text-emerald-800">
                    Features
                </a>

                <a
                    href="#help"
                    class="transition hover:text-emerald-800">
                    How It Works
                </a>

            </div>

        </div>

    </header>


    <!--
    |--------------------------------------------------------------------------
    | MAIN — Betsy, the chat-first homepage
    |--------------------------------------------------------------------------
    -->

    <main>

        <section class="bg-white">

            <div
                id="chatShell"
                class="mx-auto flex min-h-[calc(100vh-73px)] max-w-3xl flex-col px-6">


                <!--
                Betsy stage: big + centered while idle,
                shrinks to a pinned avatar once chatting
                -->

                <div
                    id="betsyStage"
                    class="flex items-center pt-14">

                    <div class="relative flex flex-col items-center">

                        <div
                            class="speech-bubble mb-3 max-w-xs rounded-2xl rounded-bl-md border border-emerald-100 bg-emerald-50 px-4 py-2.5 text-center text-sm font-medium text-emerald-900 shadow-sm">

                            <span id="greetingText">
                                Hi, I'm Betsy 👋
                            </span>

                        </div>


                        <div class="betsy-wrap">

                            <svg
                                id="betsy"
                                class="betsy greet betsy-svg"
                                width="168"
                                height="168"
                                viewBox="0 0 200 200"
                                role="img"
                                aria-label="Betsy, the UV-Assist campus companion, idle">

                                <defs>

                                    <radialGradient
                                        id="orbGradient"
                                        cx="35%"
                                        cy="30%"
                                        r="75%">

                                        <stop
                                            offset="0%"
                                            stop-color="#6ee7b7" />

                                        <stop
                                            offset="55%"
                                            stop-color="#059669" />

                                        <stop
                                            offset="100%"
                                            stop-color="#065f46" />

                                    </radialGradient>


                                    <radialGradient
                                        id="glowGradient"
                                        cx="50%"
                                        cy="50%"
                                        r="50%">

                                        <stop
                                            offset="0%"
                                            stop-color="#34d399"
                                            stop-opacity="0.35" />

                                        <stop
                                            offset="100%"
                                            stop-color="#34d399"
                                            stop-opacity="0" />

                                    </radialGradient>

                                </defs>


                                <circle
                                    class="betsy-glow"
                                    cx="100"
                                    cy="100"
                                    r="92"
                                    fill="url(#glowGradient)" />


                                <g class="orbit-ring">

                                    <circle
                                        cx="100"
                                        cy="24"
                                        r="4"
                                        fill="#a7f3d0"
                                        opacity="0.9" />

                                    <circle
                                        cx="168"
                                        cy="130"
                                        r="3"
                                        fill="#fbbf24"
                                        opacity="0.8" />

                                    <circle
                                        cx="34"
                                        cy="140"
                                        r="2.5"
                                        fill="#a7f3d0"
                                        opacity="0.7" />

                                </g>


                                <circle
                                    class="betsy-body betsy-hello"
                                    cx="100"
                                    cy="100"
                                    r="66"
                                    fill="url(#orbGradient)" />


                                <ellipse
                                    cx="78"
                                    cy="72"
                                    rx="22"
                                    ry="12"
                                    fill="#ffffff"
                                    opacity="0.18" />


                                <rect
                                    class="betsy-eye betsy-eye-l"
                                    x="72"
                                    y="92"
                                    width="15"
                                    height="20"
                                    rx="7.5"
                                    fill="#022c22" />

                                <rect
                                    class="betsy-eye betsy-eye-r"
                                    x="113"
                                    y="92"
                                    width="15"
                                    height="20"
                                    rx="7.5"
                                    fill="#022c22" />


                                <path
                                    class="mouth-smile"
                                    d="M 80 128 Q 100 142 120 128"
                                    stroke="#022c22"
                                    stroke-width="5"
                                    stroke-linecap="round"
                                    fill="none" />

                                <path
                                    class="mouth-flat"
                                    d="M 84 132 L 116 132"
                                    stroke="#022c22"
                                    stroke-width="5"
                                    stroke-linecap="round"
                                    fill="none" />

                            </svg>

                        </div>

                    </div>


                    <div
                        class="ml-3 hidden text-sm font-semibold text-emerald-950">

                        Betsy

                    </div>

                </div>


                <div
                    id="landingCopy"
                    class="mt-4 text-center">

                    <h1
                        class="heading text-3xl font-extrabold tracking-tight text-slate-950 sm:text-4xl">

                        Ask Betsy anything about campus.

                    </h1>

                    <p
                        class="mx-auto mt-3 max-w-md text-sm leading-6 text-slate-600 sm:text-base">

                        Admissions, enrollment, registrar requests, IT support —
                        Betsy searches the University's knowledge base and
                        loops in staff when a human's needed.

                    </p>

                </div>


                <!--
                Chat thread
                Hidden until the first message is sent
                -->

                <div
                    id="chatThread"
                    class="chat-scroll flex-1 flex-col gap-4 overflow-y-auto py-6"
                    aria-live="polite">
                </div>


                <!-- Composer -->

                <div
                    id="composerWrap"
                    class="sticky bottom-0 bg-white pb-8 pt-3">

                    <form
                        id="chatForm"
                        class="mx-auto flex max-w-2xl items-end gap-2 rounded-2xl border border-slate-300 bg-slate-50 p-2 shadow-sm transition focus-within:border-emerald-600 focus-within:ring-2 focus-within:ring-emerald-100">

                        <textarea
                            id="messageInput"
                            rows="1"
                            placeholder="Message Betsy…"
                            aria-label="Message Betsy"
                            class="max-h-32 min-h-[42px] flex-1 resize-none bg-transparent px-3 py-2.5 text-sm outline-none placeholder:text-slate-400"></textarea>


                        <button
                            type="submit"
                            id="sendButton"
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-900 text-white transition hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50"
                            aria-label="Send message">

                            ↑

                        </button>

                    </form>


                    <div
                        id="suggestionRow"
                        class="mx-auto mt-3 flex max-w-2xl flex-wrap justify-center gap-2">

                        <button
                            type="button"
                            onclick="useSuggestion('What are the admission requirements?')"
                            class="rounded-full border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-600 transition hover:border-emerald-300 hover:text-emerald-800">

                            Admission requirements

                        </button>


                        <button
                            type="button"
                            onclick="useSuggestion('How can I request my transcript?')"
                            class="rounded-full border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-600 transition hover:border-emerald-300 hover:text-emerald-800">

                            Transcript request

                        </button>


                        <button
                            type="button"
                            onclick="useSuggestion('How do I contact IT Support?')"
                            class="rounded-full border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-600 transition hover:border-emerald-300 hover:text-emerald-800">

                            IT Support

                        </button>

                    </div>


                    <p
                        class="mt-3 text-center text-[11px] text-slate-400">

                        Betsy may escalate questions to University staff when necessary.

                    </p>

                </div>

            </div>

        </section>


        <!--
        |--------------------------------------------------------------------------
        | FEATURES
        |--------------------------------------------------------------------------
        -->

        <section
            id="features"
            class="border-y border-slate-200 bg-slate-50">

            <div
                class="mx-auto max-w-7xl px-6 py-20">

                <div class="mx-auto max-w-2xl text-center">

                    <p
                        class="text-sm font-semibold uppercase tracking-wider text-emerald-800">

                        UV-Assist

                    </p>


                    <h2
                        class="heading mt-2 text-3xl font-bold text-slate-950">

                        Campus help when you need it

                    </h2>


                    <p class="mt-4 text-slate-600">

                        Get quick access to trusted university information
                        while keeping a path open to human assistance.

                    </p>

                </div>


                <div
                    class="mt-12 grid gap-6 md:grid-cols-2 lg:grid-cols-4">


                    <div
                        class="rounded-2xl border border-slate-200 bg-white p-6">

                        <div
                            class="mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-xl">

                            🔎

                        </div>

                        <h3 class="heading font-bold">
                            Knowledge Search
                        </h3>

                        <p
                            class="mt-2 text-sm leading-6 text-slate-600">

                            Find relevant information from the University's
                            maintained knowledge base.

                        </p>

                    </div>


                    <div
                        class="rounded-2xl border border-slate-200 bg-white p-6">

                        <div
                            class="mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-xl">

                            🤖

                        </div>

                        <h3 class="heading font-bold">
                            Intelligent Answers
                        </h3>

                        <p
                            class="mt-2 text-sm leading-6 text-slate-600">

                            Betsy uses intent classification and retrieval
                            to provide grounded responses.

                        </p>

                    </div>


                    <div
                        class="rounded-2xl border border-slate-200 bg-white p-6">

                        <div
                            class="mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-xl">

                            👤

                        </div>

                        <h3 class="heading font-bold">
                            Human Escalation
                        </h3>

                        <p
                            class="mt-2 text-sm leading-6 text-slate-600">

                            Questions requiring staff assistance can be routed
                            to the appropriate University office.

                        </p>

                    </div>


                    <div
                        class="rounded-2xl border border-slate-200 bg-white p-6">

                        <div
                            class="mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-xl">

                            📚

                        </div>

                        <h3 class="heading font-bold">
                            Trusted Sources
                        </h3>

                        <p
                            class="mt-2 text-sm leading-6 text-slate-600">

                            Responses can reference the knowledge-base source
                            used to answer the question.

                        </p>

                    </div>

                </div>

            </div>

        </section>


        <!--
        |--------------------------------------------------------------------------
        | HOW IT WORKS
        |--------------------------------------------------------------------------
        -->

        <section
            id="help"
            class="bg-white">

            <div
                class="mx-auto max-w-7xl px-6 py-20">

                <div
                    class="grid gap-12 lg:grid-cols-2 lg:items-center">

                    <div>

                        <p
                            class="text-sm font-semibold uppercase tracking-wider text-emerald-800">

                            How it works

                        </p>


                        <h2
                            class="heading mt-2 text-3xl font-bold text-slate-950">

                            Ask. Retrieve. Respond. Escalate.

                        </h2>


                        <p
                            class="mt-5 leading-7 text-slate-600">

                            Betsy first searches the University's maintained
                            knowledge base. When the system has sufficient
                            confidence, it provides a grounded response.
                            When confidence is too low or the request requires
                            human assistance, the conversation can be escalated.

                        </p>

                    </div>


                    <div class="space-y-4">


                        <div
                            class="flex gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-5">

                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-900 text-sm font-bold text-white">

                                1

                            </div>


                            <div>

                                <h3 class="font-semibold">
                                    Ask your question
                                </h3>

                                <p
                                    class="mt-1 text-sm text-slate-600">

                                    Ask Betsy about campus services, procedures,
                                    requirements, or policies.

                                </p>

                            </div>

                        </div>


                        <div
                            class="flex gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-5">

                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-900 text-sm font-bold text-white">

                                2

                            </div>


                            <div>

                                <h3 class="font-semibold">
                                    Betsy retrieves information
                                </h3>

                                <p
                                    class="mt-1 text-sm text-slate-600">

                                    The system identifies the user's intent
                                    and retrieves relevant knowledge.

                                </p>

                            </div>

                        </div>


                        <div
                            class="flex gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-5">

                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-900 text-sm font-bold text-white">

                                3

                            </div>


                            <div>

                                <h3 class="font-semibold">
                                    Receive an answer or human assistance
                                </h3>

                                <p
                                    class="mt-1 text-sm text-slate-600">

                                    High-confidence questions receive an
                                    automated answer. Low-confidence or
                                    complex inquiries can be escalated.

                                </p>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </section>

    </main>


    <!--
    |--------------------------------------------------------------------------
    | FOOTER
    |--------------------------------------------------------------------------
    -->

    <footer
        class="border-t border-slate-200 bg-slate-50">

        <div
            class="mx-auto flex max-w-7xl flex-col gap-2 px-6 py-8 text-sm text-slate-500 md:flex-row md:items-center md:justify-between">

            <div>
                © <?= date('Y') ?> University of the Visayas
            </div>

            <div>
                UV-Assist — Intelligent Campus Helpdesk
            </div>

        </div>

    </footer>


    <script>

        /*
        |--------------------------------------------------------------------------
        | ELEMENTS
        |--------------------------------------------------------------------------
        */

        const betsy =
            document.getElementById('betsy');

        const betsyStage =
            document.getElementById('betsyStage');

        const greetingText =
            document.getElementById('greetingText');

        const chatThread =
            document.getElementById('chatThread');

        const messageInput =
            document.getElementById('messageInput');

        const chatForm =
            document.getElementById('chatForm');

        const sendButton =
            document.getElementById('sendButton');

        let chatStarted = false;


        /*
        |--------------------------------------------------------------------------
        | TIME-AWARE GREETING
        |--------------------------------------------------------------------------
        */

        function setGreeting() {

            const hour = new Date().getHours();

            let text = "Hi, I'm Betsy 👋";

            if (hour < 5) {

                text =
                    "Studying late? I'm Betsy — here if you need anything.";

            } else if (hour < 12) {

                text =
                    "Good morning! I'm Betsy 👋";

            } else if (hour < 18) {

                text =
                    "Good afternoon! I'm Betsy 👋";

            } else {

                text =
                    "Good evening! I'm Betsy 👋";

            }

            greetingText.textContent = text;
        }

        setGreeting();


        /*
        |--------------------------------------------------------------------------
        | BETSY STATE MACHINE
        |--------------------------------------------------------------------------
        */

        function setBetsyState(state) {

            betsy.classList.remove(
                'state-idle',
                'state-listening',
                'state-thinking',
                'state-happy'
            );

            betsy.classList.add('state-' + state);
        }

        setBetsyState('idle');


        // Retrigger the little "hello" wiggle once, after the entrance settles.

        setTimeout(() => {
            betsy.classList.remove('greet');
        }, 1400);


        /*
        |--------------------------------------------------------------------------
        | EYE TRACKING
        | Desktop only — Betsy glances toward the cursor
        |--------------------------------------------------------------------------
        */

        if (window.matchMedia('(pointer: fine)').matches) {

            let ticking = false;

            window.addEventListener('mousemove', (event) => {

                if (ticking) return;

                ticking = true;

                requestAnimationFrame(() => {

                    const rect =
                        betsy.getBoundingClientRect();

                    const cx =
                        rect.left + rect.width / 2;

                    const cy =
                        rect.top + rect.height / 2;

                    const dx =
                        Math.max(
                            -1,
                            Math.min(
                                1,
                                (event.clientX - cx) / 260
                            )
                        );

                    const dy =
                        Math.max(
                            -1,
                            Math.min(
                                1,
                                (event.clientY - cy) / 260
                            )
                        );

                    const eyes =
                        betsy.querySelectorAll('.betsy-eye');

                    eyes.forEach((eye) => {

                        eye.style.translate =
                            `${dx * 4}px ${dy * 3}px`;

                    });

                    ticking = false;

                });

            });

        }


        /*
        |--------------------------------------------------------------------------
        | SUGGESTIONS
        |--------------------------------------------------------------------------
        */

        function useSuggestion(message) {

            messageInput.value = message;

            messageInput.focus();

            messageInput.dispatchEvent(
                new Event('input')
            );
        }


        /*
        |--------------------------------------------------------------------------
        | ADD MESSAGE
        |--------------------------------------------------------------------------
        */

        function addMessage(
            message,
            sender = 'user'
        ) {

            const wrapper =
                document.createElement('div');

            wrapper.className =
                'message-animation flex gap-2 ' +
                (
                    sender === 'user'
                        ? 'justify-end'
                        : 'justify-start'
                );


            if (sender === 'ai') {

                const avatar =
                    document.createElement('div');

                avatar.className =
                    'mini-betsy relative mt-1 h-7 w-7 shrink-0 rounded-full';

                avatar.innerHTML = `
                    <span class="mini-eye absolute left-[7px] top-[11px] h-[6px] w-[4px] rounded-full bg-emerald-950"></span>
                    <span class="mini-eye absolute right-[7px] top-[11px] h-[6px] w-[4px] rounded-full bg-emerald-950"></span>
                `;

                wrapper.appendChild(avatar);
            }


            const bubble =
                document.createElement('div');


            if (sender === 'user') {

                bubble.className =
                    'max-w-[80%] rounded-2xl rounded-br-md bg-emerald-900 px-4 py-3 text-sm leading-6 text-white';

            } else {

                bubble.className =
                    'max-w-[80%] rounded-2xl rounded-bl-md bg-white border border-slate-200 px-4 py-3 text-sm leading-6 text-slate-700 shadow-sm';

            }


            bubble.textContent = message;

            wrapper.appendChild(bubble);

            chatThread.appendChild(wrapper);

            chatThread.scrollTop =
                chatThread.scrollHeight;
        }


        /*
        |--------------------------------------------------------------------------
        | THINKING INDICATOR
        |--------------------------------------------------------------------------
        */

        function showThinking() {

            const wrapper =
                document.createElement('div');

            wrapper.id =
                'thinkingIndicator';

            wrapper.className =
                'message-animation flex items-end gap-2 justify-start';

            wrapper.innerHTML = `
                <div class="mini-betsy relative mt-1 h-7 w-7 shrink-0 rounded-full"></div>

                <div class="flex items-center gap-1 rounded-2xl rounded-bl-md border border-slate-200 bg-white px-4 py-3 shadow-sm">

                    <span class="think-dot h-1.5 w-1.5 rounded-full bg-slate-400"></span>

                    <span class="think-dot h-1.5 w-1.5 rounded-full bg-slate-400"></span>

                    <span class="think-dot h-1.5 w-1.5 rounded-full bg-slate-400"></span>

                </div>
            `;

            chatThread.appendChild(wrapper);

            chatThread.scrollTop =
                chatThread.scrollHeight;
        }


        function removeThinking() {

            const el =
                document.getElementById(
                    'thinkingIndicator'
                );

            if (el) {
                el.remove();
            }
        }


        /*
        |--------------------------------------------------------------------------
        | START CHAT MODE
        | Landing → conversation, ChatGPT-style
        |--------------------------------------------------------------------------
        */

        function enterChatMode() {

            if (chatStarted) return;

            chatStarted = true;

            document.body.classList.remove(
                'mode-landing'
            );

            document.body.classList.add(
                'mode-chat'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | LISTENING STATE
        |--------------------------------------------------------------------------
        */

        messageInput.addEventListener(
            'focus',
            () => setBetsyState('listening')
        );

        messageInput.addEventListener(
            'blur',
            () => setBetsyState('idle')
        );


        /*
        |--------------------------------------------------------------------------
        | SEND MESSAGE
        |--------------------------------------------------------------------------
        |
        | This currently connects to:
        |
        |     api/chat.php
        |
        */

        chatForm.addEventListener(
            'submit',
            async function (event) {

                event.preventDefault();

                const message =
                    messageInput.value.trim();

                if (!message) return;


                enterChatMode();

                addMessage(
                    message,
                    'user'
                );


                messageInput.value = '';

                messageInput.style.height =
                    'auto';

                sendButton.disabled = true;

                setBetsyState('thinking');

                showThinking();


                try {

                    const response =
                        await fetch(
                            'api/chat.php',
                            {
                                method: 'POST',

                                headers: {
                                    'Content-Type':
                                        'application/json',

                                    'Accept':
                                        'application/json'
                                },

                                body:
                                    JSON.stringify({
                                        message: message
                                    })
                            }
                        );


                    const data =
                        await response.json();


                    removeThinking();


                    if (
                        !response.ok ||
                        !data.success
                    ) {

                        throw new Error(
                            data.message ||
                            'Unable to process your request.'
                        );
                    }


                    addMessage(
                        data.message,
                        'ai'
                    );

                    setBetsyState('happy');


                    setTimeout(
                        () => setBetsyState('idle'),
                        700
                    );


                } catch (error) {

                    removeThinking();


                    addMessage(
                        'Sorry, I am unable to process your request right now. Please try again or request assistance from a University staff member.',
                        'ai'
                    );


                    setBetsyState('idle');

                    console.error(
                        'UV-Assist error:',
                        error
                    );


                } finally {

                    sendButton.disabled =
                        false;

                    messageInput.focus();

                }

            }
        );


        /*
        |--------------------------------------------------------------------------
        | TEXTAREA AUTO RESIZE + ENTER TO SEND
        |--------------------------------------------------------------------------
        */

        messageInput.addEventListener(
            'input',
            function () {

                this.style.height =
                    'auto';

                this.style.height =
                    Math.min(
                        this.scrollHeight,
                        128
                    ) + 'px';

            }
        );


        messageInput.addEventListener(
            'keydown',
            function (event) {

                if (
                    event.key === 'Enter' &&
                    !event.shiftKey
                ) {

                    event.preventDefault();

                    chatForm.requestSubmit();

                }

            }
        );

    </script>


</body>

</html>