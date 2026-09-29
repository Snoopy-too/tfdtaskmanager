<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

use App\Infrastructure\Security\SecurityHelper;
use App\Infrastructure\Security\SSOHelper;

SecurityHelper::initSession();

if (SecurityHelper::isLoggedIn()) {
    header('Location: index.php');
    exit();
}

// All authentication is handled centrally by the top level domain.
// Redirect immediately to central OAuth login.
header('Location: ' . SSOHelper::getCentralLoginUrl());
exit();

