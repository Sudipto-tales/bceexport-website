<?php

/**
 * Router script for PHP's built-in server.
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');

$denied = ['/.env', '/.env.example', '/composer.json', '/composer.lock', '/server.php'];
$deniedPrefixes = ['/database/', '/config/', '/core/', '/app/', '/api/', '/tools/'];

foreach ($denied as $path) {
    if ($uri === $path) {
        http_response_code(403);
        exit('Forbidden');
    }
}

foreach ($deniedPrefixes as $prefix) {
    if (str_starts_with($uri, $prefix) && is_file(__DIR__ . $uri)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

$publicPath = __DIR__ . $uri;
if ($uri !== '/' && is_file($publicPath)) {
    return false;
}

$_GET['route'] = trim($uri, '/');

require __DIR__ . '/index.php';
