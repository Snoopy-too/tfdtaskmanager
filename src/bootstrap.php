<?php
declare(strict_types=1);

// Set default timezone to Japan Standard Time (JST)
date_default_timezone_set('Asia/Tokyo');

require_once __DIR__ . '/autoloader.php';

$config = require __DIR__ . '/../config.php';

use App\Container\DIContainer;

return new DIContainer($config);
