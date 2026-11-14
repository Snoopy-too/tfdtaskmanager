<?php
declare(strict_types=1);

// Set default timezone to Japan Standard Time (JST)
date_default_timezone_set('Asia/Tokyo');

require_once __DIR__ . '/autoloader.php';

use App\Container\DIContainer;

static $containerInstance = null;
if ($containerInstance === null) {
    $config = require __DIR__ . '/../config.php';
    $containerInstance = new DIContainer($config);
}

return $containerInstance;
