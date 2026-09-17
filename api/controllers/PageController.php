<?php

/**
 * The eight fixed public pages — docs/07-api-contract.md §Site pages.
 *
 *     GET   api/pages                          the index screen
 *     GET   api/pages/{slug}                   one page with its sections
 *     PATCH api/pages/{slug}                   title, status, sections, SEO
 *     POST  api/pages/{slug}/sections/reorder  {keys: []}
 *
 * Not a block builder, deliberately. Every page has a fixed layout and a
 * purpose-built editor screen; `section_key` is what ties a stored row to the
 * component that renders it (docs/php/02-schema.md §12). So a PATCH edits the
 * sections a page already has, and a key nobody recognises is refused rather
 * than quietly creating a ninth block no template draws.
 *
 * There is no create and no delete here for the same reason — a page exists
 * because a template and a route exist for it, which is a deploy, not a form.
 *
 * The panel reads a page as one record with `sections` on it
 * (assets/admin/js/core/util.js `applySections`), so that is the shape
 * this returns and accepts, whatever the two tables underneath look like.
 */
class PageController extends ApiController
{
    /** The `seo_meta.entity_type` these rows hang their meta off. */
    private const SEO_TYPE = 'page';

    private const STATUSES = ['draft', 'published', 'hidden'];

    public function index(): never
    {
        $raws = db_fetch_all(
            'SELECT * FROM pages WHERE deleted_at IS NULL ORDER BY title, id'
        );

        $ids = array_map(static fn ($raw) => (int) $raw['id'], $raws);
        $sections = $this->sectionsFor($ids);
        $seo = SeoMeta::read(self::SEO_TYPE, $ids);
        $rows = [];

        foreach ($raws as $raw) {
            $rows[] = $this->row($raw, $sections, $seo);
        }

        Api::ok($rows, ['page' => 1, 'pageSize' => 0, 'total' => count($rows)]);
    }

    public function show(): never
    {
        $raw = $this->find((string) $this->param('id'));

        if (!$raw) {
            Api::notFound();
        }

        Api::ok($this->row($raw));
    }

    public function update(): never
    {
        $slug = (string) $this->param('id');
        $raw = $this->find($slug);

        if (!$raw) {
            Api::notFound();
        }

        $id = (int) $raw['id'];
        $body = $this->body();
        $before = $this->row($raw);

        /* Same optimistic-concurrency rule as every other PATCH: enforced when
           the caller sends updatedAt, not demanded when it does not. */
        if (!empty($body['updatedAt']) && $body['updatedAt'] !== ($before['updatedAt'] ?? null)) {
            Api::conflict('Somebody else saved this page while you were editing — reload to see their version');
        }

        $fields = [];
        $columns = [];

        /* The slug is the page's identity, not a field of it: every editor
           screen asks for its own page by name (`store.get('pages','home')`)
           and every template is bound to one. Renaming one would be renaming
           a file. */
        if (array_key_exists('id', $body) && (string) $body['id'] !== $slug) {
            $fields['id'] = 'A page slug cannot be changed';
        }

        if (array_key_exists('title', $body)) {
            $title = trim((string) $body['title']);

            if ($title === '') {
                $fields['title'] = 'Required';
            }

            $columns['title'] = $title;
        }

        if (array_key_exists('path', $body)) {
            $path = trim((string) $body['path']);

            if ($path === '') {
                $fields['path'] = 'Required';
            }

            $columns['path'] = $path;
        }

        if (array_key_exists('status', $body)) {
            if (!in_array($body['status'], self::STATUSES, true)) {
                $fields['status'] = 'Not one of the allowed values';
            } else {
                $columns['status'] = $body['status'];
            }
        }

        $sections = $this->readSections($id, $body, $fields);

        if ($fields) {
            Api::validationFailed($fields);
        }

        $columns['updated_at'] = now_iso();
        $columns['updated_by'] = $this->userId();

        db_transaction(function () use ($id, $columns, $sections) {
            $set = implode(', ', array_map(static fn ($c) => $c . ' = ?', array_keys($columns)));

            db_execute(
                'UPDATE pages SET ' . $set . ' WHERE id = ?',
                array_merge(array_values($columns), [$id])
            );

            foreach ($sections as $section) {
                db_execute(
                    'UPDATE page_sections SET enabled = ?, sort_order = ?, data = ?,
                            label = ?, updated_at = ?, updated_by = ? WHERE id = ?',
                    [
                        $section['enabled'],
                        $section['sort_order'],
                        $section['data'],
                        $section['label'],
                        now_iso(),
                        $columns['updated_by'],
                        $section['id'],
                    ]
                );
            }
        });

        SeoMeta::write(self::SEO_TYPE, $id, $body);

        $after = $this->row($this->find($slug));

        $published = ($after['status'] ?? null) === 'published'
            && ($before['status'] ?? null) !== 'published';

        ActivityLog::record(
            $published ? 'publish' : 'update',
            'pages',
            $slug,
            (string) ($after['title'] ?? $slug),
            ActivityLog::diff($this->loggable($before), $this->loggable($after))
        );

        Api::ok($after);
    }

