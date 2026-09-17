<?php

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/migrate.php';

$out = function (string $msg) {
    echo $msg . PHP_EOL;
};

echo "=== Running BCE Export Database Setup ===" . PHP_EOL;

try {
    global $pdo;

    echo "Running Migrations..." . PHP_EOL;
    $res = migration_run($pdo, $out);
    echo "Migrations Completed: " . count($res['ran']) . " ran, " . $res['skipped'] . " skipped." . PHP_EOL;

    echo "Running Seeders..." . PHP_EOL;
    $seeded = migration_seed($pdo, $out);
    echo "Seeders Completed: {$seeded} records processed." . PHP_EOL;

    echo "SUCCESS: Database database/bceexport.sqlite is ready!" . PHP_EOL;
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    exit(1);
}
