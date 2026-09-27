<?php

/**
 * Router script for PHP's built-in server.
 *
 * Usage: php -S localhost:8000 server.php
 *
 * Option (A) strategy: PHP routes take priority over static .html files.
 * Static assets (CSS, JS, images, fonts) are served directly.
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
file_put_contents(__DIR__ . '/server_debug.txt', date('Y-m-d H:i:s') . ' URI: ' . $uri . ' ROUTE: ' . ($_GET['route'] ?? 'none') . "\n", FILE_APPEND);

/* 301 Redirect map for legacy static HTML URLs */
$redirects = [
    '/index.html' => '/',
    '/about.html' => '/about',
    '/services.html' => '/services',
    '/service.html' => '/services',
    '/contact.html' => '/contact',
    '/quote.html' => '/quote',
    '/team.html' => '/team',
    '/testimonial.html' => '/testimonials',
    '/testimonials.html' => '/testimonials',
    '/leather.html' => '/products/leather',
    '/dhokra.html' => '/products/dhokra',
    '/furniture.html' => '/products/furniture',
    '/terracotta.html' => '/products/terracotta',
    '/jute.html' => '/products/jute',
    '/wooden_handicraft.html' => '/products/wooden-handicraft',
    '/wooden-handicraft.html' => '/products/wooden-handicraft',
    '/fruit-vegetable.html' => '/products/fruit-vegetable',
];

$lowerUri = strtolower($uri);
if (isset($redirects[$uri]) || isset($redirects[$lowerUri])) {
    $target = $redirects[$uri] ?? $redirects[$lowerUri];
    header('Location: ' . $target, true, 301);
    exit;
}

/* ---------------------------------------------------------------
   1. Block access to sensitive files and directories
   --------------------------------------------------------------- */

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

/* ---------------------------------------------------------------
   2. Serve static assets directly (CSS, JS, images, fonts, etc.)
      but NOT .html files — those go through the PHP router.
   --------------------------------------------------------------- */

$publicPath = __DIR__ . $uri;

if ($uri !== '/' && is_file($publicPath)) {
    $ext = strtolower(pathinfo($publicPath, PATHINFO_EXTENSION));

    /* .html files are NOT served directly — they go through the PHP
       router so that the PHP version takes priority. If no PHP route
       matches, RouteManager will 404 (the .html file is the fallback
       the user can type directly if needed). */
    if ($ext !== 'html' && $ext !== 'shtml' && $ext !== 'php') {
        return false; // Let PHP's built-in server handle the static file
    }
}

/* ---------------------------------------------------------------
   3. Route everything else through index.php
   --------------------------------------------------------------- */

$_GET['route'] = trim($uri, '/');

require __DIR__ . '/index.php';
