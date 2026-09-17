<?php

/**
 * The database connection and the query helpers everything else uses.
 *
 * SQLite in development, MySQL in production, chosen by DB_TYPE and nothing
 * else. The MongoDB branch the framework shipped with has been removed: it is
 * unused here, and its helpers took a different shape from the SQL ones, which
 * is how a codebase ends up with two ways to read a row.
 */

$db_type = env('DB_TYPE', 'sqlite');

$pdoOptions = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

switch ($db_type) {
    case 'sqlite':
        $sqliteFile = env('DB_DATABASE', '');
        $usingDefault = ($sqliteFile === '' || $sqliteFile === null);

        if ($usingDefault) {
            $sqliteFile = dirname(__DIR__) . '/database/bceexport.sqlite';
        }

        $directory = dirname($sqliteFile);

        /**
         * PDO does not refuse a SQLite path that is not there — it creates the
         * file, connects to it, and reports success. Every query after that
         * fails with "no such table", which reads like a broken migration and
         * is really a wrong DB_DATABASE two layers up.
         *
         * So on the web, a missing file is an error. The CLI is exempt because
         * creating the file is exactly what `php vayu migrate` is for on a
         * fresh install, and it is run by someone who can read what it says.
         */
        $creatingIsAllowed = PHP_SAPI === 'cli';

        if (!$creatingIsAllowed && !is_file($sqliteFile)) {
            $detail = $usingDefault
                ? 'DB_DATABASE is empty, so this is the default path — the one to '
                    . 'upload the database to if the host gives no shell. Upload it to '
                    . 'exactly this path; it is gitignored, so no deploy will overwrite '
                    . 'it, and .htaccess already refuses it over HTTP.'
                : 'That is the DB_DATABASE value from .env, and nothing is there. Note '
                    . 'that a path which does not exist reads as an empty database '
                    . 'rather than as a bad path, so check this one character by '
                    . 'character against where the file actually is.';

            $message = "SQLite database not found: {$sqliteFile}. {$detail}";

            /* Logged as well as thrown: APP_DEBUG=false turns display_errors
               off, which is right for production and would otherwise leave
               this diagnosis nowhere to be read. */
            error_log('[Vayu] ' . $message);

            throw new RuntimeException($message);
        }

        if (!is_dir($directory)) {
            if (!$creatingIsAllowed) {
                $message = "SQLite directory not found: {$directory}. "
                    . 'Create it and make it writable by PHP before deploying.';

                error_log('[Vayu] ' . $message);

                throw new RuntimeException($message);
            }

            mkdir($directory, 0755, true);
        }

        $pdo = new PDO('sqlite:' . $sqliteFile, null, null, $pdoOptions);

        /**
         * SQLite needs to write beside the database, not only to it: the -wal
         * and -shm files live in the same directory. A directory that is
         * readable but not writable therefore fails on the first save in the
         * panel rather than here, with "attempt to write a readonly database"
         * against a file that is plainly writable.
         */
        if (!$creatingIsAllowed && !is_writable($directory)) {
            error_log("[Vayu] SQLite directory is not writable: {$directory}. "
                . 'Reads will work and every write will fail.');
        }

        /**
         * Existing is not the same as populated, and the check above only
         * proves the first. An empty SQLite database is a perfectly real file
         * — PDO creates one the moment anything connects to a path that is not
         * there, and it is roughly 4 KB once the WAL header is written, so
         * neither is_file() nor a size test tells it apart from the real
         * thing. What separates them is whether a schema was ever built.
         *
         * Without this the symptom is "no such table: pages" from wherever the
         * first query happens to live, which points at the model rather than
         * at an upload that never landed.
         */
        if (!$creatingIsAllowed) {
            $hasSchema = $pdo
                ->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'migrations' LIMIT 1")
                ->fetchColumn();

            if ($hasSchema === false) {
                $bytes = @filesize($sqliteFile);

                $message = "SQLite database has no schema: {$sqliteFile} "
                    . '(' . ($bytes === false ? 'unreadable' : number_format($bytes) . ' bytes') . '). '
                    . 'The file exists but no migration has ever run against it, which means '
                    . 'it is the empty one PDO created rather than the database you meant. '
                    . 'Either the upload has not happened, or it landed somewhere other than '
                    . 'this path — an upload two directories away leaves exactly this. '
                    . 'Overwrite this file with the real one, or run `php vayu migrate` if '
                    . 'the host gives you a shell.';

                error_log('[Vayu] ' . $message);

                throw new RuntimeException($message);
            }
        }

        /* Off by default in SQLite, which means a stale doctor_id in
           department_doctors would sit there unnoticed until a page rendered
           a blank card. */
        $pdo->exec('PRAGMA foreign_keys = ON');

        /* SQLite takes a lock over the whole database to write, not over the
           row. In the default rollback-journal mode that lock also shuts out
           readers, so one admin saving a post is enough to stall a visitor
           loading the home page. WAL lets readers carry on against the last
           committed state while the write lands. */
        $pdo->exec('PRAGMA journal_mode = WAL');

        /* And when two writes really do collide, wait rather than fail.
           Without this the second one returns SQLITE_BUSY immediately —
           "database is locked" on an otherwise ordinary save. */
        $pdo->exec('PRAGMA busy_timeout = 5000');

        /* fsync on every commit is the default and is what makes SQLite
           survive a power cut; NORMAL under WAL survives a process crash but
           not the machine losing power, and is roughly an order of magnitude
           faster on the shared hosting this runs on. The content here is
           recoverable from a backup, so that is the trade taken. */
        $pdo->exec('PRAGMA synchronous = NORMAL');
        break;

    case 'mysql':
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            env('DB_HOST', '127.0.0.1'),
            env('DB_PORT', '3306'),
            env('DB_DATABASE', '')
        );

        $pdo = new PDO($dsn, env('DB_USERNAME', ''), env('DB_PASSWORD', ''), $pdoOptions);
        break;

    default:
        throw new RuntimeException("Unsupported DB_TYPE: {$db_type}. Use sqlite or mysql.");
}

/* Published explicitly rather than left to scope. bootstrap.php is required
   from inside a method by the console commands, which would otherwise make
   $pdo local to that method and leave every `global $pdo` below reading null. */
$GLOBALS['pdo'] = $pdo;

/**
 * Every helper below goes through a prepared statement. There is no
 * string-interpolating variant on purpose — the moment one exists, it gets
 * used.
 */
function db_query(string $sql, array $params = []): PDOStatement
{
    global $pdo;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt;
}

function db_fetch_all(string $sql, array $params = []): array
{
    return db_query($sql, $params)->fetchAll();
}

function db_fetch_one(string $sql, array $params = [])
{
    return db_query($sql, $params)->fetch();
}

/** Returns the number of rows affected. */
function db_execute(string $sql, array $params = []): int
{
    return db_query($sql, $params)->rowCount();
}

/** A single scalar — counts, sums, one column of one row. */
function db_scalar(string $sql, array $params = [], $default = null)
{
    $value = db_query($sql, $params)->fetchColumn();

    return $value === false ? $default : $value;
}

function db_last_insert_id(): string
{
    global $pdo;

    return $pdo->lastInsertId();
}

/**
 * Runs $work inside a transaction, rolling back if it throws.
 *
 * Used where a write is really several — an application row plus its CV
 * record, a reorder that renumbers a span — and half of it landing is worse
 * than none of it.
 */
function db_transaction(callable $work)
{
    global $pdo;

    $pdo->beginTransaction();

    try {
        $result = $work();
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
