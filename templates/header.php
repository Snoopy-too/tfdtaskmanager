<?php
declare(strict_types=1);

use App\Infrastructure\Security\SecurityHelper;

SecurityHelper::initSession();

$current_page = basename($_SERVER['PHP_SELF']);
$in_studio = basename(dirname($_SERVER['PHP_SELF'])) === 'board-game-studio';
$base_url = $in_studio ? '../' : '';

function isActive(string $page, string $current_page, bool $in_studio, bool $check_studio = false): string {
    if ($check_studio) {
        return $in_studio ? 'text-indigo-400 border-b-2 border-indigo-400' : 'text-slate-300 hover:text-white transition duration-200';
    }
    return (!$in_studio && $page === $current_page) ? 'text-indigo-400 border-b-2 border-indigo-400' : 'text-slate-300 hover:text-white transition duration-200';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Manager - Board Game Dev</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        darkbg: '#0f172a',
                        cardbg: '#1e293b',
                    }
                }
            }
        }
    </script>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
    </style>
</head>
<body class="bg-darkbg text-slate-100 min-h-screen flex flex-col">

    <header class="bg-slate-900/80 backdrop-blur-md border-b border-slate-800 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Logo -->
                <div class="flex items-center">
                    <a href="<?php echo $base_url; ?>index.php" class="flex items-center space-x-2">
                        <span class="text-xl font-bold bg-gradient-to-r from-indigo-400 to-violet-400 bg-clip-text text-transparent">TFD - SWGGD</span>
                    </a>
                </div>

                <!-- Navigation Links -->
                <?php if (SecurityHelper::isLoggedIn()): ?>
                    <nav class="hidden md:flex space-x-8">
                        <a href="<?php echo $base_url; ?>index.php" class="px-1 py-2 text-sm font-medium <?php echo isActive('index.php', $current_page, $in_studio, false); ?>">Dashboard</a>
                        <a href="<?php echo $base_url; ?>projects.php" class="px-1 py-2 text-sm font-medium <?php echo isActive('projects.php', $current_page, $in_studio, false); ?>">Projects</a>
                        <a href="<?php echo $base_url; ?>board-game-studio/index.php" class="px-1 py-2 text-sm font-medium <?php echo isActive('', $current_page, $in_studio, true); ?>">Board Game Studio</a>
                        <a href="<?php echo $base_url; ?>meetings.php" class="px-1 py-2 text-sm font-medium <?php echo (!$in_studio && ($current_page === 'meetings.php' || $current_page === 'meeting_detail.php')) ? 'text-indigo-400 border-b-2 border-indigo-400' : 'text-slate-300 hover:text-white transition duration-200'; ?>">Div/Dev</a>
                        <?php if (SecurityHelper::getCurrentUserRole() === 'super_admin'): ?>
                            <a href="<?php echo $base_url; ?>users.php" class="px-1 py-2 text-sm font-medium <?php echo isActive('users.php', $current_page, $in_studio, false); ?>">User Management</a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>

                <!-- User profile / Logout -->
                <div class="flex items-center space-x-4">
                    <?php if (SecurityHelper::isLoggedIn()): ?>
                        <div class="hidden lg:flex flex-col text-right">
                            <span class="text-sm font-semibold text-slate-200"><?php echo SecurityHelper::escape($_SESSION['user_name'] ?? ''); ?></span>
                            <span class="text-xs text-indigo-400 uppercase tracking-wider font-medium"><?php echo SecurityHelper::escape(str_replace('_', ' ', $_SESSION['role'] ?? '')); ?></span>
                        </div>
                        <a href="<?php echo $base_url; ?>logout.php" class="inline-flex items-center px-4 py-2 border border-slate-700 text-sm font-medium rounded-lg text-slate-300 hover:text-white hover:border-slate-500 hover:bg-slate-800 transition duration-200">
                            Logout
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <main class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
