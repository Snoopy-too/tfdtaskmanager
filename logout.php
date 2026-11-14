<?php
declare(strict_types=1);

$container = require_once __DIR__ . '/src/bootstrap.php';

use App\Infrastructure\Security\SecurityHelper;

SecurityHelper::initSession();
$wasSso = !empty($_SESSION['sso_tfd']);
SecurityHelper::destroySession();

$host = $_SERVER['HTTP_HOST'] ?? 'tasks.theflyingdutchmen.games';
$tfdDomain = (strpos($host, 'theflyingdutchmen.com') !== false) ? 'theflyingdutchmen.com' : 'theflyingdutchmen.games';

if ($wasSso || !empty($_COOKIE['session_id'])) {
    $returnUrl = (strpos($host, 'dev.englishjones.com') !== false) ? "https://dev.englishjones.com/" : "https://tasks.{$tfdDomain}/";
    header("Location: https://{$tfdDomain}/logout?redirect=" . urlencode($returnUrl));
    exit();
}

if (strpos($host, 'dev.englishjones.com') !== false) {
    header("Location: https://dev.englishjones.com/");
} else {
    header("Location: https://{$tfdDomain}/");
}
exit();