    /**
     * Drag-to-reorder, as its own endpoint.
     *
     * Separate from the PATCH because it is a different act: the section
     * editor saves a whole page, and moving a card should not require the rest
     * of the form to be valid, or send it.
     */
    public function reorderSections(): never
    {
        $slug = (string) $this->param('id');
        $raw = $this->find($slug);

        if (!$raw) {
            Api::notFound();
        }

        $keys = $this->body()['keys'] ?? [];

        if (!is_array($keys) || !$keys) {
            Api::validationFailed(['keys' => 'Send the section keys in their new order']);
        }

        $known = $this->sectionKeys((int) $raw['id']);
        $unknown = array_values(array_diff(array_map('strval', $keys), $known));

        if ($unknown) {
            Api::validationFailed(['keys' => 'This page has no section called ' . implode(', ', $unknown)]);
        }

        db_transaction(function () use ($raw, $keys) {
            foreach (array_values($keys) as $i => $key) {
                db_execute(
                    'UPDATE page_sections SET sort_order = ?, updated_at = ?
                     WHERE page_id = ? AND section_key = ?',
                    [$i + 1, now_iso(), (int) $raw['id'], $key]
                );
            }
        });

        ActivityLog::record('update', 'pages', $slug, 'Reordered ' . count($keys) . ' section(s)');

        Api::noContent();
    }

    /* ---------------------------------------------------------
       Reading
       --------------------------------------------------------- */

    private function find(string $slug): ?array
    {
        return db_fetch_one('SELECT * FROM pages WHERE slug = ? AND deleted_at IS NULL', [$slug]) ?: null;
    }

    /**
     * One page in the shape the panel edits.
     *
     * $sections and $seo are passed in by the index, which reads both for the
     * whole set in one query each; a single page fetches its own.
     */
    private function row(array $raw, ?array $sections = null, ?array $seo = null): array
    {
        $id = (int) $raw['id'];
        $sections ??= $this->sectionsFor([$id]);
        $seo ??= SeoMeta::read(self::SEO_TYPE, [$id]);

        return [
            'id' => $raw['slug'],
            'title' => $raw['title'],
            'path' => $raw['path'],
            'status' => $raw['status'],
            'sections' => $sections[$id] ?? [],
        ] + ($seo[$id] ?? SeoMeta::blank()) + [
            'createdAt' => iso_datetime($raw['created_at'] ?? null),
            'updatedAt' => iso_datetime($raw['updated_at'] ?? null),
            'updatedBy' => user_display_name($raw['updated_by'] ?? null),
        ];
    }

