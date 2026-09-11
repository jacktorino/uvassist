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
            animation: messageIn 0.2s ease-out;
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
    </style>

</head>


<body class="min-h-screen bg-slate-50 text-slate-900">


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
                    <span class="text-lg font-bold">UV</span>
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
| HERO
|--------------------------------------------------------------------------
-->

    <main>

        <section class="relative overflow-hidden bg-white">

            <div
                class="mx-auto grid max-w-7xl items-center gap-12 px-6 py-16 lg:grid-cols-2 lg:py-24">

                <div>

                    <div
                        class="mb-5 inline-flex items-center gap-2 rounded-full border border-emerald-100 bg-emerald-50 px-3 py-1.5 text-sm font-medium text-emerald-800">

                        <span
                            class="h-2 w-2 rounded-full bg-emerald-600"></span>

                        Campus Helpdesk

                    </div>


                    <h1
                        class="heading max-w-2xl text-4xl font-extrabold tracking-tight text-slate-950 sm:text-5xl lg:text-6xl">

                        Your campus questions,
                        <span class="text-emerald-800">
                            answered.
                        </span>

                    </h1>


                    <p
                        class="mt-6 max-w-xl text-lg leading-8 text-slate-600">

                        UV-Assist is the University of the Visayas intelligent
                        campus helpdesk. Ask questions about admissions,
                        enrollment, registrar services, student affairs,
                        IT support, and other university information.

                    </p>


                    <div class="mt-8 flex flex-wrap gap-3">

                        <button
                            type="button"
                            onclick="openChat()"
                            class="rounded-xl bg-emerald-900 px-6 py-3.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800">
                            Start a conversation
                        </button>

                        <a
                            href="#features"
                            class="rounded-xl border border-slate-300 bg-white px-6 py-3.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                            Learn more
                        </a>

                    </div>

                </div>


                <!--
            |--------------------------------------------------------------------------
            | HERO CHAT PREVIEW
            |--------------------------------------------------------------------------
            -->

                <div class="relative">

                    <div
                        class="mx-auto max-w-md overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl shadow-slate-200/60">

                        <div
                            class="bg-emerald-950 px-5 py-4 text-white">

                            <div class="flex items-center gap-3">

                                <div
                                    class="flex h-10 w-10 items-center justify-center rounded-full bg-white/10">
                                    💬
                                </div>

                                <div>

                                    <div class="font-semibold">
                                        UV-Assist
                                    </div>

                                    <div class="text-xs text-emerald-200">
                                        Online helpdesk
                                    </div>

                                </div>

                            </div>

                        </div>


                        <div class="space-y-4 bg-slate-50 p-5">

                            <div class="flex justify-start">

                                <div
                                    class="max-w-[85%] rounded-2xl rounded-tl-md bg-white px-4 py-3 text-sm text-slate-700 shadow-sm">
                                    Hello! I'm UV-Assist. How can I help you today?
                                </div>

                            </div>


                            <div class="flex justify-end">

                                <div
                                    class="max-w-[85%] rounded-2xl rounded-tr-md bg-emerald-900 px-4 py-3 text-sm text-white">
                                    What are the requirements for enrollment?
                                </div>

                            </div>


                            <div class="flex justify-start">

                                <div
                                    class="max-w-[85%] rounded-2xl rounded-tl-md bg-white px-4 py-3 text-sm text-slate-700 shadow-sm">
                                    I can help you find the enrollment requirements
                                    and direct you to the appropriate University office.
                                </div>

                            </div>

                        </div>


                        <div class="border-t border-slate-200 bg-white p-4">

                            <button
                                type="button"
                                onclick="openChat()"
                                class="w-full rounded-xl bg-emerald-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-emerald-800">
                                Ask UV-Assist
                            </button>

                        </div>

                    </div>

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

            <div class="mx-auto max-w-7xl px-6 py-20">

                <div class="mx-auto max-w-2xl text-center">

                    <p
                        class="text-sm font-semibold uppercase tracking-wider text-emerald-800">
                        UV-Assist
                    </p>

                    <h2
                        class="heading mt-2 text-3xl font-bold text-slate-950">
                        Campus help when you need it
                    </h2>

                    <p
                        class="mt-4 text-slate-600">
                        Get quick access to trusted university information
                        while keeping a path open to human assistance.
                    </p>

                </div>


                <div
                    class="mt-12 grid gap-6 md:grid-cols-2 lg:grid-cols-4">

                    <!-- Card -->

                    <div
                        class="rounded-2xl border border-slate-200 bg-white p-6">

                        <div
                            class="mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-xl">
                            🔎
                        </div>

                        <h3 class="heading font-bold">
                            Knowledge Search
                        </h3>

                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            Find relevant information from the University's
                            maintained knowledge base.
                        </p>

                    </div>


                    <!-- Card -->

                    <div
                        class="rounded-2xl border border-slate-200 bg-white p-6">

                        <div
                            class="mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-xl">
                            🤖
                        </div>

                        <h3 class="heading font-bold">
                            Intelligent Answers
                        </h3>

                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            UV-Assist uses intent classification and retrieval
                            to provide grounded responses.
                        </p>

                    </div>


                    <!-- Card -->

                    <div
                        class="rounded-2xl border border-slate-200 bg-white p-6">

                        <div
                            class="mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-xl">
                            👤
                        </div>

                        <h3 class="heading font-bold">
                            Human Escalation
                        </h3>

                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            Questions requiring staff assistance can be routed
                            to the appropriate University office.
                        </p>

                    </div>


                    <!-- Card -->

                    <div
                        class="rounded-2xl border border-slate-200 bg-white p-6">

                        <div
                            class="mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-xl">
                            📚
                        </div>

                        <h3 class="heading font-bold">
                            Trusted Sources
                        </h3>

                        <p class="mt-2 text-sm leading-6 text-slate-600">
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

        <section id="help" class="bg-white">

            <div class="mx-auto max-w-7xl px-6 py-20">

                <div class="grid gap-12 lg:grid-cols-2 lg:items-center">

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
                            UV-Assist first searches the University's maintained
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

                                <p class="mt-1 text-sm text-slate-600">
                                    Ask UV-Assist about campus services,
                                    procedures, requirements, or policies.
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
                                    UV-Assist retrieves information
                                </h3>

                                <p class="mt-1 text-sm text-slate-600">
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

                                <p class="mt-1 text-sm text-slate-600">
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

    <footer class="border-t border-slate-200 bg-slate-50">

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


    <!--
