<?php

/**
 * One-shot setup: run migrations and seed the database.
 *
 * Usage:
 *     php run_setup.php              Run pending migrations + seed
 *     php run_setup.php fresh        Drop all tables, re-migrate, and seed
 */

require_once __DIR__ . '/config/migrate.php';

$pdo = $GLOBALS['pdo'];
$out = function ($msg) { echo $msg . PHP_EOL; };
$fresh = in_array('fresh', $argv ?? [], true);

echo PHP_EOL;
echo '╔════════════════════════════════════════╗' . PHP_EOL;
echo '║       BCE Export — Database Setup      ║' . PHP_EOL;
echo '╚════════════════════════════════════════╝' . PHP_EOL;
echo PHP_EOL;

/* Fresh install: drop everything first. */
if ($fresh) {
    echo '=== Dropping all tables (fresh) ===' . PHP_EOL;
    migration_reset($pdo, $out);
    echo PHP_EOL;
}

/* Run migrations. */
echo '=== Running Migrations ===' . PHP_EOL;
$result = migration_run($pdo, $out);

if (count($result['ran']) === 0 && $result['skipped'] > 0) {
    echo "  (all {$result['skipped']} migration(s) already applied)" . PHP_EOL;
} else {
    echo '  ' . count($result['ran']) . ' migration(s) ran, '
        . $result['skipped'] . ' skipped.' . PHP_EOL;
}

/* Seed data. */
echo PHP_EOL . '=== Seeding Data ===' . PHP_EOL;
$count = migration_seed($pdo, $out);

if ($count === 0) {
    echo '  (nothing to seed — data already exists)' . PHP_EOL;
} else {
    echo "  {$count} seeder(s) ran." . PHP_EOL;
}

echo PHP_EOL . '✓ Setup complete!' . PHP_EOL;
echo '  Admin login: admin@bceexport.com / admin123' . PHP_EOL;
echo '  Start server: php -S localhost:8000 server.php' . PHP_EOL;
echo PHP_EOL;
