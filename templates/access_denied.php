<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied - TFD Task Manager</title>
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
    <link rel="stylesheet" href="https://theflyingdutchmen.games/stylesheets/tfd-nav.css">
    <link rel="stylesheet" href="css/tasks-theme.css?v=<?php echo file_exists(__DIR__ . '/../css/tasks-theme.css') ? filemtime(__DIR__ . '/../css/tasks-theme.css') : '1'; ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col">

    <!-- Shared Top Navigation Bar -->
    <header id="tfd-navbar" class="tfd-navbar" data-active="tasks"></header>

    <main class="flex-grow flex items-center justify-center p-4">
        <div class="w-full max-w-md text-center bg-slate-900/60 border border-slate-800 backdrop-blur-md rounded-2xl p-8 shadow-2xl">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-rose-500/10 flex items-center justify-center text-rose-400 text-3xl border border-rose-500/20">
                <i class="bi bi-shield-lock"></i>
            </div>
            
            <h1 class="text-2xl font-bold text-white mb-2">Access Restricted</h1>
            <p class="text-slate-400 text-sm mb-6">
                The Task Manager and Development Studio is reserved exclusively for The Flying Dutchmen administrators.
            </p>

            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <a href="https://theflyingdutchmen.games/" class="px-5 py-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium text-sm transition">
                    Return to Home
                </a>
                <a href="logout.php" class="px-5 py-2.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm font-medium transition border border-slate-700">
                    Switch Account
                </a>
            </div>
        </div>
    </main>

    <script src="https://theflyingdutchmen.games/javascripts/tfd-navbar.js"></script>
</body>
</html>
