<?php
declare(strict_types=1);

require_once __DIR__ . '/autoloader.php';

$config = require __DIR__ . '/../config.php';

use App\Container\DIContainer;

return new DIContainer($config);
