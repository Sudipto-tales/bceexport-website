<?php

/**
 * Per-IP, per-action rate limiting, in a table.
 *
 * The endpoints that need it are the ones anybody can reach without an
 * account: the contact form, the job application form, and the login screen.
 * Without a limit, the first two are a free mail relay pointed at the
 * hospital's own inbox and the third is an open door to password guessing.
 *
 * A fixed window rather than a sliding one. A determined attacker can send
 * 2n requests across a window boundary; that is a fine trade for a table with
 * no background job to prune it and no Redis to deploy.
 */
class RateLimit
{
    private static function ensureTable(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }

        try {
            db_execute('CREATE TABLE IF NOT EXISTS rate_limits (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                action VARCHAR(50) NOT NULL,
                client_key VARCHAR(100) NOT NULL,
                created_at DATETIME NOT NULL
            )');
            $checked = true;
        } catch (Throwable $e) {
            // Ignored
        }
    }

    /**
     * @param string $action  'enquiry', 'application', 'login'
     * @param int    $limit   attempts allowed in the window
     * @param int    $seconds window length
     */
    public static function tooMany(string $action, int $limit, int $seconds = 3600, ?string $key = null): bool
    {
        self::ensureTable();

        $key = $key ?? self::clientIp();
        $windowStart = gmdate('Y-m-d H:i:s', time() - $seconds);

        self::prune();

        try {
            $row = db_fetch_one(
                'SELECT COUNT(*) AS hits FROM rate_limits WHERE action = ? AND client_key = ? AND created_at >= ?',
                [$action, $key, $windowStart]
            );

            return (int) ($row['hits'] ?? 0) >= $limit;
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function hit(string $action, ?string $key = null): void
    {
        self::ensureTable();

        try {
            db_execute(
                'INSERT INTO rate_limits (action, client_key, created_at) VALUES (?, ?, ?)',
                [$action, $key ?? self::clientIp(), now_iso()]
            );
        } catch (Throwable $e) {
            // Ignored
        }
    }

    /**
     * Check and record in one call. Returns false when the caller is over the
     * limit and should be refused.
     *
     * This exists because tooMany() and hit() are two calls and forgetting the
     * second is the classic way to ship a limiter that counts nothing.
     */
    public static function attempt(string $action, int $limit, int $seconds = 3600, ?string $key = null): bool
    {
        if (self::tooMany($action, $limit, $seconds, $key)) {
            return false;
        }

        self::hit($action, $key);

        return true;
    }

    /** Wipes an action's history for this client — call after a successful login. */
    public static function clear(string $action, ?string $key = null): void
    {
        try {
            db_execute(
                'DELETE FROM rate_limits WHERE action = ? AND client_key = ?',
                [$action, $key ?? self::clientIp()]
            );
        } catch (Throwable $e) {
            // Ignored
        }
    }

    /**
     * Anything older than a day is of no use to any window in play. Done
     * probabilistically so the common request pays nothing — there is no cron
     * on this deployment to do it properly.
     */
    private static function prune(): void
    {
        if (random_int(1, 100) !== 1) {
            return;
        }

        db_execute('DELETE FROM rate_limits WHERE created_at < ?', [gmdate('Y-m-d H:i:s', time() - 86400)]);
    }

    /**
     * The proxy headers are trusted only when the app is told to trust them,
     * because anyone can send an X-Forwarded-For and a limiter keyed on a
     * spoofable value limits nobody.
     */
    public static function clientIp(): string
    {
        if (env('TRUST_PROXY', false)) {
            $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
            if ($forwarded !== '') {
                $first = trim(explode(',', $forwarded)[0]);
                if (filter_var($first, FILTER_VALIDATE_IP)) {
                    return $first;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'cli';
    }
}
