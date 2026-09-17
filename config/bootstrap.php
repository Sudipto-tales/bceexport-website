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
