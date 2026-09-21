<?php
/**
 * Built-in PHP Development Server Router for Tasks
 */

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$path = rawurldecode($uri);

// If the requested resource exists as a file, serve it directly
if ($path !== '/' && is_file(__DIR__ . $path)) {
    return false;
}
if ($uri !== '/' && is_file(__DIR__ . $uri)) {
    return false;
}

// If the requested resource exists as a directory with an index.php, serve it directly
if ($path !== '/' && is_dir(__DIR__ . $path) && is_file(__DIR__ . $path . '/index.php')) {
    return false;
}

// Root request serves index.php
if ($path === '/' || $path === '/index.php' || $path === '') {
    if (is_file(__DIR__ . '/index.php')) {
        require __DIR__ . '/index.php';
        exit;
    }
}

http_response_code(404);
return false;