|--------------------------------------------------------------------------
| CHAT WINDOW
|--------------------------------------------------------------------------
-->

    <div
        id="chatOverlay"
        class="fixed inset-0 z-50 hidden bg-slate-950/30 backdrop-blur-sm">

        <div
            class="absolute bottom-0 right-0 h-[100dvh] w-full bg-white shadow-2xl sm:bottom-6 sm:right-6 sm:h-[720px] sm:max-h-[calc(100vh-48px)] sm:w-[420px] sm:rounded-3xl">

            <!-- Header -->

            <div
                class="flex items-center justify-between bg-emerald-950 px-5 py-4 text-white sm:rounded-t-3xl">

                <div class="flex items-center gap-3">

                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-white/10">
                        💬
                    </div>

                    <div>

                        <div class="font-semibold">
                            UV-Assist
                        </div>

                        <div class="flex items-center gap-1.5 text-xs text-emerald-200">

                            <span
                                class="h-2 w-2 rounded-full bg-emerald-400"></span>

                            Campus Helpdesk

                        </div>

                    </div>

                </div>


                <button
                    type="button"
                    onclick="closeChat()"
                    class="rounded-lg p-2 text-white/80 transition hover:bg-white/10 hover:text-white"
                    aria-label="Close chat">
                    ✕
                </button>

            </div>


            <!-- Messages -->

            <div
                id="chatMessages"
                class="chat-scroll h-[calc(100dvh-145px)] overflow-y-auto bg-slate-50 p-5 sm:h-[560px]">

                <div class="message-animation mb-4 flex justify-start">

                    <div
                        class="max-w-[85%] rounded-2xl rounded-tl-md bg-white px-4 py-3 text-sm leading-6 text-slate-700 shadow-sm">

                        <div>
                            Hello! 👋 I'm <strong>UV-Assist</strong>,
                            the University of the Visayas intelligent
                            campus helpdesk.
                        </div>

                        <div class="mt-2">
                            How can I help you today?
                        </div>

                    </div>

                </div>


                <!-- Suggested questions -->

                <div class="mt-5">

                    <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">
                        Common questions
                    </div>

                    <div class="flex flex-wrap gap-2">

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

                </div>

            </div>


            <!-- Input -->

            <div
                class="border-t border-slate-200 bg-white p-4 sm:rounded-b-3xl">

                <form
                    id="chatForm"
                    class="flex items-end gap-2">

                    <textarea
                        id="messageInput"
                        rows="1"
                        placeholder="Type your question..."
                        class="max-h-32 min-h-[46px] flex-1 resize-none rounded-xl border border-slate-300 bg-slate-50 px-4 py-3 text-sm outline-none transition placeholder:text-slate-400 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100"></textarea>


                    <button
                        type="submit"
                        id="sendButton"
                        class="flex h-[46px] w-[46px] shrink-0 items-center justify-center rounded-xl bg-emerald-900 text-white transition hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50"
                        aria-label="Send message">
                        ↑
                    </button>

                </form>


                <div class="mt-2 text-center text-[11px] text-slate-400">
                    UV-Assist may escalate questions to University staff when necessary.
                </div>

            </div>

        </div>

    </div>


    <script>
        /*
|--------------------------------------------------------------------------
| CHAT UI
|--------------------------------------------------------------------------
*/

        const chatOverlay = document.getElementById('chatOverlay');

        const chatMessages = document.getElementById('chatMessages');

        const messageInput = document.getElementById('messageInput');

        const chatForm = document.getElementById('chatForm');

        const sendButton = document.getElementById('sendButton');


        function openChat() {

            chatOverlay.classList.remove('hidden');

            document.body.classList.add('overflow-hidden');

            setTimeout(() => {
                messageInput.focus();
            }, 100);

        }


        function closeChat() {

            chatOverlay.classList.add('hidden');

            document.body.classList.remove('overflow-hidden');

        }


        function useSuggestion(message) {

            openChat();

            messageInput.value = message;

            messageInput.focus();

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

            const wrapper = document.createElement('div');

            wrapper.className =
                'message-animation mb-4 flex ' +
                (sender === 'user' ?
                    'justify-end' :
                    'justify-start');


            const bubble = document.createElement('div');

            if (sender === 'user') {

                bubble.className =
                    'max-w-[85%] rounded-2xl rounded-tr-md ' +
                    'bg-emerald-900 px-4 py-3 text-sm leading-6 text-white';

            } else {

                bubble.className =
                    'max-w-[85%] rounded-2xl rounded-tl-md ' +
                    'bg-white px-4 py-3 text-sm leading-6 text-slate-700 shadow-sm';

            }


            bubble.textContent = message;

            wrapper.appendChild(bubble);

            chatMessages.appendChild(wrapper);

            chatMessages.scrollTop = chatMessages.scrollHeight;

        }


        /*
        |--------------------------------------------------------------------------
        | TYPING INDICATOR
        |--------------------------------------------------------------------------
        */

        function showTyping() {

            const wrapper = document.createElement('div');

            wrapper.id = 'typingIndicator';

            wrapper.className =
                'mb-4 flex justify-start';


            wrapper.innerHTML = `
        <div class="rounded-2xl rounded-tl-md bg-white px-4 py-3 shadow-sm">
            <div class="flex items-center gap-1">
                <span class="h-1.5 w-1.5 rounded-full bg-slate-400 animate-bounce"></span>
                <span class="h-1.5 w-1.5 rounded-full bg-slate-400 animate-bounce [animation-delay:100ms]"></span>
                <span class="h-1.5 w-1.5 rounded-full bg-slate-400 animate-bounce [animation-delay:200ms]"></span>
            </div>
        </div>
    `;

            chatMessages.appendChild(wrapper);

            chatMessages.scrollTop = chatMessages.scrollHeight;

        }


        function removeTyping() {

            const typing =
                document.getElementById('typingIndicator');

            if (typing) {
                typing.remove();
            }

        }


        /*
        |--------------------------------------------------------------------------
        | SEND MESSAGE
        |--------------------------------------------------------------------------
        |
        | This currently connects to:
        |
        |     api/chat.php
        |
        | We will build that endpoint next.
        |
        */

        chatForm.addEventListener(
            'submit',
            async function(event) {

                event.preventDefault();

                const message =
                    messageInput.value.trim();

                if (!message) {
                    return;
                }


                addMessage(
                    message,
                    'user'
                );


                messageInput.value = '';

                messageInput.style.height = 'auto';

                sendButton.disabled = true;

                showTyping();


                try {

                    const response = await fetch(
                        'api/chat.php', {
                            method: 'POST',

                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            },

                            body: JSON.stringify({
                                message: message
                            })
                        }
                    );


                    const data =
                        await response.json();


                    removeTyping();


                    if (!response.ok || !data.success) {

                        throw new Error(
                            data.message ||
                            'Unable to process your request.'
                        );

                    }


                    addMessage(
                        data.message,
                        'ai'
                    );


                } catch (error) {

                    removeTyping();

                    addMessage(
                        'Sorry, I am unable to process your request right now. Please try again or request assistance from a University staff member.',
                        'ai'
                    );

                    console.error(
                        'UV-Assist error:',
                        error
                    );

                } finally {

                    sendButton.disabled = false;

                    messageInput.focus();

                }

            }
        );


        /*
        |--------------------------------------------------------------------------
        | TEXTAREA AUTO RESIZE
        |--------------------------------------------------------------------------
        */

        messageInput.addEventListener(
            'input',
            function() {

                this.style.height = 'auto';

                this.style.height =
                    Math.min(
                        this.scrollHeight,
                        128
                    ) + 'px';

            }
        );


        /*
        |--------------------------------------------------------------------------
        | ESC KEY
        |--------------------------------------------------------------------------
        */

        document.addEventListener(
            'keydown',
            function(event) {

                if (
                    event.key === 'Escape' &&
                    !chatOverlay.classList.contains('hidden')
                ) {

                    closeChat();

                }

            }
        );
    </script>


</body>

</html>