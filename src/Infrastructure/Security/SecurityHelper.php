<?php
declare(strict_types=1);

namespace App\Infrastructure\Security;

class SecurityHelper
{
    public static function initSession(): void
    {
        if (!headers_sent()) {
            header("X-Frame-Options: DENY");
            header("X-Content-Type-Options: nosniff");
            
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            if ($isHttps) {
                header("Strict-Transport-Security: max-age=63072000; includeSubDomains; preload");
            }
            
            header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-eval' 'unsafe-inline' cdn.tailwindcss.com cdnjs.cloudflare.com https://theflyingdutchmen.games; style-src 'self' 'unsafe-inline' fonts.googleapis.com cdn.jsdelivr.net https://theflyingdutchmen.games; font-src 'self' fonts.gstatic.com cdn.jsdelivr.net https://theflyingdutchmen.games; img-src 'self' data: blob: https:; connect-src 'self' cdnjs.cloudflare.com https://theflyingdutchmen.games https://*.theflyingdutchmen.games;");
        }

        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', '1');
            ini_set('session.use_only_cookies', '1');
            
            $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
 
            session_start();
        }

        $config = require __DIR__ . '/../../../config.php';
        $lifetime = $config['app']['session_lifetime'] ?? 1800;

        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $lifetime)) {
            self::destroySession();
            header('Location: ' . SSOHelper::getCentralLoginUrl());
            exit();
        }
        $_SESSION['last_activity'] = time();

        // Seamless Single Sign-On sync from theflyingdutchmen.games
        SSOHelper::checkAndSyncSession();
    }

    public static function destroySession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params["path"],
                    $params["domain"],
                    $params["secure"],
                    $params["httponly"]
                );
            }
            session_destroy();
        }
    }

    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public static function generateCsrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrfToken(?string $token): bool
    {
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public static function getCurrentUserId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    public static function getCurrentUserRole(): ?string
    {
        return $_SESSION['role'] ?? null;
    }

    public static function requireLogin(): void
    {
        self::initSession();
        if (!self::isLoggedIn()) {
            header('Location: ' . SSOHelper::getCentralLoginUrl());
            exit();
        }

        // Restrict Tasks access strictly to administrators
        if (self::getCurrentUserRole() !== 'super_admin') {
            http_response_code(403);
            $templatePath = __DIR__ . '/../../../templates/access_denied.php';
            if (file_exists($templatePath)) {
                require $templatePath;
            } else {
                echo '<!DOCTYPE html><html><head><title>403 Access Denied</title></head><body style="background:#0f172a;color:#f8fafc;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;"><div style="text-align:center;"><h1>403 - Access Denied</h1><p>The Task Manager is restricted to administrators only.</p><p><a href="https://theflyingdutchmen.games/" style="color:#818cf8;">Return to Home</a></p></div></body></html>';
            }
            exit();
        }
    }

    public static function requireSuperAdmin(): void
    {
        self::requireLogin();
        if (self::getCurrentUserRole() !== 'super_admin') {
            http_response_code(403);
            echo "Access Denied: You do not have permissions to access this page.";
            exit();
        }
    }
}
