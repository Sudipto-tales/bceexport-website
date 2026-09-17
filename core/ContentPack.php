<?php

/**
 * Content packs — a tracked bundle of files and rows, applied to a live site.
 *
 * `php vayu seed` cannot do this job, and running it on a live site is the
 * mistake this class exists to remove. The seeder empties every seeded table
 * before it fills it, which is correct for a first install and ruinous
 * afterwards: enquiries, appointments and every edit made in the panel since
 * the deploy are in those tables. A pack is the other shape — additive, keyed
 * by a column rather than by row order, and safe to apply twice.
 *
 * What a pack carries, and why each half is needed:
 *
 *   files  Binaries a deploy cannot bring. `assets/uploads/` is gitignored on
 *          purpose — so that no Deploy can overwrite what editors uploaded —
 *          which also means a git checkout has no way to *put* a new photo
 *          there. The pack keeps its own copy under `database/content/`, a
 *          directory .htaccess refuses to serve, and copies it into place.
 *   sets   Rows, each set naming its table and the column that identifies a
 *          record. A key already in the table is updated; one that is not is
 *          inserted. Nothing here deletes a row, ever.
 *
 * Applying a pack twice changes nothing the second time. That matters more
 * than it sounds: the HTTP route below is a URL somebody will retry when a
 * response is slow, and a retry that duplicates nine gallery items would be
 * discovered by a visitor rather than by us.
 *
 * The trust model is "the repository is trusted, the request is not". A pack
 * is a tracked file that went through review and a push to `main`; the caller
 * only chooses *which* pack, by name, from a directory listing. That is why
 * the rules below constrain the pack's contents anyway — a bug in a generator
 * should not be able to write a .php file into the document root either.
 */
final class ContentPack
{
    /** Where packs live, relative to the project root. Denied by .htaccess. */
    public const DIR = 'database/content';

    /**
     * Where a pack may write, relative to the project root.
     *
     * One prefix, and it is the uploads directory the application already
     * serves images and clips from. Anything writable outside it is a way to
     * land a file next to the code that runs.
     */
    private const TARGET_PREFIX = 'assets/uploads/';

