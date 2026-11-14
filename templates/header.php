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
            --sidebar-rail-width: 3.5rem;
            --sidebar-expanded-width: 16rem;
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
            height: calc(100vh - var(--sidebar-top-offset, 48px)) !important;
            height: calc(100dvh - var(--sidebar-top-offset, 48px)) !important;
            overscroll-behavior: contain !important;
            z-index: 50 !important;
            transition: width 0.2s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.2s ease, transform 0.25s ease !important;
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

        /* Desktop: Stable Icon Rail (Collapsed by default, Expandable on click) */
        @media (min-width: 768px) {
            #tasks-sidebar {
                width: var(--sidebar-rail-width, 3.5rem) !important;
                transform: translateX(0) !important;
                overflow: hidden !important;
                transition: width 0.2s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.2s ease !important;
                box-shadow: 2px 0 8px rgba(0, 0, 0, 0.15) !important;
            }

            /* Content Margin follows rail width by default */
            #tasks-sidebar ~ .flex-1.min-w-0.flex.flex-col {
                margin-left: var(--sidebar-rail-width, 3.5rem) !important;
                transition: margin-left 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
            }

            /* --- COLLAPSED STATE (Default Rail: 3.5rem / 56px) --- */
            body:not(.sidebar-expanded):not(.sidebar-pinned) #tasks-sidebar {
                width: var(--sidebar-rail-width, 3.5rem) !important;
                border-right-color: rgba(51, 65, 85, 0.4) !important;
            }
            body:not(.sidebar-expanded):not(.sidebar-pinned) #tasks-sidebar .sidebar-header {
                border-bottom-color: transparent !important;
                padding: 0.625rem 0 !important;
                justify-content: center !important;
            }
            body:not(.sidebar-expanded):not(.sidebar-pinned) #tasks-sidebar .sidebar-brand-text,
            body:not(.sidebar-expanded):not(.sidebar-pinned) #tasks-sidebar .sidebar-item-label {
                display: none !important;
                opacity: 0 !important;
                visibility: hidden !important;
                width: 0 !important;
            }
            body:not(.sidebar-expanded):not(.sidebar-pinned) #tasks-sidebar .sidebar-brand-link {
                justify-content: center !important;
                width: 100% !important;
            }
            body:not(.sidebar-expanded):not(.sidebar-pinned) #tasks-sidebar .sidebar-logo-icon {
                width: 2rem !important;
                height: 2rem !important;
                border-radius: 0.5rem !important;
            }
            body:not(.sidebar-expanded):not(.sidebar-pinned) #tasks-sidebar nav {
                padding: 0.5rem 0.25rem !important;
            }
            body:not(.sidebar-expanded):not(.sidebar-pinned) #tasks-sidebar .sidebar-nav-item {
                justify-content: center !important;
                width: 2.25rem !important;
                height: 2.25rem !important;
                margin: 0.375rem auto !important;
                padding: 0 !important;
                border-radius: 0.625rem !important;
            }
            body:not(.sidebar-expanded):not(.sidebar-pinned) #tasks-sidebar .sidebar-item-icon {
                margin: 0 auto !important;
                font-size: 1.15rem !important;
            }
            body:not(.sidebar-expanded):not(.sidebar-pinned) #tasks-sidebar .sidebar-footer {
                display: block !important;
                border-top-color: transparent !important;
                padding: 0.5rem 0.25rem !important;
            }
            body:not(.sidebar-expanded):not(.sidebar-pinned) #tasks-sidebar .sidebar-footer #sidebar-pin-toggle {
                justify-content: center !important;
                width: 2.25rem !important;
                height: 2.25rem !important;
                margin: 0 auto !important;
                padding: 0 !important;
                border-radius: 0.625rem !important;
            }

            /* --- EXPANDED STATE (When toggled open: 16rem / 256px) --- */
            body.sidebar-expanded #tasks-sidebar,
            body.sidebar-pinned #tasks-sidebar {
                width: var(--sidebar-expanded-width, 16rem) !important;
                box-shadow: 6px 0 25px rgba(0, 0, 0, 0.45) !important;
            }
            body.sidebar-expanded #tasks-sidebar ~ .flex-1.min-w-0.flex.flex-col,
            body.sidebar-pinned #tasks-sidebar ~ .flex-1.min-w-0.flex.flex-col {
                margin-left: var(--sidebar-expanded-width, 16rem) !important;
            }
            body.sidebar-expanded #tasks-sidebar .sidebar-header,
            body.sidebar-pinned #tasks-sidebar .sidebar-header {
                border-bottom-color: #1e293b !important;
                padding: 0.75rem 0.875rem !important;
                justify-content: space-between !important;
            }
            body.sidebar-expanded #tasks-sidebar .sidebar-brand-link,
            body.sidebar-pinned #tasks-sidebar .sidebar-brand-link {
                justify-content: flex-start !important;
                width: auto !important;
            }
            body.sidebar-expanded #tasks-sidebar .sidebar-logo-icon,
            body.sidebar-pinned #tasks-sidebar .sidebar-logo-icon {
                width: 2.25rem !important;
                height: 2.25rem !important;
                border-radius: 0.75rem !important;
            }
            body.sidebar-expanded #tasks-sidebar .sidebar-brand-text,
            body.sidebar-pinned #tasks-sidebar .sidebar-brand-text {
                display: inline-block !important;
                opacity: 1 !important;
                visibility: visible !important;
            }
            body.sidebar-expanded #tasks-sidebar nav,
            body.sidebar-pinned #tasks-sidebar nav {
                padding: 0.5rem 0.75rem !important;
            }
            body.sidebar-expanded #tasks-sidebar .sidebar-nav-item,
            body.sidebar-pinned #tasks-sidebar .sidebar-nav-item {
                justify-content: flex-start !important;
                width: 100% !important;
                height: auto !important;
                padding: 0.625rem 0.75rem !important;
                margin: 0.25rem 0 !important;
                border-radius: 0.75rem !important;
            }
            body.sidebar-expanded #tasks-sidebar .sidebar-item-icon,
            body.sidebar-pinned #tasks-sidebar .sidebar-item-icon {
                margin: 0 !important;
                font-size: 1.15rem !important;
            }
            body.sidebar-expanded #tasks-sidebar .sidebar-item-label,
            body.sidebar-pinned #tasks-sidebar .sidebar-item-label {
                display: inline-block !important;
                opacity: 1 !important;
                visibility: visible !important;
            }
            body.sidebar-expanded #tasks-sidebar .sidebar-footer,
            body.sidebar-pinned #tasks-sidebar .sidebar-footer {
                display: block !important;
                border-top-color: #1e293b !important;
                padding: 0.5rem 0.75rem !important;
            }
            body.sidebar-expanded #tasks-sidebar .sidebar-footer #sidebar-pin-toggle,
            body.sidebar-pinned #tasks-sidebar .sidebar-footer #sidebar-pin-toggle {
                justify-content: flex-start !important;
                width: 100% !important;
                height: auto !important;
                padding: 0.5rem 0.75rem !important;
                margin: 0 !important;
                border-radius: 0.75rem !important;
            }
        }

        /* Mobile Styles */
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
            #tasks-sidebar ~ .flex-1.min-w-0.flex.flex-col {
                margin-left: 0 !important;
            }
        }
    </style>
    <!-- Tasks Universal Theme Integration Stylesheet -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>css/tasks-theme.css?v=<?php echo file_exists(__DIR__ . '/../css/tasks-theme.css') ? filemtime(__DIR__ . '/../css/tasks-theme.css') : '1'; ?>">
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col">
    <script>
        (function() {
            try {
                if (window.innerWidth >= 768 && (localStorage.getItem('tasks_sidebar_expanded') === 'true' || localStorage.getItem('tasks_sidebar_pinned') === 'true')) {
                    document.body.classList.add('sidebar-expanded', 'sidebar-pinned');
                }
            } catch(e) {}
        })();
    </script>

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
    <div class="flex flex-1 w-full">

        <!-- Tasks App Sidebar Menu (Icon Expand Rail on Desktop) -->
        <aside id="tasks-sidebar" class="fixed top-0 left-0 bg-slate-900/95 backdrop-blur-md border-r border-slate-800 flex flex-col z-50 md:z-30 transform -translate-x-full md:translate-x-0 transition-all duration-300 ease-in-out flex-shrink-0">
            
            <!-- Sidebar Header / Brand (Stats style: SWGGD Tasks & Game Studio) -->
            <div class="sidebar-header p-3 border-b border-slate-800 flex items-center justify-between flex-shrink-0" style="min-height: 56px;">
                <a href="<?php echo $base_url; ?>index.php" class="sidebar-brand-link flex items-center gap-3 group min-w-0 overflow-hidden" title="SWGGD Tasks & Game Studio">
                    <div class="sidebar-logo-icon w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-500 flex items-center justify-center text-white shadow-md shadow-indigo-500/20 group-hover:scale-105 transition-transform flex-shrink-0">
                        <i class="bi bi-kanban text-lg"></i>
                    </div>
                    <span class="sidebar-brand-text font-bold text-sm tracking-tight text-slate-100 whitespace-nowrap transition-opacity duration-200">
                        SWGGD Tasks & Game Studio
                    </span>
                </a>
                <button id="tasks-sidebar-close" type="button" class="md:hidden p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition flex-shrink-0" aria-label="Close menu">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <!-- Navigation Links (Streamlined) -->
            <nav class="flex-1 p-2 space-y-1.5 overflow-y-auto overflow-x-hidden" style="scrollbar-width: thin;">
                <!-- Dashboard -->
                <a href="<?php echo $base_url; ?>index.php" class="sidebar-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition duration-150 <?php echo $isDashboardActive ? 'bg-indigo-600/15 text-indigo-400 border border-indigo-500/30 font-semibold shadow-sm' : 'text-slate-300 hover:text-white hover:bg-slate-800/60 border border-transparent'; ?>" title="Dashboard">
                    <i class="sidebar-item-icon bi bi-speedometer2 text-lg flex-shrink-0 <?php echo $isDashboardActive ? 'text-indigo-400' : 'text-slate-400'; ?>"></i>
                    <span class="sidebar-item-label whitespace-nowrap">Dashboard</span>
                </a>

                <!-- Projects -->
                <a href="<?php echo $base_url; ?>projects.php" class="sidebar-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition duration-150 <?php echo $isProjectsActive ? 'bg-indigo-600/15 text-indigo-400 border border-indigo-500/30 font-semibold shadow-sm' : 'text-slate-300 hover:text-white hover:bg-slate-800/60 border border-transparent'; ?>" title="Projects">
                    <i class="sidebar-item-icon bi bi-folder2-open text-lg flex-shrink-0 <?php echo $isProjectsActive ? 'text-indigo-400' : 'text-slate-400'; ?>"></i>
                    <span class="sidebar-item-label whitespace-nowrap">Projects</span>
                </a>

                <!-- Archive -->
                <a href="<?php echo $base_url; ?>archive.php" class="sidebar-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition duration-150 <?php echo $isArchiveActive ? 'bg-indigo-600/15 text-indigo-400 border border-indigo-500/30 font-semibold shadow-sm' : 'text-slate-300 hover:text-white hover:bg-slate-800/60 border border-transparent'; ?>" title="Archive">
                    <i class="sidebar-item-icon bi bi-archive text-lg flex-shrink-0 <?php echo $isArchiveActive ? 'text-indigo-400' : 'text-slate-400'; ?>"></i>
                    <span class="sidebar-item-label whitespace-nowrap">Archive</span>
                </a>

                <!-- Game Studio -->
                <a href="<?php echo $base_url; ?>board-game-studio/index.php" class="sidebar-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition duration-150 <?php echo $in_studio ? 'bg-indigo-600/15 text-indigo-400 border border-indigo-500/30 font-semibold shadow-sm' : 'text-slate-300 hover:text-white hover:bg-slate-800/60 border border-transparent'; ?>" title="Game Studio">
                    <i class="sidebar-item-icon bi bi-dice-5 text-lg flex-shrink-0 <?php echo $in_studio ? 'text-indigo-400' : 'text-slate-400'; ?>"></i>
                    <span class="sidebar-item-label whitespace-nowrap">Game Studio</span>
                </a>

