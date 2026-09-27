<?php

/**
 * Site model helpers — the query layer for BCE Export public pages.
 *
 * Every function here reads from the `settings` table or returns a sensible
 * default for BCE Export.
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

if (!function_exists('all_settings')) {
    /**
     * All settings as group => [key => value].
     */
    function all_settings(bool $fresh = false): array
    {
        static $cache = null;

        if ($cache !== null && !$fresh) {
            return $cache;
        }

        try {
            $rows = db_fetch_all('SELECT setting_group, setting_key, setting_value FROM settings WHERE deleted_at IS NULL');
        } catch (Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $g = $row['setting_group'];
            $k = $row['setting_key'];
            $val = $row['setting_value'];
            $decoded = json_decode($val, true);
            $out[$g][$k] = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $val;
        }

        $cache = $out;
        return $out;
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
    function get_products_by_category($category): array
    {
        try {
            $catId = is_array($category) ? ($category['id'] ?? '') : (string) $category;
            $catSlug = is_array($category) ? ($category['slug'] ?? '') : (string) $category;

            return db_fetch_all(
                'SELECT * FROM products WHERE (category_id = ? OR category_id = ?) AND status = ? AND deleted_at IS NULL ORDER BY sort_order ASC',
                [(string) $catId, (string) $catSlug, 'published']
            );
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('get_products_by_category_paged')) {
    function get_products_by_category_paged($category, int $page = 1, int $perPage = 12): array
    {
        try {
            $catId = is_array($category) ? ($category['id'] ?? '') : (string) $category;
            $catSlug = is_array($category) ? ($category['slug'] ?? '') : (string) $category;

            $sqlCount = 'SELECT COUNT(*) FROM products WHERE (category_id = ? OR category_id = ?) AND status = ? AND deleted_at IS NULL';
            $total = (int) db_scalar($sqlCount, [(string) $catId, (string) $catSlug, 'published']);

            $perPage = max(1, min(24, $perPage));
            $page = max(1, $page);
            $totalPages = max(1, (int) ceil($total / $perPage));
            $offset = ($page - 1) * $perPage;

            $sqlRows = 'SELECT * FROM products WHERE (category_id = ? OR category_id = ?) AND status = ? AND deleted_at IS NULL ORDER BY sort_order ASC, id ASC LIMIT ' . $perPage . ' OFFSET ' . $offset;
            $products = db_fetch_all($sqlRows, [(string) $catId, (string) $catSlug, 'published']);

            return [
                'products' => $products,
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage,
                'totalPages' => $totalPages,
            ];
        } catch (Throwable $e) {
            return [
                'products' => [],
                'total' => 0,
                'page' => 1,
                'perPage' => $perPage,
                'totalPages' => 1,
            ];
        }
    }
}

/* ---------------------------------------------------------
   Blog helpers
   --------------------------------------------------------- */

if (!function_exists('get_blog_categories')) {
    function get_blog_categories(): array
    {
        try {
            return db_fetch_all(
                'SELECT * FROM blog_categories WHERE status = ? AND deleted_at IS NULL ORDER BY sort_order ASC',
                ['published']
            );
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('get_blog_posts')) {
    function get_blog_posts(int $page = 1, int $perPage = 9, ?string $categorySlug = null, ?string $query = null): array
    {
        try {
            $where = ['status = ?', 'deleted_at IS NULL'];
            $params = ['published'];

            if ($categorySlug !== null && $categorySlug !== '' && $categorySlug !== 'all') {
                $where[] = '(category_id = ? OR category_id = (SELECT slug FROM blog_categories WHERE slug = ?))';
                $params[] = $categorySlug;
                $params[] = $categorySlug;
            }

            if ($query !== null && trim($query) !== '') {
                $where[] = '(title LIKE ? OR excerpt LIKE ? OR body LIKE ?)';
                $q = '%' . trim($query) . '%';
                $params[] = $q;
                $params[] = $q;
                $params[] = $q;
            }

            $sqlWhere = implode(' AND ', $where);
            $total = (int) db_scalar("SELECT COUNT(*) FROM blog_posts WHERE {$sqlWhere}", $params);

            $perPage = max(1, min(24, $perPage));
            $page = max(1, $page);
            $totalPages = max(1, (int) ceil($total / $perPage));
            $offset = ($page - 1) * $perPage;

            $rows = db_fetch_all(
                "SELECT * FROM blog_posts WHERE {$sqlWhere} ORDER BY published_at DESC, id DESC LIMIT {$perPage} OFFSET {$offset}",
                $params
            );

            $cats = get_blog_categories();
            $catMap = [];
            foreach ($cats as $c) {
                $catMap[$c['slug']] = $c['name'];
            }

            foreach ($rows as &$r) {
                $r['category_name'] = $catMap[$r['category_id'] ?? ''] ?? 'General';
            }
            unset($r);

            return [
                'posts' => $rows,
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage,
                'totalPages' => $totalPages,
            ];
        } catch (Throwable $e) {
            return [
                'posts' => [],
                'total' => 0,
                'page' => 1,
                'perPage' => $perPage,
                'totalPages' => 1,
            ];
        }
    }
}

if (!function_exists('get_blog_post_by_slug')) {
    function get_blog_post_by_slug(string $slug): ?array
    {
        try {
            $post = db_fetch_one(
                'SELECT * FROM blog_posts WHERE slug = ? AND status = ? AND deleted_at IS NULL',
                [$slug, 'published']
            );

            if (!$post) {
                return null;
            }

            $cat = db_fetch_one('SELECT name FROM blog_categories WHERE slug = ? OR id = ?', [$post['category_id'] ?? '', $post['category_id'] ?? '']);
            $post['category_name'] = $cat ? $cat['name'] : 'General';

            return $post;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('get_related_blog_posts')) {
    function get_related_blog_posts(array $currentPost, int $limit = 4): array
    {
        try {
            $catId = $currentPost['category_id'] ?? '';
            $currentId = $currentPost['id'] ?? 0;
            $currentSlug = $currentPost['slug'] ?? '';

            $rows = db_fetch_all(
                'SELECT * FROM blog_posts WHERE status = ? AND deleted_at IS NULL AND category_id = ? AND id != ? AND slug != ? ORDER BY published_at DESC LIMIT ' . $limit,
                ['published', $catId, $currentId, $currentSlug]
            );

            // Fallback if not enough posts in same category: fetch latest published posts
            if (count($rows) < $limit) {
                $needed = $limit - count($rows);
                $excludeIds = array_merge([$currentId], array_column($rows, 'id'));
                $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
                $more = db_fetch_all(
                    "SELECT * FROM blog_posts WHERE status = ? AND deleted_at IS NULL AND id NOT IN ({$placeholders}) ORDER BY published_at DESC LIMIT " . $needed,
                    array_merge(['published'], $excludeIds)
                );
                $rows = array_merge($rows, $more);
            }

            $cats = get_blog_categories();
            $catMap = [];
            foreach ($cats as $c) {
                $catMap[$c['slug']] = $c['name'];
            }

            foreach ($rows as &$r) {
                $r['category_name'] = $catMap[$r['category_id'] ?? ''] ?? 'General';
            }
            unset($r);

            return $rows;
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

