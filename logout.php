<?php
declare(strict_types=1);

$container = require_once __DIR__ . '/src/bootstrap.php';

use App\Infrastructure\Security\SecurityHelper;

SecurityHelper::initSession();
SecurityHelper::destroySession();

header('Location: login.php');
exit();
