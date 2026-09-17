<?php

/**
 * Migration runner. Driven by the CLI:
 *
 *     php vayu migrate         run anything not yet run
 *     php vayu migrate:fresh   drop everything, then run all of it
 *     php vayu seed            run each migration's seed()
 *
 * Files run in filename order, so a table another one references by name is
 * created first — prefix a migration with a number when order matters.
 *
 * The original version of this file opened with
 * `define('_BASEDIR_', $base_url)` against a variable that did not exist yet,
 * and was not reachable from the CLI at all.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/migration.php';

function migration_files(): array
{
    $files = glob(__BASEDIR__ . '/database/migrations/*.php') ?: [];
    sort($files);
    return $files;
}

function migration_class(string $file): ?string
{
    $name = basename($file, '.php');

    /* 001_UsersTable.php and UsersTable.php both hold class UsersTable. */
    $name = preg_replace('/^\d+[_-]/', '', $name);
    $class = implode('', array_map('ucfirst', preg_split('/[_-]/', $name) ?: [$name]));

    require_once $file;

    return class_exists($class) ? $class : null;
}

function migration_ensure_table(PDO $pdo): void
{
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $id = $driver === 'sqlite'
        ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
        : 'id INT NOT NULL AUTO_INCREMENT PRIMARY KEY';

    $pdo->exec("CREATE TABLE IF NOT EXISTS migrations (
        {$id},
        migration VARCHAR(191) NOT NULL,
        batch INT
    )");
}

/**
 * @return array{ran: string[], skipped: int}
 */
function migration_run(PDO $pdo, callable $out): array
{
    migration_ensure_table($pdo);

    $already = array_column($pdo->query('SELECT migration FROM migrations')->fetchAll(), 'migration');
    $batch = time();
    $ran = [];
    $skipped = 0;

    foreach (migration_files() as $file) {
        $name = basename($file, '.php');

        if (in_array($name, $already, true)) {
            $skipped++;
            continue;
        }

        $class = migration_class($file);
        if (!$class) {
            $out("  ! no class found in {$name}");
            continue;
        }

        (new $class($pdo))->up();

        $stmt = $pdo->prepare('INSERT INTO migrations (migration, batch) VALUES (?, ?)');
        $stmt->execute([$name, $batch]);

        $out("  + {$name}");
        $ran[] = $name;
    }

    return ['ran' => $ran, 'skipped' => $skipped];
}

/** Drops in reverse order, so a table goes before whatever it points at. */
function migration_reset(PDO $pdo, callable $out): void
{
    foreach (array_reverse(migration_files()) as $file) {
        $class = migration_class($file);
        if (!$class) {
            continue;
        }

        (new $class($pdo))->down();
        $out('  - ' . basename($file, '.php'));
    }

    $pdo->exec('DROP TABLE IF EXISTS migrations');
}

/**
 * Runs any migration that defines its own seed(), then database/Seeder.php,
 * which loads the JSON the exporter writes.
 *
 * Both exist because they answer different questions. A migration's seed() is
 * for a row its own table cannot work without. The Seeder is for content, and
 * content does not belong in a migration — it changes for editorial reasons,
 * not schema ones.
 */
function migration_seed(PDO $pdo, callable $out): int
{
    $count = 0;

    foreach (migration_files() as $file) {
        $class = migration_class($file);
        if (!$class) {
            continue;
        }

        $migration = new $class($pdo);

        /* Only migrations that define one — the base class's seed() is empty,
           so calling it on all of them would report work never done. */
        $reflection = new ReflectionMethod($migration, 'seed');
        if ($reflection->getDeclaringClass()->getName() === 'Migration') {
            continue;
        }

        $migration->seed();
        $out('  + ' . basename($file, '.php'));
        $count++;
    }

    $seeder = __BASEDIR__ . '/database/Seeder.php';

    if (is_file($seeder)) {
        require_once $seeder;
        $count += (new Seeder($pdo, $out))->run();
    }

    return $count;
}
