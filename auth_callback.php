<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

use App\Infrastructure\Security\SecurityHelper;
use App\Infrastructure\Security\SSOHelper;

SecurityHelper::initSession();

$code = $_GET['code'] ?? null;
$error = $_GET['error'] ?? null;

if ($error) {
    http_response_code(403);
    $authError = "Authentication failed: " . htmlspecialchars($error);
    require __DIR__ . '/templates/access_denied.php';
    exit();
}

if (!$code) {
    header("Location: " . SSOHelper::getCentralLoginUrl());
    exit();
}

$host = $_SERVER['HTTP_HOST'] ?? 'tasks.theflyingdutchmen.games';
$tfdDomain = (strpos($host, 'theflyingdutchmen.com') !== false) ? 'theflyingdutchmen.com' : 'theflyingdutchmen.games';

$clientId = 'tasks-app-f807c6b8';
$clientSecret = '72edd0162a97e41f699df52b35f94642cb6c4efdceb9713f73b05bb9d09b2489';
$redirectUri = 'https://tasks.' . $tfdDomain . '/auth_callback.php';
$tokenUrl = 'http://127.0.0.1:4000/oauth/token';
$userinfoUrl = 'http://127.0.0.1:4000/oauth/userinfo';

// Exchange code for token
$ch = curl_init($tokenUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Host: ' . $tfdDomain
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'grant_type' => 'authorization_code',
    'code' => $code,
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'redirect_uri' => $redirectUri
]));
$tokenResponse = curl_exec($ch);
curl_close($ch);

$tokenData = json_decode($tokenResponse, true);
$accessToken = $tokenData['access_token'] ?? null;

if (!$accessToken) {
    http_response_code(403);
    $authError = "Failed to retrieve access token from authorization server.";
    require __DIR__ . '/templates/access_denied.php';
    exit();
}

// Fetch user profile
$ch = curl_init($userinfoUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $accessToken,
    'Host: ' . $tfdDomain
]);
$userResponse = curl_exec($ch);
curl_close($ch);

$userData = json_decode($userResponse, true);
$email = $userData['email'] ?? null;
$username = $userData['username'] ?? $userData['preferred_username'] ?? null;
$tfdRole = $userData['role'] ?? 'user';

if (!$email && !$username) {
    http_response_code(403);
    $authError = "Failed to retrieve user identity from authorization server.";
    require __DIR__ . '/templates/access_denied.php';
    exit();
}

if (strtolower($tfdRole) !== 'admin') {
    http_response_code(403);
    $authError = "Access restricted: Task Manager is only available to administrators.";
    require __DIR__ . '/templates/access_denied.php';
    exit();
}

if (SSOHelper::loginUserByEmailOrUsername($email, $username, $tfdRole)) {
    header("Location: index.php");
    exit();
} else {
    http_response_code(403);
    $authError = "Failed to synchronize user account.";
    require __DIR__ . '/templates/access_denied.php';
    exit();
}
