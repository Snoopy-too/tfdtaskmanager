<?php
declare(strict_types=1);

$container = require_once __DIR__ . '/src/bootstrap.php';

use App\Infrastructure\Security\SecurityHelper;
use App\Application\Services\AuthService;
use App\Application\Exceptions\ValidationException;

SecurityHelper::initSession();

if (SecurityHelper::isLoggedIn()) {
    header('Location: index.php');
    exit();
}

if (!empty($_SESSION['error'])) {
    $error = (string)$_SESSION['error'];
    unset($_SESSION['error']);
} else {
    $error = '';
}
$csrfToken = SecurityHelper::generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!SecurityHelper::verifyCsrfToken($submittedToken)) {
        $error = 'Security check failed. Please try again.';
    } else {
        try {
            $authService = $container->get(AuthService::class);
            $user = $authService->login($email, $password);

            if ($user->getRole() !== 'super_admin') {
                $error = 'Access restricted: Task Manager is only available to administrators.';
            } else {
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user->getId();
                $_SESSION['user_name'] = $user->getName();
                $_SESSION['role'] = $user->getRole();

                header('Location: index.php');
                exit();
            }
        } catch (ValidationException $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - TFD Task Manager</title>
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
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://theflyingdutchmen.games/stylesheets/tfd-nav.css">
    <link rel="stylesheet" href="css/tasks-theme.css?v=<?php echo file_exists(__DIR__ . '/css/tasks-theme.css') ? filemtime(__DIR__ . '/css/tasks-theme.css') : '1'; ?>">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
    </style>
</head>
<body class="text-slate-100 min-h-screen flex flex-col">

    <!-- Shared Top Navigation Bar (Managed by tfd-navbar.js) -->
    <header id="tfd-navbar" class="tfd-navbar" data-active="tasks"></header>

    <main class="flex-grow flex items-center justify-center p-4">
        <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-extrabold bg-gradient-to-r from-indigo-400 to-violet-400 bg-clip-text text-transparent">
                TFD Task Manager
            </h1>
            <p class="text-slate-400 mt-2">Board Game Development Team Portal</p>
        </div>

        <div class="bg-slate-900/50 backdrop-blur-md border border-slate-800 p-8 rounded-2xl shadow-xl">
            <h2 class="text-xl font-semibold text-slate-200 mb-6">Sign In</h2>

            <?php if (isset($_GET['expired'])): ?>
                <div class="mb-4 p-3 bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm rounded-lg">
                    Your session has expired. Please sign in again.
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="mb-4 p-3 bg-rose-500/10 border border-rose-500/30 text-rose-400 text-sm rounded-lg">
                    <?php echo SecurityHelper::escape($error); ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" class="space-y-5">
                <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">

                <div>
                    <label for="email" class="block text-sm font-medium text-slate-300 mb-1.5">Email Address</label>
                    <input type="email" id="email" name="email" required 
                        class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-4 py-2.5 text-slate-100 placeholder-slate-500 transition duration-200 outline-none" 
                        placeholder="you@tfdtaskmgr.local">
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="password" class="block text-sm font-medium text-slate-300">Password</label>
                    </div>
                    <input type="password" id="password" name="password" required 
                        class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-4 py-2.5 text-slate-100 placeholder-slate-500 transition duration-200 outline-none" 
                        placeholder="••••••••">
                </div>

                <button type="submit" 
                    class="w-full bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white font-medium py-2.5 rounded-lg transition duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-slate-900 mt-2">
                    Sign In
                </button>
            </form>

            <div class="relative my-6">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-slate-800"></div>
                </div>
                <div class="relative flex justify-center text-sm">
                    <span class="px-2 bg-slate-900 text-slate-400">or</span>
                </div>
            </div>

            <?php
            $host = $_SERVER['HTTP_HOST'] ?? 'tasks.theflyingdutchmen.games';
            $tfdDomain = (strpos($host, 'theflyingdutchmen.com') !== false) ? 'theflyingdutchmen.com' : 'theflyingdutchmen.games';
            $oauthUrl = "https://{$tfdDomain}/oauth/authorize?client_id=tasks-app-f807c6b8&response_type=code&redirect_uri=https://tasks.{$tfdDomain}/auth_callback.php&scope=openid%20profile%20email";
            ?>
            <a href="<?php echo htmlspecialchars($oauthUrl); ?>" 
                class="w-full inline-flex items-center justify-center gap-2 bg-slate-800 hover:bg-slate-700 active:bg-slate-600 text-white font-medium py-2.5 rounded-lg border border-slate-700 transition duration-200 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2 focus:ring-offset-slate-900">
                <span>🎮</span> Sign in with The Flying Dutchmen
            </a>
        </div>
    </div>
    </main>

    <script src="https://theflyingdutchmen.games/javascripts/tfd-navbar.js"></script>
</body>
</html>
