<?php

/**
 * php vayu migrate         run pending migrations
 * php vayu migrate:fresh   drop every table, then run all of them
 * php vayu seed            run each migration's seed()
 *
 * migrate:fresh asks before it destroys anything, and refuses outright when
 * APP_ENV is production. `--force` skips the prompt for scripted use; nothing
 * skips the production guard.
 */
class MigrateCommand
{
    private string $baseDir;

    public function __construct(string $baseDir, array $framework = [])
    {
        $this->baseDir = $baseDir;
    }

    public function run(string $action, array $args = []): void
    {
        require_once $this->baseDir . '/config/migrate.php';

        global $pdo;

        $out = static fn (string $line) => print($line . PHP_EOL);
        $force = in_array('--force', $args, true);

        echo PHP_EOL;
        echo "  \033[2mDatabase:\033[0m " . env('DB_TYPE', 'sqlite') . PHP_EOL . PHP_EOL;

        match ($action) {
            'migrate' => $this->migrate($pdo, $out),
            'migrate:fresh' => $this->fresh($pdo, $out, $force),
            'seed' => $this->seed($pdo, $out),
            default => $this->unknown($action),
        };

        echo PHP_EOL;
    }

    private function migrate(PDO $pdo, callable $out): void
    {
        $result = migration_run($pdo, $out);

        if (!$result['ran']) {
            echo "  \033[32mNothing to migrate\033[0m — {$result['skipped']} already applied." . PHP_EOL;
            return;
        }

        echo PHP_EOL . "  \033[32m" . count($result['ran']) . " migrated\033[0m" . PHP_EOL;
    }

    private function fresh(PDO $pdo, callable $out, bool $force): void
    {
        if (APP_ENV === 'production') {
            echo "  \033[31mRefusing to run migrate:fresh in production.\033[0m" . PHP_EOL;
            echo "  It drops every table. Change APP_ENV if you really mean it." . PHP_EOL;
            exit(1);
        }

        if (!$force) {
            echo "  \033[33mThis drops every table and all their data.\033[0m" . PHP_EOL;
            echo '  Type "yes" to continue: ';

            $answer = trim((string) fgets(STDIN));
            if (strtolower($answer) !== 'yes') {
                echo PHP_EOL . '  Cancelled.' . PHP_EOL;
                return;
            }
            echo PHP_EOL;
        }

        migration_reset($pdo, $out);
        echo PHP_EOL;
        migration_run($pdo, $out);
        echo PHP_EOL . "  \033[32mDatabase rebuilt\033[0m" . PHP_EOL;
    }

    private function seed(PDO $pdo, callable $out): void
    {
        $count = migration_seed($pdo, $out);

        echo PHP_EOL . "  \033[32m{$count} seeded\033[0m" . PHP_EOL;
    }

    private function unknown(string $action): void
    {
        echo "  \033[31mUnknown migration command: {$action}\033[0m" . PHP_EOL;
        exit(1);
    }
}
