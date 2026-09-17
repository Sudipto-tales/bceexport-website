<?php

if (!function_exists('now_iso')) {
    /**
     * Timestamp for a database column. One format everywhere, so sorting works.
     *
     * UTC, not local time. APP_TIMEZONE is Asia/Kolkata, and it is what the
     * site displays in — but a stored local timestamp is ambiguous the moment
     * the setting changes, and the seeded rows were converted to UTC on the
     * way in. Store UTC, convert on display.
     */
    function now_iso(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}

if (!function_exists('str_slug')) {
    /** 'Dr. Anita Sharma' → 'dr-anita-sharma'. */
    function str_slug(string $value): string
    {
        $value = preg_replace('/[^\p{L}\p{N}]+/u', '-', $value) ?? '';
        $value = trim(strtolower($value), '-');

        return $value === '' ? '' : preg_replace('/-+/', '-', $value);
    }
}

if (!function_exists('json_column')) {
    /**
     * Decode a JSON column. Repeaters — a doctor's clinic schedule, a
     * department's procedure cards — are stored as JSON because they are
     * edited as a block and never queried by their contents. Bad or missing
     * data reads as empty rather than throwing: a malformed column should
     * cost one section of a page, not the whole page.
     */
    function json_column(mixed $raw, array $default = []): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (!is_string($raw) || $raw === '') {
            return $default;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : $default;
    }
}

if (!function_exists('iso_datetime')) {
    /**
     * A stored 'Y-m-d H:i:s' (UTC) as the ISO 8601 the API contract promises.
     *
     * The panel parses every timestamp with `new Date(...)`, which reads a
     * bare 'Y-m-d H:i:s' as local time in some browsers and rejects it in
     * others. The trailing Z is the whole difference between "saved a minute
     * ago" and "saved five and a half hours from now".
     */
    function iso_datetime(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        return str_replace(' ', 'T', substr($value, 0, 19)) . 'Z';
    }
}

if (!function_exists('next_public_id')) {
    /**
     * The next 'enq-004' style key for a table, continuing its numbering.
     *
     * The seeded rows use this scheme and the panel prints it as the record's
     * id, so a second scheme alongside it would be two kinds of reference to
     * the same kind of thing. Counts from the highest existing number rather
     * than the row count, including soft-deleted rows — a restored record must
     * never find its id taken.
     *
     * Two requests can still compute the same number. The unique index is what
     * settles that; callers retry.
     */
    function next_public_id(string $table, string $prefix, string $column = 'public_id'): string
    {
        $rows = db_fetch_all(
            "SELECT {$column} AS k FROM {$table} WHERE {$column} LIKE ?",
            [$prefix . '-%']
        );

        $highest = 0;

        foreach ($rows as $row) {
            $n = (int) substr((string) $row['k'], strlen($prefix) + 1);

            if ($n > $highest) {
                $highest = $n;
            }
        }

        return $prefix . '-' . str_pad((string) ($highest + 1), 3, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('user_display_name')) {
    /**
     * The name behind an `updated_by` column, cached for the request.
     *
     * Every row of every list carries one, and a page of twenty records is
     * usually two or three distinct editors.
     */
    function user_display_name(mixed $id): ?string
    {
        static $cache = [];

        if (!$id) {
            return null;
        }

        return $cache[$id] ??= (db_scalar('SELECT name FROM users WHERE id = ?', [$id]) ?: null);
    }
}

if (!function_exists('view_data')) {
    function view_data(array $data = [], array $extras = []): array
    {
        return array_merge($data, $extras);
    }
}

if (!function_exists('render_view')) {
    function render_view(string $path, array $data = [])
    {
        return load_view($path, $data);
    }
}

if (!function_exists('api_request')) {
    function api_request(string $url, string $method = 'GET', array $options = [])
    {
        $method = strtoupper($method);
        $query = $options['query'] ?? [];
        $headers = $options['headers'] ?? [];
        $payload = $options['payload'] ?? null;

        if ($query) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($query);
        }

        $headers = array_merge(['Accept: application/json'], $headers);
        $curlAvailable = function_exists('curl_init');

        if ($curlAvailable) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            if ($payload !== null && $method !== 'GET') {
                if (is_array($payload)) {
                    $payload = json_encode($payload);
                    $headers[] = 'Content-Type: application/json';
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            }

            $response = curl_exec($ch);
            $error = curl_error($ch);
            curl_close($ch);

            if ($response === false) {
                return ['success' => false, 'error' => $error ?: 'cURL request failed'];
            }
        } else {
            $contextOptions = [
                'http' => [
                    'method' => $method,
                    'header' => implode("\r\n", $headers),
                ],
            ];

            if ($payload !== null && $method !== 'GET') {
                $contextOptions['http']['content'] = is_array($payload) ? json_encode($payload) : $payload;
            }

            $context = stream_context_create($contextOptions);
            $response = @file_get_contents($url, false, $context);
            if ($response === false) {
                return ['success' => false, 'error' => 'Unable to fetch API response'];
            }
        }

        $decoded = json_decode($response, true);
        return $decoded !== null ? $decoded : $response;
    }
}

if (!function_exists('api_get')) {
    function api_get(string $url, array $query = [], array $headers = [])
    {
        return api_request($url, 'GET', ['query' => $query, 'headers' => $headers]);
    }
}

if (!function_exists('api_post')) {
    function api_post(string $url, array $payload = [], array $headers = [])
    {
        return api_request($url, 'POST', ['payload' => $payload, 'headers' => $headers]);
    }
}

?>