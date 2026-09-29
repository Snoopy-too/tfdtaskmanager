<?php
declare(strict_types=1);

use App\Infrastructure\Security\SecurityHelper;

SecurityHelper::initSession();

$current_page = basename($_SERVER['PHP_SELF']);
$in_studio = basename(dirname($_SERVER['PHP_SELF'])) === 'board-game-studio';
$base_url = $in_studio ? '../' : '';
$studioBase = $in_studio ? '' : 'board-game-studio/';
$projQuery = !empty($_GET['project_id']) ? '?project_id=' . urlencode((string)$_GET['project_id']) : '';

$isDashboardActive = !$in_studio && in_array($current_page, ['index.php', 'task_detail.php', 'add_task.php', 'edit_task.php'], true);
$isProjectsActive = !$in_studio && ($current_page === 'projects.php');
$isArchiveActive = !$in_studio && ($current_page === 'archive.php');
$isMeetingsActive = !$in_studio && in_array($current_page, ['meetings.php', 'meeting_detail.php'], true);
$isUsersActive = !$in_studio && ($current_page === 'users.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Manager - Board Game Dev</title>
    <meta name="csrf-token" content="<?php echo SecurityHelper::escape(SecurityHelper::generateCsrfToken()); ?>">
    <!-- Early Universal Theme Initializer to prevent theme flash -->
    <script>
    (function() {
        try {
            var cookieMatch = document.cookie.match(/(?:^|;\s*)tfd_theme=([^;]+)/);
            var pref = cookieMatch ? decodeURIComponent(cookieMatch[1]) : (localStorage.getItem('tfd-theme-preference') || localStorage.getItem('tfd-theme') || 'auto');
            var theme = pref;
            if (pref === 'auto' || !pref) {
                theme = (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) ? 'light' : 'arcade';
            }
            if (theme === 'dark') theme = 'arcade';
            document.documentElement.setAttribute('data-theme', theme);
        } catch(e) {}
    })();
    </script>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        darkbg: '#090d16',
                        cardbg: '#131b2e',
                    }
                }
            }
        }
    </script>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .stamp-finished {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-family: 'Courier New', Courier, 'Lucida Console', monospace;
            font-weight: 900;
            font-size: 0.75rem;
            line-height: 1.1;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: #ef4444;
            border: 2px solid #ef4444;
            outline: 1.5px solid rgba(239, 68, 68, 0.55);
            outline-offset: 1.5px;
            border-radius: 4px;
            padding: 3px 8px 3px 10px;
            background: rgba(239, 68, 68, 0.08);
            box-shadow: 0 0 10px rgba(239, 68, 68, 0.2), inset 0 0 6px rgba(239, 68, 68, 0.15);
            text-shadow: 0 0 1px rgba(239, 68, 68, 0.8);
            transform: rotate(-9deg);
            transform-origin: center;
            user-select: none;
            transition: transform 0.2s ease, filter 0.2s ease;
            filter: drop-shadow(0 2px 4px rgba(239, 68, 68, 0.2));
        }
        .stamp-finished:hover {
            transform: rotate(-6deg) scale(1.06);
        }
        #tasks-sidebar nav::-webkit-scrollbar {
            width: 4px;
        }
        #tasks-sidebar nav::-webkit-scrollbar-track {
            background: transparent;
        }
        #tasks-sidebar nav::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, 0.2);
            border-radius: 4px;
        }
        #tasks-sidebar nav::-webkit-scrollbar-thumb:hover {
            background: rgba(148, 163, 184, 0.4);
        }
    </style>
    <!-- TFD Universal Navigation Stylesheet -->
    <link rel="stylesheet" href="https://theflyingdutchmen.games/stylesheets/tfd-nav.css">
    <style>
        :root {
            --sidebar-top-offset: var(--tfd-navbar-height, 48px);
        }
        header#tfd-navbar, header#tfd-navbar.tfd-navbar, html body header#tfd-navbar, html body #tfd-navbar, html body .tfd-navbar, :root header#tfd-navbar, #tfd-navbar, .tfd-navbar {
            position: relative !important;
            top: auto !important;
            z-index: 99999 !important;
        }
        #tasks-sidebar-toggle {
            top: calc(var(--sidebar-top-offset, 48px) + 8px) !important;
        }
        #tasks-sidebar {
            position: fixed !important;
            top: var(--sidebar-top-offset, 48px) !important;
            left: 0 !important;
            width: 16rem !important;
            height: calc(100vh - var(--sidebar-top-offset, 48px)) !important;
            height: calc(100dvh - var(--sidebar-top-offset, 48px)) !important;
            overscroll-behavior: contain !important;
        }
        #tasks-sidebar nav {
            overscroll-behavior: contain !important;
            -webkit-overflow-scrolling: touch !important;
        }
        html.sidebar-open, body.sidebar-open {
            overflow: hidden !important;
            touch-action: none !important;
            overscroll-behavior: none !important;
        }
        @media (min-width: 768px) {
            #tasks-sidebar ~ .flex-1.min-w-0.flex.flex-col {
                margin-left: 16rem !important;
            }
        }
        @media (max-width: 767.98px) {
            #tasks-sidebar {
                top: 0 !important;
                height: 100vh !important;
                height: 100dvh !important;
                width: min(280px, calc(100vw - 56px)) !important;
                max-width: calc(100vw - 56px) !important;
                box-shadow: 4px 0 20px rgba(0, 0, 0, 0.4) !important;
            }
            #tasks-sidebar nav {
                touch-action: pan-y !important;
            }
        }
    </style>
    <!-- Tasks Universal Theme Integration Stylesheet -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>css/tasks-theme.css?v=<?php echo file_exists(__DIR__ . '/../css/tasks-theme.css') ? filemtime(__DIR__ . '/../css/tasks-theme.css') : '1'; ?>">
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col">

    <!-- Universal TFD Top Navigation Bar (Shared across all TFD apps) -->
    <header id="tfd-navbar" class="tfd-navbar" data-active="tasks" data-admin="<?php echo (SecurityHelper::getCurrentUserRole() === 'super_admin') ? 'true' : 'false'; ?>"></header>

    <!-- Mobile Floating Sidebar Toggle Button (similar to Stats app) -->
    <button id="tasks-sidebar-toggle" class="sidebar-toggle md:hidden fixed left-3 z-40 w-10 h-10 rounded-xl bg-slate-900/80 border border-slate-700/60 text-slate-200 flex items-center justify-center shadow-lg backdrop-blur-md hover:bg-slate-800 transition-all duration-200" aria-label="Toggle navigation menu" aria-controls="tasks-sidebar" aria-expanded="false">
        <span class="w-5 h-5 flex flex-col justify-center gap-1">
            <span class="w-full h-0.5 bg-current rounded-full"></span>
            <span class="w-full h-0.5 bg-current rounded-full"></span>
            <span class="w-full h-0.5 bg-current rounded-full"></span>
        </span>
    </button>

    <!-- Main Application Layout Container -->
    <div class="flex flex-1 min-h-[calc(100vh-48px)] w-full">

        <!-- Tasks App Sidebar Menu -->
        <aside id="tasks-sidebar" class="fixed top-0 left-0 w-64 bg-slate-900/95 backdrop-blur-md border-r border-slate-800 flex flex-col z-50 md:z-30 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out flex-shrink-0">
            
            <!-- Sidebar Header / Brand -->
            <div class="p-4 border-b border-slate-800 flex items-center justify-between">
                <a href="<?php echo $base_url; ?>index.php" class="flex items-center space-x-3 group">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-500 flex items-center justify-center text-white shadow-lg shadow-indigo-500/20 group-hover:scale-105 transition-transform">
                        <i class="bi bi-kanban text-lg"></i>
                    </div>
                    <div>
                        <div class="text-sm font-bold bg-gradient-to-r from-indigo-400 via-purple-300 to-violet-400 bg-clip-text text-transparent">
                            TFD - SWGGD
                        </div>
                        <div class="text-[11px] text-slate-400 font-medium tracking-wide">Tasks & Game Studio</div>
                    </div>
                </a>
                <button id="tasks-sidebar-close" type="button" class="md:hidden p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition" aria-label="Close menu">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <!-- Navigation Links -->
            <nav class="flex-1 p-3 space-y-1.5 overflow-y-auto" style="scrollbar-width: thin;">
                <!-- Dashboard -->
                <a href="<?php echo $base_url; ?>index.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition duration-150 <?php echo $isDashboardActive ? 'bg-indigo-600/15 text-indigo-400 border border-indigo-500/30 font-semibold shadow-sm' : 'text-slate-300 hover:text-white hover:bg-slate-800/60 border border-transparent'; ?>">
                    <i class="bi bi-speedometer2 text-base <?php echo $isDashboardActive ? 'text-indigo-400' : 'text-slate-400'; ?>"></i>
                    <span>Dashboard</span>
                </a>

                <!-- Projects -->
                <a href="<?php echo $base_url; ?>projects.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition duration-150 <?php echo $isProjectsActive ? 'bg-indigo-600/15 text-indigo-400 border border-indigo-500/30 font-semibold shadow-sm' : 'text-slate-300 hover:text-white hover:bg-slate-800/60 border border-transparent'; ?>">
                    <i class="bi bi-folder2-open text-base <?php echo $isProjectsActive ? 'text-indigo-400' : 'text-slate-400'; ?>"></i>
                    <span>Projects</span>
                </a>

                <!-- Archive -->
                <a href="<?php echo $base_url; ?>archive.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition duration-150 <?php echo $isArchiveActive ? 'bg-indigo-600/15 text-indigo-400 border border-indigo-500/30 font-semibold shadow-sm' : 'text-slate-300 hover:text-white hover:bg-slate-800/60 border border-transparent'; ?>">
                    <i class="bi bi-archive text-base <?php echo $isArchiveActive ? 'text-indigo-400' : 'text-slate-400'; ?>"></i>
                    <span>Archive</span>
                </a>

                <!-- Board Game Studio -->
                <a href="<?php echo $base_url; ?>board-game-studio/index.php<?php echo $projQuery; ?>" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition duration-150 <?php echo $in_studio ? 'bg-indigo-600/15 text-indigo-400 border border-indigo-500/30 font-semibold shadow-sm' : 'text-slate-300 hover:text-white hover:bg-slate-800/60 border border-transparent'; ?>">
                    <i class="bi bi-dice-5 text-base <?php echo $in_studio ? 'text-indigo-400' : 'text-slate-400'; ?>"></i>
                    <span>Board Game Studio</span>
                </a>

                <?php if ($in_studio): ?>
                    <div class="ml-4 pl-3 border-l border-slate-800/80 space-y-1 my-1">
                        <a href="<?php echo $studioBase; ?>index.php<?php echo $projQuery; ?>" class="flex items-center gap-2.5 px-2.5 py-1.5 rounded-lg text-xs font-medium transition duration-150 <?php echo in_array($current_page, ['index.php', 'editor.php'], true) ? 'text-indigo-400 font-semibold bg-indigo-500/10' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/40'; ?>">
                            <i class="bi bi-grid text-xs"></i>
                            <span>Components</span>
                        </a>
                        <a href="<?php echo $studioBase; ?>assets.php<?php echo $projQuery; ?>" class="flex items-center gap-2.5 px-2.5 py-1.5 rounded-lg text-xs font-medium transition duration-150 <?php echo ($current_page === 'assets.php') ? 'text-indigo-400 font-semibold bg-indigo-500/10' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/40'; ?>">
                            <i class="bi bi-images text-xs"></i>
                            <span>Assets</span>
                        </a>
                        <a href="<?php echo $studioBase; ?>datasets.php<?php echo $projQuery; ?>" class="flex items-center gap-2.5 px-2.5 py-1.5 rounded-lg text-xs font-medium transition duration-150 <?php echo ($current_page === 'datasets.php') ? 'text-indigo-400 font-semibold bg-indigo-500/10' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/40'; ?>">
                            <i class="bi bi-table text-xs"></i>
                            <span>Datasets</span>
                        </a>
                        <a href="<?php echo $studioBase; ?>rulebooks.php<?php echo $projQuery; ?>" class="flex items-center gap-2.5 px-2.5 py-1.5 rounded-lg text-xs font-medium transition duration-150 <?php echo in_array($current_page, ['rulebooks.php', 'rulebook-editor.php'], true) ? 'text-indigo-400 font-semibold bg-indigo-500/10' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/40'; ?>">
                            <i class="bi bi-book text-xs"></i>
                            <span>Rulebooks</span>
                        </a>
                        <a href="<?php echo $studioBase; ?>export.php<?php echo $projQuery; ?>" class="flex items-center gap-2.5 px-2.5 py-1.5 rounded-lg text-xs font-medium transition duration-150 <?php echo ($current_page === 'export.php') ? 'text-indigo-400 font-semibold bg-indigo-500/10' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/40'; ?>">
                            <i class="bi bi-printer text-xs"></i>
                            <span>Export Sheets</span>
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Meetings (Div/Dev) -->
                <a href="<?php echo $base_url; ?>meetings.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition duration-150 <?php echo $isMeetingsActive ? 'bg-indigo-600/15 text-indigo-400 border border-indigo-500/30 font-semibold shadow-sm' : 'text-slate-300 hover:text-white hover:bg-slate-800/60 border border-transparent'; ?>">
                    <i class="bi bi-calendar-event text-base <?php echo $isMeetingsActive ? 'text-indigo-400' : 'text-slate-400'; ?>"></i>
                    <span>Div / Dev Meetings</span>
                </a>

                <?php if (SecurityHelper::getCurrentUserRole() === 'super_admin'): ?>
                    <!-- User Management -->
                    <a href="<?php echo $base_url; ?>users.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition duration-150 <?php echo $isUsersActive ? 'bg-indigo-600/15 text-indigo-400 border border-indigo-500/30 font-semibold shadow-sm' : 'text-slate-300 hover:text-white hover:bg-slate-800/60 border border-transparent'; ?>">
                        <i class="bi bi-shield-lock text-base <?php echo $isUsersActive ? 'text-indigo-400' : 'text-slate-400'; ?>"></i>
                        <span>User Management</span>
                    </a>
                <?php endif; ?>
            </nav>
        </aside>

        <!-- Mobile Sidebar Backdrop Overlay (translucent with no blur so page behind remains visible like Stats app) -->
        <div id="tasks-sidebar-overlay" class="fixed inset-0 bg-slate-900/50 z-40 hidden md:hidden transition-opacity duration-300"></div>

        <!-- Content Area Wrapper -->
        <div class="flex-1 min-w-0 flex flex-col bg-slate-950 md:ml-64">
            <main class="flex-grow w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
