<?php

/**
 * Every mutation the API performs writes one row here.
 *
 * Several people edit this site and roles are displayed but not enforced
 * (docs/php/06-decisions.md §2), so this is the only answer to "who changed
 * the emergency number". Nothing offers a delete.
 *
 * Logging must never break the write it is describing. A failure here is
 * swallowed and sent to the error log: losing an audit row is bad, losing the
 * doctor somebody just saved because the audit table was locked is worse.
 */
final class ActivityLog
{
    public static function record(
        string $action,
        string $entity,
        ?string $entityId = null,
        string $summary = '',
        ?array $diff = null
    ): void {
        try {
            $user = Auth::user();

            db_execute(
                'INSERT INTO activity_log
                    (user_id, user_name, action, entity, entity_id, summary, diff, ip, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $user['id'] ?? null,
                    $user['name'] ?? null,
                    $action,
                    $entity,
                    $entityId,
                    $summary,
                    $diff === null ? null : json_encode($diff),
                    self::ip(),
                    now_iso(),
                ]
            );
        } catch (Throwable $e) {
            error_log('[activity] ' . $e->getMessage());
        }
    }

    /**
     * Only the fields that moved, with what they moved from and to.
     *
     * Storing whole records would make the log larger than the content inside
     * a month, and reading it would mean diffing by eye.
     */
    public static function diff(array $before, array $after): array
    {
        $diff = [];

        foreach ($after as $field => $value) {
            $old = $before[$field] ?? null;

            if ($old === $value) {
                continue;
            }

            /* Long text turns the log into a wall. That a body changed is
               the useful fact; what it changed to is on the record. */
            $diff[$field] = [
                'from' => self::summarise($old),
                'to' => self::summarise($value),
            ];
        }

        return $diff;
    }

    private static function summarise(mixed $value): mixed
    {
        if (is_array($value)) {
            return count($value) . ' item(s)';
        }

        if (is_string($value) && strlen($value) > 120) {
            return substr($value, 0, 117) . '...';
        }

        return $value;
    }

    private static function ip(): ?string
    {
        if (env('TRUST_PROXY', false) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwarded = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($forwarded[0]);
        }

        return $_SERVER['REMOTE_ADDR'] ?? null;
    }
}
