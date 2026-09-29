<?php
declare(strict_types=1);

namespace App\Infrastructure\Security;

use PDO;
use Exception;

class SSOHelper
{
    private static ?PDO $pdo = null;

    /**
     * Check central session cookie from theflyingdutchmen.games and sync login state.
     */
    public static function checkAndSyncSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $rawCookie = $_COOKIE['session_id'] ?? '';

        // If currently logged in via SSO in Tasks, verify central TFD session is still valid
        if (!empty($_SESSION['sso_tfd']) && !empty($_SESSION['user_id'])) {
            if (empty($rawCookie)) {
                self::clearSession();
                return;
            }
            $tfdUser = self::getTfdUserFromCookie($rawCookie);
            if (!$tfdUser) {
                self::clearSession();
                return;
            }
            $currentUsername = $_SESSION['user_name'] ?? '';
            if (!empty($tfdUser['username']) && strtolower($tfdUser['username']) !== strtolower($currentUsername)) {
                self::loginUserByEmailOrUsername($tfdUser['email'] ?? '', $tfdUser['username'] ?? '', $tfdUser['role'] ?? 'user');
            }
            return;
        }

        // If not logged in in Tasks, check if central TFD session cookie is present
        if (empty($_SESSION['user_id']) && !empty($rawCookie)) {
            $tfdUser = self::getTfdUserFromCookie($rawCookie);
            if ($tfdUser) {
                self::loginUserByEmailOrUsername($tfdUser['email'] ?? '', $tfdUser['username'] ?? '', $tfdUser['role'] ?? 'user');
            }
        }
    }

    private static function getPdo(): ?PDO
    {
        if (self::$pdo === null) {
            try {
                if (file_exists(__DIR__ . '/../../../config/env.php')) {
                    require_once __DIR__ . '/../../../config/env.php';
                }
                $host = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
                $dbname = defined('DB_NAME') ? DB_NAME : 'tfd_tasks';
                $user = defined('DB_USER') ? DB_USER : 'tfd_user';
                $pass = defined('DB_PASS') ? DB_PASS : 'TheFUNnel!';

                self::$pdo = new PDO(
                    "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
                    $user,
                    $pass,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                    ]
                );
            } catch (Exception $e) {
                return null;
            }
        }
        return self::$pdo;
    }

    /**
     * Decode session_id cookie and fetch user record from central TFD database.
     */
    public static function getTfdUserFromCookie(string $rawCookie): ?array
    {
        if (empty($rawCookie)) {
            return null;
        }

        $sessionId = $rawCookie;
        // Strip Express signed cookie prefix 's:' if present
        if (strpos($sessionId, 's:') === 0) {
            $sessionId = substr($sessionId, 2);
            $dotPos = strpos($sessionId, '.');
            if ($dotPos !== false) {
                $sessionId = substr($sessionId, 0, $dotPos);
            }
        }

        $pdo = self::getPdo();
        if (!$pdo) {
            return null;
        }

        try {
            $stmt = $pdo->prepare("SELECT data, expires FROM TFD.sessions WHERE session_id = ? AND expires > UNIX_TIMESTAMP()");
            $stmt->execute([$sessionId]);
            $row = $stmt->fetch();
            if (!$row || empty($row['data'])) {
                return null;
            }

            $sessData = json_decode($row['data'], true);
            $userId = $sessData['passport']['user'] ?? null;
            if (!$userId) {
                return null;
            }

            $uStmt = $pdo->prepare("SELECT id, username, email, role FROM TFD.users WHERE id = ?");
            $uStmt->execute([$userId]);
            return $uStmt->fetch() ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Locate or create a local Tasks user account and populate session state.
     */
    public static function loginUserByEmailOrUsername(string $email, ?string $username = null, string $tfdRole = 'user'): bool
    {
        $pdo = self::getPdo();
        if (!$pdo) {
            return false;
        }

        try {
            $user = null;
            if (!empty($email)) {
                $stmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
            }
            if (!$user && !empty($username)) {
                $stmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE name = ? LIMIT 1");
                $stmt->execute([$username]);
                $user = $stmt->fetch();
            }

            // Tasks is strictly restricted to administrators
            if (strtolower($tfdRole) !== 'admin') {
                return false;
            }

            $targetRole = 'super_admin';

            // Auto-provision user if not found
            if (!$user && (!empty($email) || !empty($username))) {
                $insName = !empty($username) ? $username : explode('@', $email)[0];
                $insEmail = !empty($email) ? $email : $insName . '@theflyingdutchmen.games';
                $dummyHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

                $insStmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)");
                $insStmt->execute([$insName, $insEmail, $dummyHash, $targetRole]);
                $newId = (int)$pdo->lastInsertId();

                $stmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
                $stmt->execute([$newId]);
                $user = $stmt->fetch();
            }

            if ($user) {
                // Keep super_admin sync updated if user is admin in central TFD
                if ($user['role'] !== $targetRole && strtolower($tfdRole) === 'admin') {
                    $upd = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                    $upd->execute([$targetRole, $user['id']]);
                    $user['role'] = $targetRole;
                }

                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['sso_tfd'] = true;
                $_SESSION['last_activity'] = time();

                return true;
            }
        } catch (Exception $e) {
            error_log("SSO login failed in tasks: " . $e->getMessage());
        }

        return false;
    }

    public static function getTfdDomain(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'tasks.theflyingdutchmen.games';
        return (strpos($host, 'theflyingdutchmen.com') !== false) ? 'theflyingdutchmen.com' : 'theflyingdutchmen.games';
    }

    public static function getCentralLoginUrl(): string
    {
        $tfdDomain = self::getTfdDomain();
        return "https://{$tfdDomain}/oauth/authorize?client_id=tasks-app-f807c6b8&response_type=code&redirect_uri=" . urlencode("https://tasks.{$tfdDomain}/auth_callback.php") . "&scope=openid%20profile%20email";
    }

    public static function clearSession(): void
    {
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
        @session_destroy();
    }
}
