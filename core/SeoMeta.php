<?php

/**
 * The polymorphic SEO table, read and written as fields on the record it
 * belongs to.
 *
 * `seo_meta` is one table for doctors, departments, posts and pages
 * (docs/php/02-schema.md §14), so that the SEO screen is one query rather than a
 * four-way union. The forms that edit it do not know that: a doctor's editor
 * shows "Meta title" beside "Qualification" and posts them together. So the
 * columns are merged into the record on the way out and split off again on the
 * way in, and this is the one place that knows how.
 *
 * `entityType` is the registry's `seo` key — 'doctor', 'department', 'post' —
 * or 'page' for the fixed pages, which have no registry entry.
 */
final class SeoMeta
{
    /** API field → column. The whole contract between a form and this table. */
    private const MAP = [
        'metaTitle' => 'meta_title',
        'metaDescription' => 'meta_description',
        'ogImage' => 'og_image',
        'canonical' => 'canonical',
        'noindex' => 'noindex',
        'keywords' => 'keywords',
    ];

    /**
     * The fields with nothing in them.
     *
     * A record with no meta row still has the fields — an editor binds its SEO
     * inputs to them, and a missing key and an empty one should not render
     * differently.
     */
    public static function blank(): array
    {
        return [
            'metaTitle' => null,
            'metaDescription' => null,
            'ogImage' => null,
            'canonical' => null,
            'noindex' => false,
            'keywords' => null,
        ];
    }

    /**
     * The meta for a whole page of records, in one query.
     *
     * @param int[] $ids integer primary keys of the owning rows
     * @return array<int, array<string, mixed>> owner id → fields
     */
    public static function read(string $entityType, array $ids): array
    {
        if ($entityType === '' || !$ids) {
            return [];
        }

        $in = implode(',', array_fill(0, count($ids), '?'));

        $rows = db_fetch_all(
            'SELECT entity_id, ' . implode(', ', self::MAP) . ' FROM seo_meta'
            . ' WHERE entity_type = ? AND entity_id IN (' . $in . ')',
            array_merge([$entityType], $ids)
        );

        $out = [];

        foreach ($rows as $row) {
            $fields = [];

            foreach (self::MAP as $name => $column) {
                $fields[$name] = $name === 'noindex' ? (bool) $row[$column] : $row[$column];
            }

            $out[(int) $row['entity_id']] = $fields;
        }

        return $out;
    }

    /**
     * Writes whatever meta fields the body carries, and nothing else.
     *
     * A body with none of them leaves the row untouched rather than blanking
     * it — a PATCH that says nothing about a field is not a PATCH that clears
     * it, and most forms that reach here are not the SEO form.
     */
    public static function write(string $entityType, int $id, array $body): void
    {
        if ($entityType === '') {
            return;
        }

        $columns = [];

        foreach (self::MAP as $name => $column) {
            if (!array_key_exists($name, $body)) {
                continue;
            }

            $columns[$column] = $name === 'noindex'
                ? (filter_var($body[$name], FILTER_VALIDATE_BOOL) ? 1 : 0)
                : ($body[$name] === '' ? null : $body[$name]);
        }

        if (!$columns) {
            return;
        }

        $existing = db_scalar(
            'SELECT id FROM seo_meta WHERE entity_type = ? AND entity_id = ?',
            [$entityType, $id]
        );

        if ($existing) {
            $set = implode(', ', array_map(static fn ($c) => $c . ' = ?', array_keys($columns)));

            db_execute(
                'UPDATE seo_meta SET ' . $set . ', updated_at = ? WHERE id = ?',
                array_merge(array_values($columns), [now_iso(), $existing])
            );

            return;
        }

        $columns['entity_type'] = $entityType;
        $columns['entity_id'] = $id;
        $columns['created_at'] = now_iso();
        $columns['updated_at'] = now_iso();

        db_execute(
            'INSERT INTO seo_meta (' . implode(', ', array_keys($columns)) . ') VALUES ('
            . implode(', ', array_fill(0, count($columns), '?')) . ')',
            array_values($columns)
        );
    }
}