    /**
     * Every section of every page named, in one query.
     *
     * @param int[] $ids
     * @return array<int, array<int, array>> page id → sections, in order
     */
    private function sectionsFor(array $ids): array
    {
        if (!$ids) {
            return [];
        }

        $in = implode(',', array_fill(0, count($ids), '?'));

        $rows = db_fetch_all(
            'SELECT * FROM page_sections WHERE page_id IN (' . $in . ') AND deleted_at IS NULL'
            . ' ORDER BY sort_order, id',
            $ids
        );

        $out = [];

        foreach ($rows as $row) {
            $out[(int) $row['page_id']][] = [
                'key' => $row['section_key'],
                'label' => $row['label'],
                /* Not a publish state — a switched-off section is a block the
                   page keeps and does not draw. */
                'enabled' => (bool) $row['enabled'],
                'order' => (int) $row['sort_order'],
                'data' => json_column($row['data']),
                'updatedAt' => iso_datetime($row['updated_at'] ?? null),
            ];
        }

        return $out;
    }

    /** @return string[] */
    private function sectionKeys(int $pageId): array
    {
        return array_map(
            static fn ($row) => (string) $row['section_key'],
            db_fetch_all(
                'SELECT section_key FROM page_sections WHERE page_id = ? AND deleted_at IS NULL',
                [$pageId]
            )
        );
    }

    /* ---------------------------------------------------------
       Writing
       --------------------------------------------------------- */

    /**
     * The `sections` array of a PATCH, turned into one update per row.
     *
     * A section the body does not mention is left exactly as it is, and a key
     * the page does not have is refused: the panel sends back the list it was
     * given, so an unknown key is a bug in a form, not a new block.
     *
     * `data` is merged rather than replaced. Every editor screen renders a
     * subset of a section's fields — page-home.js posts a hero's title and
     * lead but not its image — and a replace would blank whatever that screen
     * happens not to show.
     *
     * @param array<string,string> $fields collected validation problems
     * @return array<int, array{id:int, enabled:int, sort_order:int, data:string, label:?string}>
     */
    private function readSections(int $pageId, array $body, array &$fields): array
    {
        if (!array_key_exists('sections', $body)) {
            return [];
        }

        if (!is_array($body['sections'])) {
            $fields['sections'] = 'Expected a list of sections';
            return [];
        }

        $existing = [];

        foreach (db_fetch_all(
            'SELECT * FROM page_sections WHERE page_id = ? AND deleted_at IS NULL',
            [$pageId]
        ) as $row) {
            $existing[(string) $row['section_key']] = $row;
        }

        $out = [];
        $unknown = [];
        $position = 0;

        foreach ($body['sections'] as $section) {
            $position++;

            if (!is_array($section)) {
                continue;
            }

            $key = (string) ($section['key'] ?? '');
            $row = $existing[$key] ?? null;

            if (!$row) {
                $unknown[] = $key === '' ? '(unnamed)' : $key;
                continue;
            }

            $data = array_key_exists('data', $section) && is_array($section['data'])
                ? array_merge(json_column($row['data']), $section['data'])
                : json_column($row['data']);

            $out[] = [
                'id' => (int) $row['id'],
                'enabled' => array_key_exists('enabled', $section)
                    ? (filter_var($section['enabled'], FILTER_VALIDATE_BOOL) ? 1 : 0)
                    : (int) $row['enabled'],
                /* The order sent, not the order stored: the list arrives in
                   the order the cards sit on screen. */
                'sort_order' => (int) ($section['order'] ?? $position),
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'label' => array_key_exists('label', $section)
                    ? (trim((string) $section['label']) ?: null)
                    : $row['label'],
            ];
        }

        if ($unknown) {
            $fields['sections'] = 'This page has no section called ' . implode(', ', $unknown);
        }

        return $out;
    }

    /**
     * The record as the activity log should read it.
     *
     * Sections are dropped to a count. A diff of twelve nested blobs is a
     * wall of JSON that answers nothing; that the page was saved, by whom, and
     * which of its own fields moved is the part somebody comes back for.
     */
    private function loggable(array $row): array
    {
        $row['sections'] = array_map(
            static fn ($section) => $section['key'] . ($section['enabled'] ? '' : ' (hidden)'),
            $row['sections'] ?? []
        );

        return $row;
    }

    private function userId(): ?int
    {
        $id = Auth::id();

        return $id === null ? null : (int) $id;
    }
}
