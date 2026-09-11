<?php
require_once __DIR__ . '/../admin_config.php';
require_admin();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= admin_e($pageTitle ?? 'Admin') ?> — UV-ASSIST</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'ui-sans-serif', 'system-ui']
                    }
                }
            }
        };
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="bg-[#f5f7f6] font-sans text-slate-900">
<div class="min-h-screen">
<?php require __DIR__ . '/sidebar.php'; ?>
<div class="lg:pl-72">
<?php require __DIR__ . '/header.php'; ?>
<main class="p-4 sm:p-6 lg:p-8">
