<?php
/* Application configuration, loaded from the environment. */

/* The CLI defines this before it loads anything, so that `php vayu help` can
   find the console commands without booting the framework. */
if (!defined('__BASEDIR__')) {
    define('__BASEDIR__', dirname(__DIR__));
}

define('APP_ENV', env('APP_ENV', 'production'));
define('APP_DEBUG', filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN));

/* Every date the panel shows and every timestamp it writes is local to the
   hospital. Left to UTC, an appointment logged at 9pm IST files itself under
   the previous day, which is the one time somebody is certain to notice. */
date_default_timezone_set(env('APP_TIMEZONE', 'Asia/Kolkata'));

/* $_SERVER['HTTP_HOST'] does not exist under the CLI, and the migration and
   seed commands load this file. Guarding it here is what lets `php vayu
   migrate` run at all. */
$httpHost = $_SERVER['HTTP_HOST'] ?? '';
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

if ($httpHost !== '') {
    $base_url = env('APP_URL', ($isHttps ? 'https://' : 'http://') . $httpHost);
} else {
    $base_url = env('APP_URL', 'http://localhost');
}

/* Same reason as $pdo in db.php — base_url() reads this through $GLOBALS, and
   the console commands boot the framework from inside a method. */
$GLOBALS['base_url'] = $base_url;

if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

$frontendRoutes = require __DIR__ . '/../app/view.php';
$apiRoutes = require __DIR__ . '/../api/gateway.php';

/* Union, not array_merge. PHP stores '404' as the integer 404, and
   array_merge renumbers integer keys — the error route arrived as key 0 and
   RouteManager, looking for '404', fell back to plain text on every path that
   matched no route. The two tables cannot collide in any case: an API key
   carries its method ('GET:api/...') and a frontend key never does. */
$routes = $frontendRoutes + $apiRoutes;
