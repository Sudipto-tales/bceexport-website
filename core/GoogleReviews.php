<?php

/**
 * The hospital's Google rating, read from the Places API and cached to disk.
 *
 * Configured through `settings.integrations` — `googlePlacesApiKey` and
 * `googlePlaceId` together turn it on, and `googleRating` / `googleReviewCount`
 * are the numbers shown until the first call succeeds. Both keys ship empty: an
 * install that has not been given them still renders the tile, from the
 * fallback, rather than dropping a hole in the page.
 *
 * No visitor ever waits on Google. A page render answers from the cache file,
 * however old it is, and queues the refresh through Async::defer(), which
 * flushes the response first. The worst case is a rating a few hours stale.
 *
 * The field mask asks for `rating` and `userRatingCount` and nothing else. Both
 * sit in the same billing tier, so the call costs one SKU; adding
 * `googleMapsUri` for the reviews link would add a second one, and the link can
 * be built from the place id here for free.
 *
 * At a twelve-hour TTL this is about sixty calls a month.
 */
final class GoogleReviews
{
    private const ENDPOINT = 'https://places.googleapis.com/v1/places/';

    /** Spaces are not allowed anywhere in a field mask. */
    private const FIELDS = 'rating,userRatingCount';

    /** Seconds. Deferred or not, nothing here holds a PHP worker for long. */
    private const TIMEOUT = 5;

    /** Seconds. A hospital's rating moves over months, not minutes. */
    private const TTL = 43200;

    /**
     * Seconds. After a failed call the cache is stamped anyway, so a wrong key
     * or a firewalled server retries twice an hour instead of on every render.
     */
    private const RETRY = 1800;

    /** Where a visitor is sent to read the reviews themselves. */
    private const REVIEWS_URL = 'https://search.google.com/local/reviews?placeid=';

    /** Both credentials present. Without them only the fallback is available. */
    public static function isConfigured(): bool
    {
        return self::apiKey() !== '' && self::placeId() !== '';
    }

    /**
     * What the rating tile renders, or null when there is nothing to show.
     *
     *   rating      float   4.4
     *   score       string  '4.4' — one decimal, as Google prints it
     *   count       int     1319, or 0 when unknown
     *   countLabel  string  '1,319'
     *   percent     int     88 — the score as a share of five, for the stars
     *   url         string  where to read the reviews, or '' for no link
     *   live        bool    these numbers came from Google, not the fallback
     *   fetchedAt   int     when, or 0
     */
    public static function summary(): ?array
    {
        $cached = self::cached();

        if ($cached === null || self::isStale($cached)) {
            self::queueRefresh();
        }

        $rating = (float) ($cached['rating'] ?? 0);
        $count = (int) ($cached['count'] ?? 0);
        $live = $rating > 0;

        /* A cache that holds nothing usable — first render, or a key that has
           never worked — falls back to the panel's own numbers. */
        if (!$live) {
            $rating = (float) self::setting('googleRating');
            $count = (int) self::setting('googleReviewCount');
        }

        if ($rating <= 0) {
            return null;
        }

        return [
            'rating' => $rating,
            'score' => number_format($rating, 1),
            'count' => $count,
            'countLabel' => number_format($count),
            'percent' => (int) round(min($rating, 5) / 5 * 100),
            'url' => self::reviewsUrl(),
            'live' => $live,
            'fetchedAt' => (int) ($cached['fetchedAt'] ?? 0),
        ];
    }

    /**
     * Call Google and rewrite the cache. Public because the deferred task and
     * anything scripted (a cron warming the cache) both need it; a page render
     * should go through summary() instead.
     *
     * @return bool whether the cache now holds fresh numbers
     */
    public static function refresh(): bool
    {
        if (!self::isConfigured()) {
            return false;
        }

        $body = self::get(self::ENDPOINT . rawurlencode(self::placeId()));
        $decoded = $body === null ? null : json_decode($body, true);

        /* Google answers an error as JSON too, so the test is for the field
           being asked for rather than for the request having completed. */
        if (!is_array($decoded) || !isset($decoded['rating'])) {
            $reason = is_array($decoded)
                ? (string) ($decoded['error']['message'] ?? 'no rating in the response')
                : 'the service could not be reached';

            error_log('[google-reviews] ' . $reason . ' — keeping the last known rating');

            /* The place id goes in either way. Without it the file reads as
               belonging to another place, and the failed call would not hold
               off the next one. */
            self::write(['checkedAt' => time(), 'placeId' => self::placeId()] + (self::cached() ?? []));

            return false;
        }

        self::write([
            'rating' => (float) $decoded['rating'],
            'count' => (int) ($decoded['userRatingCount'] ?? 0),
            'placeId' => self::placeId(),
            'fetchedAt' => time(),
            'checkedAt' => time(),
        ]);

        return true;
    }