    /**
     * What a pack may write.
     *
     * Deliberately narrower than Upload's list — no SVG, which is a document
     * that can carry script, and this is the one path where a file arrives
     * without an editor having chosen it in a picker.
     */
    private const EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'mp4', 'webm'];

    /**
     * Tables a pack may write to.
     *
     * An allowlist rather than a denylist: the cost of forgetting an entry is
     * an error message, and the cost of forgetting one in a denylist is a pack
     * that can rewrite `users`. Add a table here when a pack genuinely needs
     * it, and check first that its rows are content rather than records people
     * created — an upsert into `enquiries` would be editing someone's message.
     */
    private const TABLES = ['gallery', 'media'];

    /* ---------------------------------------------------------
       Listing
       --------------------------------------------------------- */

    /** @return string[] pack names, without the .json */
    public static function available(): array
    {
        $names = [];

        foreach (glob(__BASEDIR__ . '/' . self::DIR . '/*.json') ?: [] as $file) {
            $names[] = basename($file, '.json');
        }

        sort($names);

        return $names;
    }

    /* ---------------------------------------------------------
       Applying
       --------------------------------------------------------- */

    /**
     * @param  bool $dryRun Report what would change without changing it.
     * @return array{pack: string, dryRun: bool, files: array, rows: array, warnings: string[]}
     * @throws RuntimeException on anything malformed; the caller turns it into
     *         a 4xx. Every check that can fail is made before the first write.
     */
    public static function apply(string $pack, bool $dryRun = false): array
    {
        $manifest = self::read($pack);

        $warnings = [];
        $files = self::files($manifest['files'] ?? [], $dryRun, $warnings);
        $rows = self::sets($manifest['sets'] ?? [], $dryRun, $warnings);

        return [
            'pack' => $pack,
            'dryRun' => $dryRun,
            'files' => $files,
            'rows' => $rows,
            'warnings' => $warnings,
        ];
    }

    /* ---------------------------------------------------------
       The manifest
       --------------------------------------------------------- */

    private static function read(string $pack): array
    {
        /* The name is the only thing a caller supplies, so it is the only
           thing that can be hostile. Lowercase, digits and dashes cannot spell
           `..` or an absolute path, which makes the concatenation below safe
           without a realpath dance. */
        if (!preg_match('/^[a-z0-9][a-z0-9-]*$/', $pack)) {
            throw new RuntimeException('Not a pack name: ' . $pack);
        }

        $path = __BASEDIR__ . '/' . self::DIR . '/' . $pack . '.json';

        if (!is_file($path)) {
            throw new RuntimeException("No such pack: {$pack}");
        }

        $manifest = json_decode((string) file_get_contents($path), true);

        if (!is_array($manifest)) {
            throw new RuntimeException("{$pack}.json is not valid JSON");
        }

        return $manifest;
    }

    /* ---------------------------------------------------------
       Files
       --------------------------------------------------------- */

    /**
     * @return array{copied: int, present: int, planned: string[]}
     */
    private static function files(array $entries, bool $dryRun, array &$warnings): array
    {
        $copied = 0;
        $present = 0;
        $planned = [];

        /* Everything is validated before anything is copied. A pack that is
           wrong about its ninth file should not have already written its
           first eight — a half-applied pack is the state nobody can reason
           about afterwards. */
        $work = [];

        foreach ($entries as $entry) {
            $source = (string) ($entry['source'] ?? '');
            $target = ltrim((string) ($entry['target'] ?? ''), '/');

            if ($source === '' || $target === '') {
                throw new RuntimeException('A file entry is missing source or target');
            }

            /* `..` is checked on the raw string rather than after resolution:
               the target does not exist yet, so there is nothing to resolve,
               and a segment-wise check is what the prefix rule below relies
               on to mean what it says. */
            if (str_contains($source, '..') || str_contains($target, '..')) {
                throw new RuntimeException("Path segments are not allowed to walk up: {$target}");
            }

            if (!str_starts_with($target, self::TARGET_PREFIX)) {
                throw new RuntimeException("A pack may only write under " . self::TARGET_PREFIX . ", not: {$target}");
            }

            $extension = strtolower(pathinfo($target, PATHINFO_EXTENSION));

            if (!in_array($extension, self::EXTENSIONS, true)) {
                throw new RuntimeException("A pack may not write a .{$extension} file: {$target}");
            }

            $absoluteSource = __BASEDIR__ . '/' . self::DIR . '/' . $source;

            if (!is_file($absoluteSource)) {
                throw new RuntimeException("The pack is missing its own file: {$source}");
            }

            /* The manifest records what it shipped. A mismatch means the file
               changed after the manifest was written — a bad merge, a truncated
               transfer, an editor "fixing" a binary — and copying it anyway
               would publish something nobody reviewed. */
            $hash = hash_file('sha256', $absoluteSource);

            if (!empty($entry['sha256']) && !hash_equals((string) $entry['sha256'], $hash)) {
                throw new RuntimeException("{$source} does not match the checksum in the manifest");
            }

            $work[] = [$absoluteSource, __BASEDIR__ . '/' . $target, $target, $hash];
        }

        foreach ($work as [$absoluteSource, $absoluteTarget, $target, $hash]) {
            if (is_file($absoluteTarget)) {
                /* Same bytes: the pack has already been applied here, and this
                   is the retry that must do nothing. Different bytes under the
                   same name is not something a random 32-hex filename does by
                   accident, so it is reported and left alone rather than
                   overwritten — the file already in place is the one the
                   database rows point at. */
                if (hash_file('sha256', $absoluteTarget) !== $hash) {
                    $warnings[] = "{$target} exists with different contents and was left as it is";
                }

                $present++;
                continue;
            }

            $planned[] = $target;

            if ($dryRun) {
                continue;
            }

            $directory = dirname($absoluteTarget);

            if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new RuntimeException("Could not create {$directory}");
            }

            if (!copy($absoluteSource, $absoluteTarget)) {
                throw new RuntimeException("Could not write {$target}");
            }

            chmod($absoluteTarget, 0644);
            $copied++;
        }

        return ['copied' => $copied, 'present' => $present, 'planned' => $planned];
    }

    /* ---------------------------------------------------------
       Rows
       --------------------------------------------------------- */

    /**
     * @return array{inserted: int, updated: int, byTable: array<string, array{inserted: int, updated: int}>}
     */
    private static function sets(array $sets, bool $dryRun, array &$warnings): array
    {
        $inserted = 0;
        $updated = 0;
        $byTable = [];

        foreach ($sets as $set) {
            $table = (string) ($set['table'] ?? '');
            $key = (string) ($set['key'] ?? '');
            $rows = $set['rows'] ?? [];
            /* Tables with a `public_id` get theirs at import time, from the
               numbers the destination is actually using. A pack cannot carry
               them: it was generated against one database and applied to
               another, and med-004 is taken on the live site by whatever the
               editor uploaded last Tuesday. */
            $idPrefix = (string) ($set['idPrefix'] ?? '');

            if (!in_array($table, self::TABLES, true)) {
                throw new RuntimeException("A pack may not write to `{$table}`");
            }

            $columns = self::columns($table);

            if ($key === '' || !in_array($key, $columns, true)) {
                throw new RuntimeException("`{$key}` is not a column of `{$table}`");
            }

            $counts = ['inserted' => 0, 'updated' => 0];

            /* One transaction per set. A set is the unit that makes sense on
               its own — the stills can land without the gallery rows and the
               site is unchanged; half the gallery rows landing is a page with
               holes in it. */
            $work = static function () use ($table, $key, $rows, $columns, $idPrefix, $dryRun, &$counts) {
                foreach ($rows as $row) {
                    if (!is_array($row) || !array_key_exists($key, $row)) {
                        throw new RuntimeException("A row in `{$table}` has no {$key}");
                    }

                    $unknown = array_diff(array_keys($row), $columns);

                    if ($unknown) {
                        throw new RuntimeException(
                            "`{$table}` has no column " . implode(', ', $unknown)
                        );
                    }

                    $exists = db_scalar(
                        "SELECT COUNT(*) FROM {$table} WHERE {$key} = ?",
                        [$row[$key]],
                        0
                    ) > 0;

                    $values = $row;
                    $now = date('Y-m-d H:i:s');

                    if (in_array('updated_at', $columns, true)) {
                        $values['updated_at'] = $now;
                    }

                    if ($exists) {
                        $counts['updated']++;

                        if ($dryRun) {
                            continue;
                        }

                        unset($values[$key]);

                        $assignments = implode(', ', array_map(
                            static fn ($column) => "{$column} = ?",
                            array_keys($values)
                        ));

                        db_execute(
                            "UPDATE {$table} SET {$assignments} WHERE {$key} = ?",
                            [...array_values($values), $row[$key]]
                        );

                        continue;
                    }

                    $counts['inserted']++;

                    if ($dryRun) {
                        continue;
                    }

                    if (in_array('created_at', $columns, true) && empty($values['created_at'])) {
                        $values['created_at'] = $now;
                    }

                    if ($idPrefix !== '' && in_array('public_id', $columns, true) && empty($values['public_id'])) {
                        $values['public_id'] = next_public_id($table, $idPrefix);
                    }

                    db_execute(
                        "INSERT INTO {$table} (" . implode(', ', array_keys($values)) . ')'
                        . ' VALUES (' . implode(', ', array_fill(0, count($values), '?')) . ')',
                        array_values($values)
                    );
                }
            };

            $dryRun ? $work() : db_transaction($work);

            $inserted += $counts['inserted'];
            $updated += $counts['updated'];
            $byTable[$table] = $counts;
        }

        return ['inserted' => $inserted, 'updated' => $updated, 'byTable' => $byTable];
    }

    /** @return string[] */
    private static function columns(string $table): array
    {
        global $pdo;

        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        $rows = $driver === 'sqlite'
            ? $pdo->query("PRAGMA table_info({$table})")->fetchAll()
            : $pdo->query("SHOW COLUMNS FROM {$table}")->fetchAll();

        return array_values(array_filter(array_map(
            static fn ($row) => $row['name'] ?? $row['Field'] ?? '',
            $rows
        )));
    }
}
