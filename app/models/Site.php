<?php

/**
 * Site model helpers — the query layer for BCE Export public pages.
 *
 * Every function here reads from the `settings` table or returns a sensible
 * default. This fills the gap left when the codebase was ported from the
 * Teresa Hospital project — that project had its own model layer; this one
 * starts fresh with exactly what BCE Export needs.
 */

/* ---------------------------------------------------------
   Settings
   --------------------------------------------------------- */

if (!function_exists('setting')) {
    /**
     * Read a single setting value.
     *
     * @param string $group   The setting_group column
     * @param string $key     The setting_key column
     * @param mixed  $default Returned when the row does not exist
     */
    function setting(string $group, string $key, $default = null)
    {
        static $cache = [];

        $cacheKey = $group . '.' . $key;

        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        try {
            $row = db_fetch_one(
                'SELECT setting_value FROM settings WHERE setting_group = ? AND setting_key = ?',
                [$group, $key]
            );
        } catch (Throwable $e) {
            /* Table may not exist yet (fresh install before migrate). */
            return $default;
        }

        if (!$row) {
            $cache[$cacheKey] = $default;
            return $default;
        }

        $value = $row['setting_value'];

        /* Settings are stored as JSON-encoded values. */
        $decoded = json_decode($value, true);
        $result = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $value;

        $cache[$cacheKey] = $result;
        return $result;
    }
}

if (!function_exists('settings_group')) {
    /**
     * All settings in a group as key => value.
     */
    function settings_group(string $group): array
    {
        static $cache = [];

        if (isset($cache[$group])) {
            return $cache[$group];
        }

        try {
            $rows = db_fetch_all(
                'SELECT setting_key, setting_value FROM settings WHERE setting_group = ?',
                [$group]
            );
        } catch (Throwable $e) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            $decoded = json_decode($row['setting_value'], true);
            $result[$row['setting_key']] = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $row['setting_value'];
        }

        $cache[$group] = $result;
        return $result;
    }
}

/* ---------------------------------------------------------
   Site contact helpers
   --------------------------------------------------------- */

if (!function_exists('site_primary_phone')) {
    /**
     * The primary phone as ['number' => display, 'digits' => tel: safe].
     */
    function site_primary_phone(): array
    {
        $phone = (string) setting('contact', 'phone', '+91 8900379037');
        return [
            'number' => $phone,
            'digits' => site_digits($phone),
        ];
    }
}

if (!function_exists('site_primary_email')) {
    function site_primary_email(): string
    {
        return (string) setting('contact', 'email', 'admin@bceexport.com');
    }
}

if (!function_exists('site_address_lines')) {
    /**
     * The address as an array of display lines.
     */
    function site_address_lines(): array
    {
        $address = (string) setting('contact', 'address', 'Kolkata, West Bengal, India');
        return array_filter(array_map('trim', explode(',', $address)));
    }
}

if (!function_exists('site_digits')) {
    /**
     * Strip a phone string to digits only (for tel: links).
     */
    function site_digits(string $phone): string
    {
        return preg_replace('/[^0-9+]/', '', $phone);
    }
}

if (!function_exists('site_url')) {
    /**
     * Turn a stored path into a full URL. Falls back to $fallback when empty.
     */
    function site_url(string $path, string $fallback = ''): string
    {
        if ($path === '') {
            return $fallback;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return base_url(ltrim($path, '/'));
    }
}

/* ---------------------------------------------------------
   Navigation helpers (stubs — not needed for BCE Export
   public pages, but called by SiteController/AdminController)
   --------------------------------------------------------- */

if (!function_exists('nav_for_location')) {
    /** Navigation items for a footer location. Returns empty for now. */
    function nav_for_location(string $location): array
    {
        return [];
    }
}

if (!function_exists('departments_for_menu')) {
    /** Not applicable to BCE Export — returns empty array. */
    function departments_for_menu(): array
    {
        return [];
    }
}

/* ---------------------------------------------------------
   SEO helpers
   --------------------------------------------------------- */

if (!function_exists('seo_for')) {
    /**
     * SEO meta for an entity. Returns null when no custom SEO is stored.
     */
    function seo_for(string $entityType, string $entityId): ?array
    {
        return null;
    }
}

/* ---------------------------------------------------------
   Category & Product helpers
   --------------------------------------------------------- */

if (!function_exists('get_categories')) {
    /**
     * All published product categories, ordered by sort_order.
     */
    function get_categories(): array
    {
        try {
            return db_fetch_all(
                'SELECT * FROM categories WHERE status = ? AND deleted_at IS NULL ORDER BY sort_order ASC',
                ['published']
            );
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('get_category_by_slug')) {
    function get_category_by_slug(string $slug): ?array
    {
        try {
            $row = db_fetch_one(
                'SELECT * FROM categories WHERE slug = ? AND status = ? AND deleted_at IS NULL',
                [$slug, 'published']
            );
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('get_products_by_category')) {
    function get_products_by_category(int $categoryId): array
    {
        try {
            return db_fetch_all(
                'SELECT * FROM products WHERE category_id = ? AND status = ? AND deleted_at IS NULL ORDER BY sort_order ASC',
                [$categoryId, 'published']
            );
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('get_team_members')) {
    function get_team_members(): array
    {
        try {
            return db_fetch_all(
                'SELECT * FROM team_members WHERE status = ? AND deleted_at IS NULL ORDER BY sort_order ASC',
                ['published']
            );
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('get_testimonials')) {
    function get_testimonials(): array
    {
        try {
            return db_fetch_all(
                'SELECT * FROM testimonials WHERE status = ? AND deleted_at IS NULL ORDER BY sort_order ASC',
                ['published']
            );
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('get_certificates')) {
    function get_certificates(): array
    {
        try {
            return db_fetch_all(
                'SELECT * FROM certificates WHERE status = ? AND deleted_at IS NULL ORDER BY sort_order ASC',
                ['published']
            );
        } catch (Throwable $e) {
            return [];
        }
    }
}