    /* =========================================================
       Cache
       ========================================================= */

    private static function path(): string
    {
        return __BASEDIR__ . '/storage/cache/google-reviews.json';
    }

    /** The cache file, or null when it is missing, unreadable or for another place. */
    private static function cached(): ?array
    {
        static $cache = false;

        if ($cache !== false) {
            return $cache;
        }

        $cache = null;
        $raw = @file_get_contents(self::path());

        if ($raw !== false) {
            $decoded = json_decode($raw, true);

            /* A place id change in the panel invalidates the file: the numbers
               in it belong to whatever was configured before. */
            if (is_array($decoded) && (string) ($decoded['placeId'] ?? '') === self::placeId()) {
                $cache = $decoded;
            }
        }

        return $cache;
    }

    private static function isStale(?array $cached): bool
    {
        $checked = (int) ($cached['checkedAt'] ?? 0);

        /* A failed call stamps checkedAt without fetchedAt, and that is what
           the short retry window is measured against. */
        $window = isset($cached['fetchedAt']) ? self::TTL : self::RETRY;

        return time() - $checked >= $window;
    }

    /** Written through a temporary file so a reader never sees half of it. */
    private static function write(array $data): void
    {
        $path = self::path();
        $dir = dirname($path);

        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            error_log('[google-reviews] Could not create ' . $dir);
            return;
        }

        $temp = $path . '.' . getmypid() . '.tmp';
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        if ($json === false || @file_put_contents($temp, $json, LOCK_EX) === false || !@rename($temp, $path)) {
            @unlink($temp);
            error_log('[google-reviews] Could not write ' . $path);
        }
    }

    /**
     * Refresh after the response has gone out — once per request, and only
     * when there is a key to use.
     */
    private static function queueRefresh(): void
    {
        static $queued = false;

        if ($queued || !self::isConfigured()) {
            return;
        }

        $queued = true;

        Async::defer(static function (): void {
            self::refresh();
        });
    }

    /* =========================================================
       Settings and transport
       ========================================================= */

    private static function apiKey(): string
    {
        return self::setting('googlePlacesApiKey');
    }

    private static function placeId(): string
    {
        return self::setting('googlePlaceId');
    }

    /** The panel may override the link; otherwise it is built from the place id. */
    private static function reviewsUrl(): string
    {
        $url = self::setting('googleReviewsUrl');

        if ($url !== '') {
            return $url;
        }

        $placeId = self::placeId();

        return $placeId === '' ? '' : self::REVIEWS_URL . rawurlencode($placeId);
    }

    private static function setting(string $key): string
    {
        if (!function_exists('setting')) {
            return '';
        }

        return trim((string) setting('integrations', $key, ''));
    }

    /** @return string|null the body, or null if the service was unreachable */
    private static function get(string $url): ?string
    {
        $headers = [
            'X-Goog-Api-Key: ' . self::apiKey(),
            'X-Goog-FieldMask: ' . self::FIELDS,
            'Accept: application/json',
        ];

        if (function_exists('curl_init')) {
            $ch = curl_init($url);

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => self::TIMEOUT,
                CURLOPT_CONNECTTIMEOUT => self::TIMEOUT,
            ]);

            $body = curl_exec($ch);
            curl_close($ch);

            return $body === false ? null : (string) $body;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => self::TIMEOUT,
                /* The error body carries the reason the call failed, and it is
                   worth logging rather than throwing away as a warning. */
                'ignore_errors' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);

        return $body === false ? null : $body;
    }
}
