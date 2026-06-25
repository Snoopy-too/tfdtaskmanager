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

$error = '';
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

            session_regenerate_id(true);

            $_SESSION['user_id'] = $user->getId();
            $_SESSION['user_name'] = $user->getName();
            $_SESSION['role'] = $user->getRole();

            header('Location: index.php');
            exit();
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
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #0f172a;
        }
    </style>
</head>
<body class="text-slate-100 min-h-screen flex items-center justify-center p-4">

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
        </div>
        
        <div class="text-center mt-6 text-xs text-slate-500">
            <p>Seeded Credentials: admin@tfdtaskmgr.local / AdminPass123!</p>
        </div>
    </div>

</body>
</html>
