<?php

/**
 * Base class for migrations.
 *
 * Development runs on SQLite and production on MySQL, and the two disagree
 * about the only column every table has: SQLite wants
 * `INTEGER PRIMARY KEY AUTOINCREMENT`, MySQL wants `INT AUTO_INCREMENT
 * PRIMARY KEY` and rejects the other outright. Rather than write every
 * migration twice — which is how the two schemas quietly drift apart — the
 * type helpers below answer for whichever driver is connected.
 *
 * Everything else in these migrations is ordinary SQL that both accept.
 */
abstract class Migration
{
    protected PDO $pdo;
    protected string $driver;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    abstract public function up();

    public function down()
    {
    }

    /** Optional: sample or required rows, run by `php vayu seed`. */
    public function seed()
    {
    }

    /* ---------------------------------------------------------
       Column types
       --------------------------------------------------------- */

    protected function isSqlite(): bool
    {
        return $this->driver === 'sqlite';
    }

    /**
     * The auto-incrementing primary key.
     *
     * Signed, deliberately. MySQL refuses a foreign key whose column is INT
     * when the column it references is INT UNSIGNED — so an unsigned primary
     * key would mean every `*_id` column in every migration had to repeat
     * UNSIGNED, and the one that forgot would fail on MySQL and pass on
     * SQLite, which does not check. Two billion rows is not a limit this
     * hospital will meet.
     */
    protected function id(string $name = 'id'): string
    {
        return $this->isSqlite()
            ? "{$name} INTEGER PRIMARY KEY AUTOINCREMENT"
            : "{$name} INT NOT NULL AUTO_INCREMENT PRIMARY KEY";
    }

    /**
     * A JSON column. MySQL has a real JSON type with validation; SQLite stores
     * it as text. Both are read back through json_column().
     */
    protected function json(string $name): string
    {
        return $this->isSqlite() ? "{$name} TEXT" : "{$name} JSON";
    }

    /**
     * SQLite has no boolean, and MySQL's is an alias for TINYINT(1) — so this
     * is TINYINT either way and the value is always 0 or 1, never a PHP bool
     * round-tripped through a driver that disagrees about it.
     */
    protected function bool(string $name, bool $default = false): string
    {
        return "{$name} TINYINT NOT NULL DEFAULT " . ($default ? '1' : '0');
    }

    protected function timestamps(): string
    {
        return 'created_at DATETIME, updated_at DATETIME, deleted_at DATETIME';
    }

    /** Table options MySQL needs and SQLite rejects. */
    protected function tableOptions(): string
    {
        return $this->isSqlite() ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    }

    /* ---------------------------------------------------------
       Statements
       --------------------------------------------------------- */

    /**
     * @param string[] $columns
     */
    protected function create(string $table, array $columns): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$table} (\n    "
            . implode(",\n    ", $columns)
            . "\n)" . $this->tableOptions();

        $this->pdo->exec($sql);
    }

    protected function drop(string $table): void
    {
        $this->pdo->exec("DROP TABLE IF EXISTS {$table}");
    }

    /**
     * Indexes are separate statements because MySQL will not accept
     * `CREATE INDEX IF NOT EXISTS` before 8.0 in every configuration, and a
     * duplicate index is not worth failing a migration over.
     */
    protected function index(string $table, string $column, bool $unique = false): void
    {
        $name = 'idx_' . $table . '_' . str_replace([',', ' '], ['_', ''], $column);
        $kind = $unique ? 'UNIQUE INDEX' : 'INDEX';

        try {
            if ($this->isSqlite()) {
                $this->pdo->exec("CREATE " . ($unique ? 'UNIQUE ' : '') . "INDEX IF NOT EXISTS {$name} ON {$table} ({$column})");
            } else {
                $this->pdo->exec("CREATE {$kind} {$name} ON {$table} ({$column})");
            }
        } catch (PDOException $e) {
            /* Already there. Nothing to do and nothing to report. */
        }
    }
}
