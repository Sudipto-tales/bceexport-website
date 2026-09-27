<?php

// Load Composer autoload and dotenv if available
$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;

    if (class_exists('Dotenv\\Dotenv')) {
        $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
        $dotenv->safeLoad();
    }
}

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/framework.php';
require_once __DIR__ . '/db.php';

// Load all core files dynamically
foreach (glob(__BASEDIR__ . '/core/*.php') as $filename) {
    require_once $filename;
}

/** Load a view file with its data extracted into scope. */
function load_view($path, $data = [])
{
    $file_path = __BASEDIR__ . '/' . ltrim($path, '/');

    if (!file_exists($file_path)) {
        error_log("[Vayu] View not found: {$path}");
        if (APP_DEBUG) {
            echo "Error: View '{$path}' not found!";
        }
        return;
    }

    extract($data);
    require $file_path;
}

/**
 * A URL for a path on this site.
 *
 * Built from APP_URL, not from $_SERVER, for two reasons: it has to work
 * under the CLI where there is no request at all, and it has to keep working
 * behind a proxy that terminates TLS — where $_SERVER['HTTPS'] is empty and
 * deriving the scheme from it would emit http:// links on an https:// page.
 */
function base_url($path = '')
{
    global $base_url;

    $root = rtrim($base_url ?: 'http://localhost', '/');

    return $path === '' ? $root : $root . '/' . ltrim($path, '/');
}

/** Escape for HTML. Short name because views are full of it. */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/* -------------------------------------------------------------
   CORS — only needed when frontend and API are on different
   origins. With APP_URL=http://localhost:8000 and the page
   opened at http://localhost:8000, requests are same-origin
   and browsers do not enforce CORS. This block is a safety net
   for any reverse-proxy or subdomain setups.
   ------------------------------------------------------------- */
if (isset($_SERVER['HTTP_ORIGIN'])) {
    $origin = $_SERVER['HTTP_ORIGIN'];
    $allowed = [
        'http://localhost:8000',
        'http://127.0.0.1:8000',
        env('APP_URL', ''),
    ];

    $allowed = array_filter($allowed);
    $allowed = array_unique($allowed);

    if (in_array($origin, $allowed, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Accept, X-CSRF-Token, Authorization');
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}