<<<<<<< HEAD
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
                        <a href="<?php echo $studioBase; ?>sandboxes.php<?php echo $projQuery; ?>" class="flex items-center gap-2.5 px-2.5 py-1.5 rounded-lg text-xs font-medium transition duration-150 <?php echo in_array($current_page, ['sandboxes.php', 'sandbox.php'], true) ? 'text-indigo-400 font-semibold bg-indigo-500/10' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/40'; ?>">
                            <i class="bi bi-joystick text-xs"></i>
                            <span>Sandbox</span>
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
=======
                <!-- Meetings -->
                <a href="<?php echo $base_url; ?>meetings.php" class="sidebar-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition duration-150 <?php echo $isMeetingsActive ? 'bg-indigo-600/15 text-indigo-400 border border-indigo-500/30 font-semibold shadow-sm' : 'text-slate-300 hover:text-white hover:bg-slate-800/60 border border-transparent'; ?>" title="Meetings">
                    <i class="sidebar-item-icon bi bi-calendar-event text-lg flex-shrink-0 <?php echo $isMeetingsActive ? 'text-indigo-400' : 'text-slate-400'; ?>"></i>
                    <span class="sidebar-item-label whitespace-nowrap">Meetings</span>
>>>>>>> UX+
                </a>

                <?php if (SecurityHelper::getCurrentUserRole() === 'super_admin'): ?>
                    <!-- Users -->
                    <a href="<?php echo $base_url; ?>users.php" class="sidebar-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition duration-150 <?php echo $isUsersActive ? 'bg-indigo-600/15 text-indigo-400 border border-indigo-500/30 font-semibold shadow-sm' : 'text-slate-300 hover:text-white hover:bg-slate-800/60 border border-transparent'; ?>" title="Users">
                        <i class="sidebar-item-icon bi bi-shield-lock text-lg flex-shrink-0 <?php echo $isUsersActive ? 'text-indigo-400' : 'text-slate-400'; ?>"></i>
                        <span class="sidebar-item-label whitespace-nowrap">Users</span>
                    </a>
                <?php endif; ?>
            </nav>

            <!-- Bottom Desktop Toggle Action (Expand / Collapse) -->
            <div class="sidebar-footer p-2 border-t border-slate-800/80 hidden md:block flex-shrink-0">
                <button type="button" id="sidebar-pin-toggle" class="sidebar-nav-item flex items-center gap-3 w-full px-3 py-2 rounded-xl text-slate-400 hover:text-slate-200 hover:bg-slate-800/60 transition text-xs font-medium" title="Expand menu">
                    <i class="sidebar-item-icon bi bi-chevron-double-right text-base flex-shrink-0"></i>
                    <span class="sidebar-item-label whitespace-nowrap">Expand menu</span>
                </button>
            </div>
        </aside>

        <!-- Mobile Sidebar Backdrop Overlay (translucent with no blur so page behind remains visible like Stats app) -->
        <div id="tasks-sidebar-overlay" class="fixed inset-0 bg-slate-900/50 z-40 hidden md:hidden transition-opacity duration-300"></div>

        <!-- Content Area Wrapper -->
        <div class="flex-1 min-w-0 flex flex-col bg-slate-950 md:ml-[3.5rem]">
            <main class="flex-grow w-full flex flex-col min-w-0">
