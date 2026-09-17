<?php

/**
 * What points at a file — computed, never stored.
 *
 * `media` has no `used_by` column on purpose (docs/php/02-schema.md §11): a
 * stored back-reference is wrong the moment any record holding a media URL is
 * edited by something that forgets to update it, and a wrong one is worse than
 * none, because it is what a delete confirmation is trusted with.
 *
 * So this scans the columns that can hold a media URL, once per request. It is
 * needed on exactly one screen and one dialog, and the whole scan is eight
 * small queries.
 *
 * ---------------------------------------------------------------------------
 * Matching is by *file*, not by string.
 *
 * The seeded library is a set of Unsplash URLs, and the site asks for a 500px
 * rendition of the portrait the gallery holds at 1600px — same photo,
 * different query string. Comparing the URLs as text would report every
 * seeded image as unused and offer to delete it. Same rule as the panel's own
 * scan in assets/admin/js/pages/gallery.js.
 * ---------------------------------------------------------------------------
 */
final class MediaUsage
{
    /**
     * Every table column that can hold a media URL.
     *
     * Adding an image field to a form means adding it here too. The
     * alternative is a delete that silently breaks a page — which is the one
     * failure this file exists to prevent.
     *
     * `jobs` is absent on purpose: the panel's prototype listed `jobs.image`,
     * but the table has no such column (database/migrations/019_JobsTable.php)
     * and a vacancy carries no picture.
     */
    private const SOURCES = [
        ['entity' => 'doctors', 'table' => 'doctors', 'key' => 'slug', 'label' => 'name', 'columns' => ['photo' => 'photo']],
        ['entity' => 'leadership', 'table' => 'leadership', 'key' => 'slug', 'label' => 'name', 'columns' => ['photo' => 'photo']],
        ['entity' => 'departments', 'table' => 'departments', 'key' => 'slug', 'label' => 'name', 'columns' => ['banner' => 'banner', 'intro_img' => 'intro image']],
        ['entity' => 'posts', 'table' => 'posts', 'key' => 'slug', 'label' => 'title', 'columns' => ['cover_image' => 'cover image']],
        ['entity' => 'testimonials', 'table' => 'testimonials', 'key' => 'public_id', 'label' => 'name', 'columns' => ['photo' => 'photo']],
        ['entity' => 'facilities', 'table' => 'facilities', 'key' => 'slug', 'label' => 'title', 'columns' => ['image' => 'image']],
    ];

    /** Settings that hold a media URL, by group and key. */
    private const SETTINGS = [
        ['group' => 'general', 'key' => 'logo', 'label' => 'Header logo'],
        ['group' => 'general', 'key' => 'logoDark', 'label' => 'Dark-theme logo'],
        ['group' => 'general', 'key' => 'favicon', 'label' => 'Favicon'],
        ['group' => 'social', 'key' => 'shareImage', 'label' => 'Share image'],
        ['group' => 'popups', 'key' => 'adsImage', 'label' => 'Ads popup image'],
    ];

    /**
     * file key → the records using it.
     *
     * @return array<string, array<int, array{entity: string, id: string, label: string}>>
     */
    public static function map(): array
    {
        static $map = null;

        if ($map !== null) {
            return $map;
        }

        $map = [];

        foreach (self::SOURCES as $source) {
            $columns = array_keys($source['columns']);

            $rows = db_fetch_all(
                'SELECT ' . $source['key'] . ' AS k, ' . $source['label'] . ' AS label, '
                . implode(', ', $columns)
                . ' FROM ' . $source['table'] . ' WHERE deleted_at IS NULL'
            );

            foreach ($rows as $row) {
                foreach ($source['columns'] as $column => $what) {
                    self::add($map, $row[$column] ?? null, [
                        'entity' => $source['entity'],
                        'id' => (string) $row['k'],
                        'label' => ($row['label'] ?? $row['k']) . ' — ' . $what,
                    ]);
                }
            }
        }

        self::addSections($map);
        self::addSettings($map);

        return $map;
    }

    /**
     * @return array<int, array{entity: string, id: string, label: string}>
     */
    public static function forUrl(?string $url): array
    {
        return self::map()[self::fileKey($url)] ?? [];
    }

    /**
     * The identity of the picture behind a URL.
     *
     * An Unsplash id where there is one, the URL minus its query string
     * otherwise — so `?w=500` and `?w=1600` of the same photo are one file,
     * and two different uploads never collide.
     */
    public static function fileKey(?string $url): string
    {
        $url = (string) $url;

        if (preg_match('/photo-([A-Za-z0-9_-]+)/', $url, $m)) {
            return $m[1];
        }

        return explode('?', $url)[0];
    }

    /* --------------------------------------------------------- */

    /**
     * Page sections nest their content one level deep and every page editor is
     * a different screen with different field names, so this walks the values
     * rather than naming the fields — a new section's image field is covered
     * the day it is added.
     */
    private static function addSections(array &$map): void
    {
        $rows = db_fetch_all(
            'SELECT p.slug AS page, p.title AS page_title, s.label, s.section_key, s.data
             FROM page_sections s JOIN pages p ON p.id = s.page_id
             WHERE s.deleted_at IS NULL AND p.deleted_at IS NULL'
        );

        foreach ($rows as $row) {
            foreach (json_column($row['data']) as $value) {
                if (!is_string($value) || !preg_match('#^(https?:|/|\.\./|data:image)#', $value)) {
                    continue;
                }

                self::add($map, $value, [
                    'entity' => 'pages',
                    'id' => (string) $row['page'],
                    'label' => $row['page_title'] . ' — ' . ($row['label'] ?: $row['section_key']),
                ]);
            }
        }
    }

    private static function addSettings(array &$map): void
    {
        $settings = function_exists('all_settings') ? all_settings() : [];

        foreach (self::SETTINGS as $entry) {
            $value = $settings[$entry['group']][$entry['key']] ?? null;

            if (is_string($value)) {
                self::add($map, $value, [
                    'entity' => 'settings',
                    'id' => $entry['group'],
                    'label' => $entry['label'],
                ]);
            }
        }
    }

    private static function add(array &$map, ?string $url, array $reference): void
    {
        if ($url === null || trim($url) === '') {
            return;
        }

        $map[self::fileKey($url)][] = $reference;
    }
}
