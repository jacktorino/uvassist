<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| UV-ASSIST — STATIC PROTOTYPE
|--------------------------------------------------------------------------
| No database, no session, no api/chat.php. All answers (including form
| downloads) are matched and served client-side from the KNOWLEDGE_BASE
| array in the <script> block below. Swap this for the DB-backed version
| once the real backend is ready.
|--------------------------------------------------------------------------
*/

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

        .betsy.state-listening .betsy-svg {
            transform: rotate(-2deg) scale(1.03);
        }

        .betsy.state-listening .betsy-eye {
            animation-play-state: paused;
            transform: scaleY(1.12);
        }

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

        /* Form / document reply card */

        .form-card {
            background: #ffffff;
            border: 1px solid #a7f3d0;
            border-radius: 14px;
            padding: 14px 16px;
            max-width: 320px;
            margin-top: 6px;
        }

        .form-card .form-card-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 700;
            font-size: 14px;
            color: #022c22;
            margin-bottom: 2px;
        }

        .form-card .form-card-sub {
            font-size: 11.5px;
            color: #64748b;
            margin-bottom: 10px;
        }

        .form-card ul {
            margin: 0 0 12px;
            padding-left: 18px;
        }

        .form-card li {
            font-size: 12.5px;
            color: #334155;
            margin-bottom: 4px;
        }

        .form-dl-btn {
            background: #065f46;
            color: #ffffff;
            border: none;
            border-radius: 20px;
            padding: 8px 16px;
            font-size: 12.5px;
            font-weight: 600;
            cursor: pointer;
        }

        .form-dl-btn:hover {
            background: #054f3d;
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
                        ask Betsy a question or ask for a form, and she'll hand
                        it to you directly.

                    </p>

                </div>


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
                            onclick="useSuggestion('Can I have the TOR request form?')"
                            class="rounded-full border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-600 transition hover:border-emerald-300 hover:text-emerald-800">

                            TOR request form

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

                        Prototype build — answers and forms are served from a static knowledge base, not a live backend.

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

                            📄

                        </div>

                        <h3 class="heading font-bold">
                            Forms &amp; Documents
                        </h3>

                        <p
                            class="mt-2 text-sm leading-6 text-slate-600">

                            Ask for a form by name and Betsy hands you a
                            ready-to-fill copy right in the chat.

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
                            confidence, it provides a grounded response —
                            including handing over a form when that's what
                            you asked for. When confidence is too low or the
                            request requires human assistance, the
                            conversation can be escalated.

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
                                    requirements, policies — or ask for a form
                                    directly.

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
                                    and retrieves the matching knowledge-base
                                    entry — answer or downloadable form.

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
                                    Receive an answer, a form, or human assistance
                                </h3>

                                <p
                                    class="mt-1 text-sm text-slate-600">

                                    High-confidence questions receive an
                                    automated answer or a downloadable form.
                                    Low-confidence or complex inquiries can be
                                    escalated.

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
        | STATIC KNOWLEDGE BASE
        |--------------------------------------------------------------------------
        | Two entry types:
        |   - "faq"  → answered with plain text
        |   - "form" → answered with a short blurb + a downloadable form card
        |
        | Edit this array to add/change what Betsy can answer or hand out.
        |--------------------------------------------------------------------------
        */

        const KNOWLEDGE_BASE = [

            {
                type: 'faq',
                title: 'Enrollment Requirements',
                department: 'Admissions Office',
                keywords: ['document', 'requirement', 'what do i need', 'admission requirements'],
                answer: 'For new students: Form 138 (report card), PSA birth certificate, and 2 ID photos. For continuing students: your registration form from the previous semester and a valid UV ID.'
            },
            {
                type: 'form',
                title: 'New Student Enrollment Form',
                department: 'Admissions Office',
                keywords: ['enrollment form', 'new student form', 'admission form', 'application form'],
                answer: 'Here is the New Student Enrollment Form. Fill it out and bring it, along with your requirements, to the Admissions window.',
                fields: ['Full Name', 'Date of Birth', 'Previous School', 'Program Applied For', 'Contact Number', 'Email Address', 'Guardian Name']
            },
            {
                type: 'faq',
                title: 'Admission Process',
                department: 'Admissions Office',
                keywords: ['admission', 'apply', 'application', 'applicant', 'entrance exam', 'freshman', 'transferee', 'transfer'],
                answer: 'Freshman applicants need Form 138, PSA birth certificate, and a passing entrance exam result. Transferees additionally submit a Transcript of Records and an Honorable Dismissal from their previous school.'
            },
            {
                type: 'faq',
                title: 'Transcript of Records (TOR)',
                department: 'Registrar',
                keywords: ['how do i request', 'transcript', 'school records', 'academic record', 'diploma', 'certification', 'good moral', 'honorable dismissal'],
                answer: "Submit a TOR request at the Registrar's window or through the online request portal. Processing takes 5–7 working days once outstanding balances are cleared."
            },
            {
                type: 'form',
                title: 'Transcript of Records (TOR) Request Form',
                department: 'Registrar',
                keywords: ['tor form', 'tor request form', 'transcript form', 'transcript request'],
                answer: 'Here is the Transcript of Records Request Form. Complete it and submit at the Registrar window with your official receipt.',
                fields: ['Full Name', 'Student ID', 'Program', 'Year Graduated / Last Attended', 'Purpose of Request', 'Number of Copies', 'Contact Number']
            },
            {
                type: 'faq',
                title: 'Enrollment Period',
                department: 'Registrar',
                keywords: ['enroll', 'enrollment', 'registration', 'register', 'schedule of enrollment', 'class schedule', 'load', 'add subject', 'drop subject'],
                answer: "Enrollment for the upcoming semester opens two weeks before the term starts. Continuing students enroll by year level; exact dates are posted on the Registrar's bulletin and the student portal."
            },
            {
                type: 'faq',
                title: 'Scholarships',
                department: 'Student Affairs',
                keywords: ['scholarship', 'stipend', 'financial assistance', 'financial aid', 'discount', 'grant'],
                answer: "UV offers academic scholarships (President's & Dean's List), athletic grants, and partial working-student discounts. Applications are filed at the Scholarship Office at the start of each school year."
            },
            {
                type: 'form',
                title: 'Scholarship Application Form',
                department: 'Student Affairs',
                keywords: ['scholarship form', 'scholarship application', 'financial aid form'],
                answer: 'Here is the Scholarship Application Form. Submit it to the Scholarship Office with your latest grades.',
                fields: ['Full Name', 'Student ID', 'Program & Year Level', 'General Weighted Average', 'Scholarship Type', 'Contact Number', 'Email Address']
            },
            {
                type: 'faq',
                title: 'Guidance Office',
                department: 'Student Affairs',
                keywords: ['guidance', 'counseling', 'counsellor', 'counselor', 'student organization', 'student activity'],
                answer: 'The Guidance Office is at the Student Affairs building, 2nd floor, open 8am–5pm on weekdays.'
            },
            {
                type: 'faq',
                title: 'IT / Portal Support',
                department: 'IT Support',
                keywords: ['it support', 'technical support', 'wifi', 'wi fi', 'internet', 'password', 'reset password', 'login', 'log in', 'portal', 'student portal', 'account', 'email account', 'cannot access', 'cant access'],
                answer: "For portal login issues, use the 'Forgot Password' link on the student portal. If that fails, visit the IT Support office at the Admin building, ground floor, with a valid ID."
            },
            {
                type: 'faq',
                title: 'Tuition & Fees',
                department: 'Cashier / Finance',
                keywords: ['tuition', 'fees', 'fee', 'payment', 'cashier', 'assessment', 'balance', 'pay', 'payment deadline'],
                answer: 'Tuition assessment is generated after enrollment and can be viewed on the student portal. Payments are accepted at the Cashier window or through accredited payment partners; deadlines are posted each semester.'
            },
            {
                type: 'faq',
                title: 'Attendance Policy',
                department: 'Registrar',
                keywords: ['attendance', 'absent', 'late', 'excuse letter'],
                answer: 'Students who accumulate absences equal to 20% of total class hours in a subject may be dropped, per the Student Handbook. Notify your instructor and file an excuse letter for valid absences.'
            },
            {
                type: 'form',
                title: 'Course Shifting Form',
                department: 'Registrar',
                keywords: ['shifting form', 'shift program form', 'change course form'],
                answer: 'Here is the Course Shifting Form. Have it signed by your current and target program advisers before submitting to the Registrar.',
                fields: ['Full Name', 'Student ID', 'Current Program', 'Program To Shift Into', 'Reason For Shifting', "Adviser's Signature"]
            },
            {
                type: 'faq',
                title: 'Course Shifting',
                department: 'Registrar',
                keywords: ['shifting', 'shift program', 'change course'],
                answer: 'Course shifting requires a signed shifting form from both your current and target program advisers, submitted to the Registrar before the shifting deadline for the term.'
            },
            {
                type: 'faq',
                title: 'Greeting',
                department: null,
                keywords: ['hello', 'hi', 'hey', 'help', 'uv assist', 'university'],
                answer: "Hi! I'm Betsy, the UV-Assist campus helpdesk. Ask me about admissions, enrollment, registrar requests, scholarships, IT support, campus policies — or ask for a form directly."
            }

        ];

        const CONFIDENCE_THRESHOLD = 0.45;


        /*
        |--------------------------------------------------------------------------
        | MATCHING
        |--------------------------------------------------------------------------
        */

        function normalizeText(text) {
            return text
                .toLowerCase()
                .replace(/[^\p{L}\p{N}\s]/gu, ' ')
                .replace(/\s+/g, ' ')
                .trim();
        }

        function scoreEntry(normalizedMessage, entry) {
            const keywords = entry.keywords || [];
            if (!keywords.length) return 0;

            let hits = 0;
            let weight = 0;

            keywords.forEach(keyword => {
                const needle = normalizeText(keyword);
                if (needle && normalizedMessage.includes(needle)) {
                    hits++;
                    weight += needle.includes(' ') ? 2 : 1;
                }
            });

            if (hits === 0) return 0;

            const score = Math.min(0.99, (weight / Math.max(3, keywords.length)) + (hits > 1 ? 0.15 : 0));
            return Math.round(score * 10000) / 10000;
        }

        function findBestMatch(message) {
            const normalized = normalizeText(message);
            let best = null;
            let bestScore = 0;

            KNOWLEDGE_BASE.forEach(entry => {
                const score = scoreEntry(normalized, entry);
                if (score > bestScore) {
                    bestScore = score;
                    best = entry;
                }
            });

            return { entry: best, score: bestScore };
        }

        function slugify(text) {
            return text.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
        }


        /*
        |--------------------------------------------------------------------------
        | ELEMENTS
        |--------------------------------------------------------------------------
        */

        const betsy =
            document.getElementById('betsy');

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
                text = "Studying late? I'm Betsy — here if you need anything.";
            } else if (hour < 12) {
                text = "Good morning! I'm Betsy 👋";
            } else if (hour < 18) {
                text = "Good afternoon! I'm Betsy 👋";
            } else {
                text = "Good evening! I'm Betsy 👋";
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

        setTimeout(() => {
            betsy.classList.remove('greet');
        }, 1400);


        /*
        |--------------------------------------------------------------------------
        | EYE TRACKING
        |--------------------------------------------------------------------------
        */

        if (window.matchMedia('(pointer: fine)').matches) {

            let ticking = false;

            window.addEventListener('mousemove', (event) => {

                if (ticking) return;

                ticking = true;

                requestAnimationFrame(() => {

                    const rect = betsy.getBoundingClientRect();
                    const cx = rect.left + rect.width / 2;
                    const cy = rect.top + rect.height / 2;

                    const dx = Math.max(-1, Math.min(1, (event.clientX - cx) / 260));
                    const dy = Math.max(-1, Math.min(1, (event.clientY - cy) / 260));

                    const eyes = betsy.querySelectorAll('.betsy-eye');

                    eyes.forEach((eye) => {
                        eye.style.translate = `${dx * 4}px ${dy * 3}px`;
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
            messageInput.dispatchEvent(new Event('input'));
        }


        /*
        |--------------------------------------------------------------------------
        | MESSAGE RENDERING
        |--------------------------------------------------------------------------
        */

        function addMessage(message, sender = 'user') {

            const wrapper = document.createElement('div');

            wrapper.className =
                'message-animation flex gap-2 ' +
                (sender === 'user' ? 'justify-end' : 'justify-start');

            if (sender === 'ai') {
                wrapper.appendChild(makeMiniAvatar());
            }

            const bubble = document.createElement('div');

            bubble.className = sender === 'user'
                ? 'max-w-[80%] rounded-2xl rounded-br-md bg-emerald-900 px-4 py-3 text-sm leading-6 text-white'
                : 'max-w-[80%] rounded-2xl rounded-bl-md bg-white border border-slate-200 px-4 py-3 text-sm leading-6 text-slate-700 shadow-sm whitespace-pre-line';

            bubble.textContent = message;
            wrapper.appendChild(bubble);

            chatThread.appendChild(wrapper);
            chatThread.scrollTop = chatThread.scrollHeight;
        }

        function makeMiniAvatar() {
            const avatar = document.createElement('div');
            avatar.className = 'mini-betsy relative mt-1 h-7 w-7 shrink-0 rounded-full';
            avatar.innerHTML = `
                <span class="mini-eye absolute left-[7px] top-[11px] h-[6px] w-[4px] rounded-full bg-emerald-950"></span>
                <span class="mini-eye absolute right-[7px] top-[11px] h-[6px] w-[4px] rounded-full bg-emerald-950"></span>
            `;
            return avatar;
        }

        function addFormMessage(entry) {

            const wrapper = document.createElement('div');
            wrapper.className = 'message-animation flex flex-col gap-2 items-start';

            const row = document.createElement('div');
            row.className = 'flex gap-2 justify-start w-full';
            row.appendChild(makeMiniAvatar());

            const bubble = document.createElement('div');
            bubble.className = 'max-w-[80%] rounded-2xl rounded-bl-md bg-white border border-slate-200 px-4 py-3 text-sm leading-6 text-slate-700 shadow-sm';
            bubble.textContent = entry.answer;
            row.appendChild(bubble);
            wrapper.appendChild(row);

            const card = document.createElement('div');
            card.className = 'form-card ml-9';
            card.innerHTML = `
                <div class="form-card-title">${entry.title}</div>
                <div class="form-card-sub">${(entry.fields || []).length} fields to complete</div>
                <ul>${(entry.fields || []).map(f => `<li>${f}</li>`).join('')}</ul>
                <button type="button" class="form-dl-btn">⬇ Download form</button>
            `;

            card.querySelector('.form-dl-btn').addEventListener('click', () => downloadForm(entry));

            wrapper.appendChild(card);
            chatThread.appendChild(wrapper);
            chatThread.scrollTop = chatThread.scrollHeight;
        }

        function downloadForm(entry) {

            const lines = [
                'UNIVERSITY OF THE VISAYAS',
                entry.title,
                '------------------------------------------------',
                'Instructions: Fill out all fields below and submit to the concerned office.',
                ''
            ];

            (entry.fields || []).forEach(f => {
                lines.push(f + ':  ____________________________________');
                lines.push('');
            });

            const blob = new Blob([lines.join('\n')], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);

            const a = document.createElement('a');
            a.href = url;
            a.download = slugify(entry.title) + '.txt';
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
        }

        function showThinking() {

            const wrapper = document.createElement('div');
            wrapper.id = 'thinkingIndicator';
            wrapper.className = 'message-animation flex items-end gap-2 justify-start';

            wrapper.innerHTML = `
                <div class="mini-betsy relative mt-1 h-7 w-7 shrink-0 rounded-full"></div>

                <div class="flex items-center gap-1 rounded-2xl rounded-bl-md border border-slate-200 bg-white px-4 py-3 shadow-sm">
                    <span class="think-dot h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                    <span class="think-dot h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                    <span class="think-dot h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                </div>
            `;

            chatThread.appendChild(wrapper);
            chatThread.scrollTop = chatThread.scrollHeight;
        }

        function removeThinking() {
            const el = document.getElementById('thinkingIndicator');
            if (el) el.remove();
        }


        /*
        |--------------------------------------------------------------------------
        | START CHAT MODE
        |--------------------------------------------------------------------------
        */

        function enterChatMode() {
            if (chatStarted) return;
            chatStarted = true;
            document.body.classList.remove('mode-landing');
            document.body.classList.add('mode-chat');
        }


        /*
        |--------------------------------------------------------------------------
        | LISTENING STATE
        |--------------------------------------------------------------------------
        */

        messageInput.addEventListener('focus', () => setBetsyState('listening'));
        messageInput.addEventListener('blur', () => setBetsyState('idle'));


        /*
        |--------------------------------------------------------------------------
        | SEND MESSAGE — fully client-side, static knowledge base
        |--------------------------------------------------------------------------
        */

        chatForm.addEventListener('submit', function (event) {

            event.preventDefault();

            const message = messageInput.value.trim();
            if (!message) return;

            enterChatMode();
            addMessage(message, 'user');

            messageInput.value = '';
            messageInput.style.height = 'auto';

            sendButton.disabled = true;
            setBetsyState('thinking');
            showThinking();

            setTimeout(() => {

                removeThinking();

                const wantsHuman = /\b(real person|human|staff|agent)\b/i.test(message);
                const { entry, score } = findBestMatch(message);

                if (!wantsHuman && entry && score >= CONFIDENCE_THRESHOLD) {

                    if (entry.type === 'form') {
                        addFormMessage(entry);
                    } else {
                        addMessage(entry.answer, 'ai');
                    }

                } else {

                    const department = entry ? entry.department : null;

                    const reply = wantsHuman
                        ? "Sure — I've forwarded this to " + (department || 'the appropriate University office') + ". A staff member will follow up through your registered student email."
                        : "I'm not confident I have enough information in the UV-Assist knowledge base to answer that accurately.\n\nI've forwarded your question to " + (department || 'a University support office') + " for assistance.";

                    addMessage(reply, 'ai');
                }

                setBetsyState('happy');
                setTimeout(() => setBetsyState('idle'), 700);

                sendButton.disabled = false;
                messageInput.focus();

            }, 500 + Math.random() * 400);
        });


        /*
        |--------------------------------------------------------------------------
        | TEXTAREA AUTO RESIZE + ENTER TO SEND
        |--------------------------------------------------------------------------
        */

        messageInput.addEventListener('input', function () {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 128) + 'px';
        });

        messageInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                chatForm.requestSubmit();
            }
        });

    </script>


</body>

</html>