<?php
if (!function_exists('renderPageHeader')) {
    /**
     * Renders a simple, single-row sticky-top header aligned with Stats app design.
     */
    function renderPageHeader(string $title, string|int $subtitle = '', string $actionsHtml = '', string $backUrl = ''): void {
        $displayTitle = htmlspecialchars(htmlspecialchars_decode($title, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        $displaySubtitle = $subtitle !== '' ? htmlspecialchars(htmlspecialchars_decode((string)$subtitle, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8') : '';
        ?>
        <header class="page-header-sticky">
            <div class="header-title-group">
                <button type="button" class="sidebar-toggle-inline md:hidden p-2 -ml-1 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition focus:outline-none flex-shrink-0" onclick="document.getElementById('tasks-sidebar-toggle')?.click()" aria-label="Toggle navigation menu">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <?php if ($backUrl): ?>
                    <a href="<?php echo htmlspecialchars($backUrl); ?>" class="text-slate-400 hover:text-white transition p-1.5 -ml-1 rounded-lg hover:bg-slate-800/60 flex-shrink-0" title="Go back">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    </a>
                <?php endif; ?>
                <div class="flex items-center gap-2.5 min-w-0">
                    <h1 class="truncate"><?php echo $displayTitle; ?></h1>
                    <?php if ($displaySubtitle !== ''): ?>
                        <span class="header-subtitle hidden sm:inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-800 text-slate-300 border border-slate-700/60 whitespace-nowrap">
                            <?php echo $displaySubtitle; ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($actionsHtml): ?>
                <div class="header-actions">
                    <?php echo $actionsHtml; ?>
                </div>
            <?php endif; ?>
        </header>
        <?php
    }
}
?>
