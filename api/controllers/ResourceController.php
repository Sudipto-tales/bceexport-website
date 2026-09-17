<?php

/**
 * One controller for all eighteen CRUD resources.
 *
 * Everything that differs between a doctor and a redirect is in
 * config/resources.php. What is left — the shape of a list response, how a
 * partial patch is applied, what a refused delete says — is identical for all
 * of them, and is here.
 *
 * Twenty near-identical controllers would drift: one would forget to exclude
 * soft-deleted rows, another would sort by a column the caller named. There is
 * one of each of those decisions in this file.
 *
 * Implements docs/07-api-contract.md:
 *
 *     GET    api/{resource}                  list, filtered, paged
 *     GET    api/{resource}/{id}             one record
 *     POST   api/{resource}                  create
 *     PATCH  api/{resource}/{id}             partial update
 *     DELETE api/{resource}/{id}             soft delete, or 409 HAS_DEPENDENTS
 *     POST   api/{resource}/{id}/restore     undo that delete
 *     POST   api/{resource}/reorder          {ids: []}
 *     POST   api/{resource}/bulk             {ids, action, payload}
 */
class ResourceController extends ApiController
{
    /**
     * Protected, not private, so a purpose-built controller for one of these
     * resources can answer in exactly the shape the generic one does.
     * EnquiryController's reply and note endpoints return an enquiry, and an
     * enquiry that came back from /api/enquiries/{id}/reply with different
     * field names from /api/enquiries/{id} would be a second contract to keep
     * in step.
     */
    protected array $resource;

    /** The one message that turns a 422 into a 409. */
    private const TAKEN = 'Already in use';

    /* ---------------------------------------------------------
       Endpoints
       --------------------------------------------------------- */

    public function index(): never
    {
        $r = $this->resource();

        $page = max(1, (int) ($this->query('page') ?? 1));
        $pageSize = (int) ($this->query('pageSize') ?? 20);
        $pageSize = $pageSize === 0 ? 0 : max(1, min(200, $pageSize));

        [$where, $params] = $this->conditions($r);

        /* The status histogram behind the filter chips is counted with every
           other filter applied but not the status filter itself — a chip
           reading "Draft 3" has to mean three drafts in the set on screen.
           Same rule as the mock store it replaces. */
        $counts = $this->counts($r, $where, $params);

        if ($r['hasStatus']) {
            $status = $this->query('status');
            if ($status !== null && $status !== '' && $status !== 'all') {
                $where[] = 't.status = ?';
                $params[] = $status;
            }
        }

        $sql = 'FROM ' . $r['table'] . ' t WHERE ' . implode(' AND ', $where);
        $total = (int) db_scalar('SELECT COUNT(*) ' . $sql, $params);

        $order = $this->orderBy($r);
        $limit = '';

        if ($pageSize > 0) {
            $limit = ' LIMIT ' . $pageSize . ' OFFSET ' . (($page - 1) * $pageSize);
        }

        $rows = db_fetch_all('SELECT t.* ' . $sql . $order . $limit, $params);

        Api::ok(
            $this->rows($r, $rows),
            ['page' => $page, 'pageSize' => $pageSize, 'total' => $total, 'counts' => $counts]
        );
    }

    public function show(): never
    {
        $r = $this->resource();
        $row = $this->find($r, (string) $this->param('id'), true);

        if (!$row) {
            Api::notFound();
        }

        Api::ok($this->row($r, $row));
    }

    public function store(): never
    {
        $r = $this->resource();

        if (!$r['canCreate']) {
            Api::forbidden('Records of this kind are received, not created here');
        }

        $body = $this->body();
        $fields = [];

        $key = $this->publicKey($r, $body, null, $fields);
        $status = $this->statusFor($r, $body, 'draft', $fields);
        $columns = $this->columns($r, $body, $fields);

        $this->requireForPublish($r, $status, $body, [], $fields);
        $this->checkUnique($r, $body, null, $fields);

        $this->reject($fields);

        $columns[$r['key']] = $key;

        if ($r['hasStatus']) {
            $columns['status'] = $status;
        }

        /* New records go to the end. MAX + 1 rather than COUNT + 1, so a
           deleted row does not hand its position to the next one created. */
        $columns['sort_order'] = $body['order']
            ?? ((int) db_scalar('SELECT MAX(sort_order) FROM ' . $r['table']) + 1);

        $columns['created_at'] = now_iso();
        $columns['updated_at'] = now_iso();
        $columns['updated_by'] = $this->userId();

        $id = $this->insert($r, $columns);
        $this->writeJoins($r, $id, $body);
        $this->writeSeo($r, $id, $body);

        $row = $this->find($r, $key, true);

        ActivityLog::record('create', $r['name'], $key, $this->describe($r, $row));

        Api::created($this->row($r, $row));
    }

    public function update(): never
    {
        $r = $this->resource();

        if ($r['readonly']) {
            Api::forbidden('This record is read-only');
        }

        $id = (string) $this->param('id');
        $existing = $this->find($r, $id, true);

        if (!$existing) {
            Api::notFound();
        }

        $body = $this->body();
        $fields = [];
        $before = $this->row($r, $existing);

        /* Optimistic concurrency. The contract asks for updatedAt on every
           patch; it is enforced when sent and not demanded when it is not,
           because a panel screen that forgets it should fail to overwrite
           somebody, not fail to save at all. */
        if (!empty($body['updatedAt']) && $body['updatedAt'] !== ($before['updatedAt'] ?? null)) {
            Api::conflict('Somebody else saved this while you were editing — reload to see their version');
        }

        $status = $r['hasStatus']
            ? $this->statusFor($r, $body, $existing['status'], $fields)
            : null;

        $columns = $this->columns($r, $body, $fields, true);

        $this->requireForPublish($r, $status, $body, $before, $fields);
        $this->checkUnique($r, $body, $existing['id'], $fields);

        $key = $existing[$r['key']];

        /* Changing the key changes the public URL. Allowed — a slug is often
           wrong on the first try — but it must not collide, and the redirects
           table is what keeps the old URL alive. */
        if (array_key_exists('id', $body) && (string) $body['id'] !== (string) $key) {
            $key = $this->publicKey($r, $body, $existing['id'], $fields);
            $columns[$r['key']] = $key;
        }

        $this->reject($fields);

        if ($r['hasStatus']) {
            $columns['status'] = $status;
        }

        if (array_key_exists('order', $body)) {
            $columns['sort_order'] = (int) $body['order'];
        }

        $columns['updated_at'] = now_iso();
        $columns['updated_by'] = $this->userId();

        $this->updateRow($r, (int) $existing['id'], $columns);
        $this->writeJoins($r, (int) $existing['id'], $body);
        $this->writeSeo($r, (int) $existing['id'], $body);

        $row = $this->find($r, $key, true);
        $after = $this->row($r, $row);

        /* Going live is worth telling apart from an edit — it is the change
           somebody asks about later. */
        $published = ($after['status'] ?? null) === 'published'
            && ($before['status'] ?? null) !== 'published';

        ActivityLog::record(
            $published ? 'publish' : 'update',
            $r['name'],
            $key,
            $this->describe($r, $row),
            ActivityLog::diff($before, $after)
        );

        Api::ok($after);
    }

    public function destroy(): never
    {
        $r = $this->resource();

        if ($r['readonly']) {
            Api::forbidden('This record is read-only');
        }

        $id = (string) $this->param('id');
        $row = $this->find($r, $id, true);

        if (!$row) {
            Api::notFound();
        }

        $force = filter_var($this->query('force') ?? 'false', FILTER_VALIDATE_BOOL);
        $dependents = $this->dependents($r, (int) $row['id']);

        if ($dependents && !$force) {
            Api::hasDependents($dependents);
        }

        /* Soft. The panel offers Undo on the toast, and a hard delete makes
           that a lie. The row leaves every list the moment deleted_at is set. */
        db_execute(
            'UPDATE ' . $r['table'] . ' SET deleted_at = ?, updated_at = ?, updated_by = ? WHERE id = ?',
            [now_iso(), now_iso(), $this->userId(), $row['id']]
        );

        ActivityLog::record('delete', $r['name'], $id, $this->describe($r, $row));

        Api::noContent();
    }

    public function restore(): never
    {
        $r = $this->resource();

        if ($r['readonly']) {
            Api::forbidden('This record is read-only');
        }

        $id = (string) $this->param('id');
        $row = $this->find($r, $id, false);

        if (!$row) {
            Api::notFound();
        }

        db_execute(
            'UPDATE ' . $r['table'] . ' SET deleted_at = NULL, updated_at = ?, updated_by = ? WHERE id = ?',
            [now_iso(), $this->userId(), $row['id']]
        );

        $restored = $this->find($r, $id, true);

        ActivityLog::record('restore', $r['name'], $id, $this->describe($r, $restored));

        Api::ok($this->row($r, $restored));
    }

    public function reorder(): never
    {
        $r = $this->resource();

        if ($r['readonly']) {
            Api::forbidden('This record is read-only');
        }

        $ids = $this->body()['ids'] ?? [];

        if (!is_array($ids) || !$ids) {
            Api::validationFailed(['ids' => 'Send the ids in their new order']);
        }

        db_transaction(function () use ($r, $ids) {
            foreach (array_values($ids) as $i => $id) {
                db_execute(
                    'UPDATE ' . $r['table'] . ' SET sort_order = ?, updated_at = ? WHERE ' . $r['key'] . ' = ?',
                    [$i + 1, now_iso(), $id]
                );
            }
        });

        ActivityLog::record('update', $r['name'], null, 'Reordered ' . count($ids) . ' record(s)');

        Api::noContent();
    }

    /**
     * Bulk actions report partial failure honestly rather than swallowing it
     * — see the bulk toast rule in docs/04-crud-flows.md. Nine of ten deleting
     * is not a success, and it is not a failure either.
     */
    public function bulk(): never
    {
        $r = $this->resource();

        if ($r['readonly']) {
            Api::forbidden('This record is read-only');
        }

        $body = $this->body();
        $ids = $body['ids'] ?? [];
        $action = $body['action'] ?? '';
        $payload = $body['payload'] ?? [];

        if (!is_array($ids) || !$ids) {
            Api::validationFailed(['ids' => 'Nothing was selected']);
        }

        if (!in_array($action, ['publish', 'hide', 'delete', 'patch'], true)) {
            Api::validationFailed(['action' => 'Not something that can be done in bulk']);
        }

        $succeeded = [];
        $failed = [];

        foreach ($ids as $id) {
            $row = $this->find($r, (string) $id, true);

            if (!$row) {
                $failed[] = ['id' => $id, 'reason' => 'Not found'];
                continue;
            }

            if ($action === 'delete') {
                $dependents = $this->dependents($r, (int) $row['id']);

                if ($dependents) {
                    $failed[] = ['id' => $id, 'reason' => 'In use by ' . count($dependents) . ' record(s)'];
                    continue;
                }

                db_execute(
                    'UPDATE ' . $r['table'] . ' SET deleted_at = ?, updated_at = ?, updated_by = ? WHERE id = ?',
                    [now_iso(), now_iso(), $this->userId(), $row['id']]
                );
                $succeeded[] = $id;
                continue;
            }

            if ($action === 'publish' || $action === 'hide') {
                if (!$r['hasStatus']) {
                    $failed[] = ['id' => $id, 'reason' => 'These records have no published state'];
                    continue;
                }

                $target = $action === 'publish' ? 'published' : 'hidden';
                $missing = [];

                if ($target === 'published') {
                    $this->requireForPublish($r, 'published', [], $this->row($r, $row), $missing);
                }

                if ($missing) {
                    $failed[] = ['id' => $id, 'reason' => 'Missing ' . implode(', ', array_keys($missing))];
                    continue;
                }

                db_execute(
                    'UPDATE ' . $r['table'] . ' SET status = ?, updated_at = ?, updated_by = ? WHERE id = ?',
                    [$target, now_iso(), $this->userId(), $row['id']]
                );
                $succeeded[] = $id;
                continue;
            }

            $fields = [];
            $columns = $this->columns($r, is_array($payload) ? $payload : [], $fields, true);

            if ($fields) {
                $failed[] = ['id' => $id, 'reason' => implode('; ', $fields)];
                continue;
            }

            if ($columns) {
                $columns['updated_at'] = now_iso();
                $columns['updated_by'] = $this->userId();
                $this->updateRow($r, (int) $row['id'], $columns);
            }

            $succeeded[] = $id;
        }

        ActivityLog::record(
            $action === 'delete' ? 'delete' : 'update',
            $r['name'],
            null,
            ucfirst($action) . ' on ' . count($succeeded) . ' of ' . count($ids) . ' record(s)'
        );

        Api::ok(['succeeded' => $succeeded, 'failed' => $failed]);
    }

    /* ---------------------------------------------------------
       Resolving the resource
       --------------------------------------------------------- */

    protected function resource(): array
    {
        if (isset($this->resource)) {
            return $this->resource;
        }

        $name = (string) $this->param('resource');
        $resource = ResourceRegistry::get($name);

        if (!$resource) {
            Api::notFound('No such collection');
        }

        /* Read-only means read-only. The route table sends every verb to this
           controller, so the refusal lives here rather than in eight route
           entries that could be added to later without noticing. */
        if ($resource['readonly'] && ApiRequest::method() !== HttpMethod::GET) {
            Api::forbidden('Appointment records are read-only — the site takes no bookings');
        }

        return $this->resource = $resource;
    }

    private function query(string $key, mixed $default = null): mixed
    {
        return ApiRequest::query($key, $default);
    }

    protected function userId(): ?int
    {
        $user = Auth::user();
        return isset($user['id']) ? (int) $user['id'] : null;
    }

    /* ---------------------------------------------------------
       Reading
       --------------------------------------------------------- */

    protected function find(array $r, string $id, bool $liveOnly): ?array
    {
        $sql = 'SELECT * FROM ' . $r['table'] . ' WHERE ' . $r['key'] . ' = ?';

        if ($liveOnly) {
            $sql .= ' AND deleted_at IS NULL';
        }

        return db_fetch_one($sql, [$id]) ?: null;
    }

    /**
     * The WHERE clause for a list, minus the status filter.
     *
     * Every value reaching SQL is a bound parameter, and every column name is
     * one the registry named — a caller cannot introduce either.
     *
     * @return array{0: string[], 1: array}
     */
    private function conditions(array $r): array
    {
        $where = ['t.deleted_at IS NULL'];
        $params = [];

        $q = trim((string) ($this->query('q') ?? ''));

        if ($q !== '' && !empty($r['search'])) {
            $clauses = [];

            foreach ($r['search'] as $key) {
                $column = $r['fields'][$key]['column'] ?? ResourceRegistry::snake($key);
                $clauses[] = 't.' . $column . ' LIKE ?';
                $params[] = '%' . $q . '%';
            }

            $where[] = '(' . implode(' OR ', $clauses) . ')';
        }

        foreach ($r['filters'] as $filter) {
            $value = $this->query($filter['name']);

            if ($value === null || $value === '' || $value === 'all') {
                continue;
            }

            switch ($filter['type']) {
                case 'join':
                    $field = $r['fields'][$filter['field']];
                    $target = ResourceRegistry::get($field['target']);

                    $where[] = 't.id IN (SELECT j.' . $field['local'] . ' FROM ' . $field['table'] . ' j'
                        . ' JOIN ' . $target['table'] . ' g ON g.id = j.' . $field['foreign']
                        . ' WHERE g.' . $target['key'] . ' = ?)';
                    $params[] = $value;
                    break;

                case 'ref':
                    $target = ResourceRegistry::get($filter['target']);
                    $where[] = 't.' . $filter['column'] . ' = (SELECT id FROM ' . $target['table']
                        . ' WHERE ' . $target['key'] . ' = ?)';
                    $params[] = $value;
                    break;

                case 'bool':
                    $where[] = 't.' . $filter['column'] . ' = ?';
                    $params[] = filter_var($value, FILTER_VALIDATE_BOOL) ? 1 : 0;
                    break;

                case 'dateFrom':
                    $where[] = 't.' . $filter['column'] . ' >= ?';
                    $params[] = $value;
                    break;

                case 'dateTo':
                    $where[] = 't.' . $filter['column'] . ' <= ?';
                    $params[] = $value . ' 23:59:59';
                    break;

                case 'withinDays':
                    $where[] = 't.' . $filter['column'] . ' BETWEEN ? AND ?';
                    $params[] = gmdate('Y-m-d');
                    $params[] = gmdate('Y-m-d', time() + ((int) $value * 86400));
                    break;

                /* The mirror image: a window that reaches backwards, for a log
                   or an inbox. 1 is today from local midnight, not the last 24
                   hours — the timestamp is stored UTC and the person reading it
                   is in Kolkata, so a raw subtraction loses the small hours of
                   this morning. */
                case 'daysBack':
                    $days = max(1, (int) $value);
                    $where[] = 't.' . $filter['column'] . ' >= ?';
                    $params[] = gmdate('Y-m-d H:i:s', strtotime('today -' . ($days - 1) . ' days'));
                    break;

                /* today / upcoming / past, against a plain date column. The
                   appointments archive asks nothing else of a date, and giving
                   it a picker would suggest a booking screen it is not. */
                case 'when':
                    $today = date('Y-m-d');
                    $operator = ['today' => '=', 'upcoming' => '>', 'past' => '<'][$value] ?? null;

                    if ($operator === null) {
                        break;
                    }

                    $where[] = 't.' . $filter['column'] . ' ' . $operator . ' ?';
                    $params[] = $today;
                    break;

                default:
                    $where[] = 't.' . $filter['column'] . ' = ?';
                    $params[] = $value;
            }
        }

        return [$where, $params];
    }

    private function counts(array $r, array $where, array $params): array
    {
        $sql = 'FROM ' . $r['table'] . ' t WHERE ' . implode(' AND ', $where);
        $counts = ['all' => (int) db_scalar('SELECT COUNT(*) ' . $sql, $params)];

        if (!$r['hasStatus']) {
            return $counts;
        }

        foreach (db_fetch_all('SELECT t.status, COUNT(*) AS n ' . $sql . ' GROUP BY t.status', $params) as $row) {
            $counts[$row['status']] = (int) $row['n'];
        }

        return $counts;
    }

    /**
     * The ORDER BY a caller who named no sort gets.
     *
     * Not every table has a hand-ordered position. An appointment is not
     * dragged into place; it has a date. So the fallback is the resource's own
     * first sortable key, which check-resources has already proved exists,
     * rather than a sort_order column that may not.
     */
    protected function defaultOrder(array $r): string
    {
        $fallback = $r['defaultSort']
            ?? (in_array('order', $r['sort'] ?? [], true) ? 'order' : ($r['sort'][0] ?? 'id'));

        $column = ResourceRegistry::sortColumn($r, $fallback) ?? $r['key'];
        $dir = strtolower((string) ($r['defaultDir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';

        return ' ORDER BY t.' . $column . ' ' . $dir . ', t.id ASC';
    }

    private function orderBy(array $r): string
    {
        $fallback = $r['defaultSort']
            ?? (in_array('order', $r['sort'] ?? [], true) ? 'order' : ($r['sort'][0] ?? 'id'));

        $key = (string) ($this->query('sort') ?? $fallback);
        $column = ResourceRegistry::sortColumn($r, $key)
            ?? ResourceRegistry::sortColumn($r, $fallback)
            ?? $r['key'];

        /* Whitelisted above; anything unlisted silently becomes the default
           order rather than an error, because a stale sort in a bookmarked
           URL should not be a broken screen. */
        $dir = strtolower((string) ($this->query('dir') ?? $r['defaultDir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';

        return ' ORDER BY t.' . $column . ' ' . $dir . ', t.id ASC';
    }

    /* ---------------------------------------------------------
       Database row → API row
       --------------------------------------------------------- */

    protected function rows(array $r, array $dbRows): array
    {
        if (!$dbRows) {
            return [];
        }

        $ids = array_map(static fn ($row) => (int) $row['id'], $dbRows);
        $joins = $this->readJoins($r, $ids);
        $seo = $this->readSeo($r, $ids);
        $out = [];

        foreach ($dbRows as $dbRow) {
            $out[] = $this->row($r, $dbRow, $joins, $seo);
        }

        return $out;
    }

    protected function row(array $r, array $dbRow, ?array $joins = null, ?array $seo = null): array
    {
        $id = (int) $dbRow['id'];
        $joins ??= $this->readJoins($r, [$id]);
        $seo ??= $this->readSeo($r, [$id]);

        $out = [
            'id' => $dbRow[$r['key']],
            'order' => (int) ($dbRow['sort_order'] ?? 0),
        ];

        if ($r['hasStatus']) {
            $out['status'] = $dbRow['status'] ?? 'draft';
        }

        foreach ($r['fields'] as $field) {
            if ($field['writeonly']) {
                continue;
            }

            if ($field['type'] === 'join') {
                $out[$field['name']] = $joins[$field['name']][$id] ?? [];
                continue;
            }

            $out[$field['name']] = $this->cast($field, $dbRow[$field['column']] ?? null);
        }

        foreach ($seo[$id] ?? [] as $name => $value) {
            $out[$name] = $value;
        }

        $out['createdAt'] = $this->iso($dbRow['created_at'] ?? null);
        $out['updatedAt'] = $this->iso($dbRow['updated_at'] ?? null);
        $out['updatedBy'] = $this->userName($dbRow['updated_by'] ?? null);

        if (!empty($dbRow['deleted_at'])) {
            $out['deletedAt'] = $this->iso($dbRow['deleted_at']);
        }

        return $this->decorate($r, $out, $dbRow);
    }

    /**
     * The one seam in an otherwise data-driven row.
     *
     * A field that is neither a column nor a join cannot be expressed in
     * config/resources.php — `cvUrl` is the route that streams a file, built
     * from the record's own key. Rather than teach the registry about computed
     * fields for the one resource that needs one, the subclass that already
     * exists for that resource adds it here.
     */
    protected function decorate(array $r, array $row, array $dbRow): array
    {
        return $row;
    }

    private function cast(array $field, mixed $value): mixed
    {
        if ($value === null) {
            return match ($field['type']) {
                'json' => [],
                'csv' => '',
                'bool' => false,
                default => null,
            };
        }

        return match ($field['type']) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => (bool) $value,
            'json' => json_column($value),
            /* Stored as an array; the form edits it as "a, b, c". */
            'csv' => implode(', ', json_column($value)),
            'datetime' => $this->iso($value),
            'ref' => $this->refKey($field['target'], (int) $value),
            default => $value,
        };
    }

    /** 'Y-m-d H:i:s' in UTC → the ISO 8601 the panel reads. */
    private function iso(?string $value): ?string
    {
        return iso_datetime($value);
    }

    /**
     * An integer foreign key back to the public key it stands for.
     *
     * Cached per request: a listing of twenty posts asks for the same handful
     * of authors over and over.
     */
    private function refKey(string $resourceName, ?int $id): ?string
    {
        static $cache = [];

        if (!$id) {
            return null;
        }

        if (isset($cache[$resourceName][$id])) {
            return $cache[$resourceName][$id];
        }

        $target = ResourceRegistry::get($resourceName);

        if (!$target) {
            return null;
        }

        $key = db_scalar('SELECT ' . $target['key'] . ' FROM ' . $target['table'] . ' WHERE id = ?', [$id]);

        return $cache[$resourceName][$id] = ($key === false ? null : $key);
    }

    private function userName(mixed $id): ?string
    {
        return user_display_name($id);
    }

    /**
     * Every join for a whole page of rows in one query each.
     *
     * @return array<string, array<int, string[]>>  field → row id → keys
     */
    private function readJoins(array $r, array $ids): array
    {
        $out = [];

        foreach ($r['fields'] as $field) {
            if ($field['type'] !== 'join' || !$ids) {
                continue;
            }

            $target = ResourceRegistry::get($field['target']);
            $in = implode(',', array_fill(0, count($ids), '?'));

            $rows = db_fetch_all(
                'SELECT j.' . $field['local'] . ' AS owner, g.' . $target['key'] . ' AS k'
                . ' FROM ' . $field['table'] . ' j'
                . ' JOIN ' . $target['table'] . ' g ON g.id = j.' . $field['foreign']
                . ' WHERE j.' . $field['local'] . ' IN (' . $in . ')'
                . ' ORDER BY j.id',
                $ids
            );

            $map = [];

            foreach ($rows as $row) {
                $map[(int) $row['owner']][] = $row['k'];
            }

            $out[$field['name']] = $map;
        }

        return $out;
    }

    /**
     * SEO lives in its own polymorphic table but the form edits it as two more
     * fields on the record, so it is merged in on the way out and split off on
     * the way in. Both halves are in core/SeoMeta.php, because the fixed pages
     * carry the same fields and have no registry entry to reach them through.
     */
    private function readSeo(array $r, array $ids): array
    {
        return $r['seo'] ? SeoMeta::read($r['seo'], $ids) : [];
    }

    /* ---------------------------------------------------------
       API row → database columns
       --------------------------------------------------------- */

    /**
     * @param array<string,string> $fields  collected validation problems
     */
    private function columns(array $r, array $body, array &$fields, bool $partial = false): array
    {
        $columns = [];

        foreach ($r['fields'] as $field) {
            if ($field['type'] === 'join' || $field['readonly']) {
                continue;
            }

            if (!array_key_exists($field['name'], $body)) {
                /* A PATCH says nothing about the fields it omits. A POST gets
                   the registry's default, or nothing at all. */
                if (!$partial && array_key_exists('default', $field)) {
                    $columns[$field['column']] = $this->encode($field, $field['default'], $fields);
                }
                continue;
            }

            $columns[$field['column']] = $this->encode($field, $body[$field['name']], $fields);
        }

        /* A password change gets a date of its own. `updated_at` cannot stand
           in for it — that moves when somebody corrects a phone number — and
           the users screen prints "Changed 3 months ago" from this. Only the
           one resource declares a password field, so this cannot fire
           elsewhere, and it is stamped here rather than sent, because a client
           that decides when its own password was last changed is a client that
           can say "just now" forever. */
        if (array_key_exists('password', $columns)) {
            $columns['password_updated_at'] = now_iso();
        }

        return $columns;
    }

    private function encode(array $field, mixed $value, array &$fields): mixed
    {
        if (!empty($field['enum']) && $value !== null && $value !== '') {
            if (!in_array($value, $field['enum'], true) && !in_array((string) $value, array_map('strval', $field['enum']), true)) {
                $fields[$field['name']] = 'Not one of the allowed values';
                return null;
            }
        }

        return match ($field['type']) {
            /* '' becomes null, never 0 — an empty consultation fee is unknown,
               not free. */
            'int' => ($value === '' || $value === null) ? null : (int) $value,
            'float' => ($value === '' || $value === null) ? null : (float) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOL) ? 1 : 0,
            'json' => json_encode(is_array($value) ? $value : []),
            'csv' => json_encode(is_array($value)
                ? $value
                : array_values(array_filter(array_map('trim', explode(',', (string) $value))))),
            'datetime' => $this->fromIso($value),
            'date' => ($value === '' || $value === null) ? null : substr((string) $value, 0, 10),
            'ref' => $this->refId($field, $value, $fields),
            'password' => ($value === '' || $value === null) ? null : Auth::hash((string) $value),
            default => ($value === '' ? null : $value),
        };
    }

    private function fromIso(mixed $value): ?string
    {
        if ($value === '' || $value === null) {
            return null;
        }

        $time = strtotime((string) $value);

        return $time === false ? null : gmdate('Y-m-d H:i:s', $time);
    }

    private function refId(array $field, mixed $value, array &$fields): ?int
    {
        if ($value === '' || $value === null) {
            return null;
        }

        $target = ResourceRegistry::get($field['target']);

        if (!$target) {
            return null;
        }

        $id = db_scalar(
            'SELECT id FROM ' . $target['table'] . ' WHERE ' . $target['key'] . ' = ?',
            [$value]
        );

        if ($id === false || $id === null) {
            $fields[$field['name']] = 'No such record';
            return null;
        }

        return (int) $id;
    }

    /* ---------------------------------------------------------
       Validation
       --------------------------------------------------------- */

    /**
     * Ends the request if anything was collected.
     *
     * A taken slug or a taken email is a 409 and not a 422: the contract
     * separates "you sent something wrong" from "somebody already has that",
     * and the panel shows a different message for each. Both carry the same
     * `fields` map, so the red line lands under the right input either way.
     */
    private function reject(array $fields): void
    {
        if (!$fields) {
            return;
        }

        if (in_array(self::TAKEN, $fields, true)) {
            Api::conflict(
                count($fields) === 1 ? 'That value is already in use' : 'Some values are already in use',
                $fields
            );
        }

        Api::validationFailed($fields);
    }

    private function publicKey(array $r, array $body, ?int $exceptId, array &$fields): string
    {
        $key = trim((string) ($body['id'] ?? ''));

        if ($key === '') {
            /* Derived from whatever the resource calls its label, so creating
               a doctor does not mean inventing a slug by hand. */
            $key = str_slug((string) ($body[$r['label']] ?? ''));
        }

        if ($key === '') {
            $fields['id'] = 'Required';
            return '';
        }

        $sql = 'SELECT COUNT(*) FROM ' . $r['table'] . ' WHERE ' . $r['key'] . ' = ?';
        $params = [$key];

        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }

        if ((int) db_scalar($sql, $params) > 0) {
            $fields['id'] = self::TAKEN;
        }

        return $key;
    }

    private function statusFor(array $r, array $body, string $fallback, array &$fields): string
    {
        $status = (string) ($body['status'] ?? $fallback);

        if (!in_array($status, $r['statusValues'], true)) {
            $fields['status'] = 'Not one of the allowed values';
            return $fallback;
        }

        return $status;
    }

    /**
     * Required fields are checked on publish, not on save.
     *
     * A draft is a place to park a half-written record — docs/04-crud-flows.md
     * calls publish "stricter than draft" and that is the whole distinction.
     * Refusing to save an incomplete draft would make the draft state useless.
     *
     * A resource with no draft state has no such place to park, so for those
     * the check runs on every write. Otherwise a redirect or a nav item — both
     * live the moment they exist — could be saved pointing nowhere.
     *
     * The schema agrees with this: the columns listed in `required` are
     * nullable, because a NOT NULL on one of them would refuse the draft the
     * workflow is built around.
     */
    private function requireForPublish(array $r, ?string $status, array $body, array $existing, array &$fields): void
    {
        if ($r['hasStatus'] && $status !== 'published') {
            return;
        }

        foreach ($r['required'] as $name) {
            $value = array_key_exists($name, $body) ? $body[$name] : ($existing[$name] ?? null);

            if ($value === null || $value === '' || $value === []) {
                $fields[$name] = 'Required before publishing';
            }
        }
    }

    private function checkUnique(array $r, array $body, ?int $exceptId, array &$fields): void
    {
        foreach ($r['unique'] as $name) {
            if (!array_key_exists($name, $body) || $body[$name] === '') {
                continue;
            }

            $column = $r['fields'][$name]['column'] ?? ResourceRegistry::snake($name);
            $sql = 'SELECT COUNT(*) FROM ' . $r['table'] . ' WHERE ' . $column . ' = ?';
            $params = [$body[$name]];

            if ($exceptId !== null) {
                $sql .= ' AND id <> ?';
                $params[] = $exceptId;
            }

            if ((int) db_scalar($sql, $params) > 0) {
                $fields[$name] = self::TAKEN;
            }
        }
    }

    /**
     * What still points at this record, named.
     *
     * The panel renders this straight into the confirm dialog, so a list of
     * "3 records" is no use: somebody has to be able to go and fix them. Each
     * entry carries the dependent's own public key and its label — the article
     * title, the doctor's name — so the dialog reads like a to-do list.
     *
     * Two shapes:
     *
     *   resource     the dependent table is a resource in its own right; the
     *                rows pointing here are the records to name
     *   far          the dependent table is a join, so the interesting record
     *                is on its other side. Deleting a department names the
     *                doctors on its team, not eight anonymous link rows.
     *
     * A join row on its *own* record is not listed at all. A doctor's
     * department memberships cascade when the doctor goes; blocking on them
     * would mean unassigning a leaver from every team before they could be
     * removed, for no gain — the links vanish cleanly either way.
     *
     * Soft-deleted dependents do not count: a doctor whose only article is in
     * the bin is not in use.
     *
     * @return array<int,array{entity:string,id:string,label:string}>
     */
    private function dependents(array $r, int $id): array
    {
        $out = [];

        foreach ($r['dependents'] as $dep) {
            $target = ResourceRegistry::get($dep['farResource'] ?? $dep['resource'] ?? '');

            if (!$target) {
                continue;
            }

            if (isset($dep['far'])) {
                $sql = 'SELECT g.' . $target['key'] . ' AS k, g.'
                    . $this->labelColumn($target) . ' AS label'
                    . ' FROM ' . $dep['table'] . ' j'
                    . ' JOIN ' . $target['table'] . ' g ON g.id = j.' . $dep['far']
                    . ' WHERE j.' . $dep['column'] . ' = ?';

                if ($this->tableHasDeletedAt($target['table'])) {
                    $sql .= ' AND g.deleted_at IS NULL';
                }
            } else {
                $sql = 'SELECT ' . $target['key'] . ' AS k, ' . $this->labelColumn($target) . ' AS label'
                    . ' FROM ' . $dep['table'] . ' WHERE ' . $dep['column'] . ' = ?';

                if ($this->tableHasDeletedAt($dep['table'])) {
                    $sql .= ' AND deleted_at IS NULL';
                }
            }

            /* Capped. A department with forty enquiries against it does not
               need forty rows in a dialog to make the point. */
            foreach (db_fetch_all($sql . ' LIMIT 25', [$id]) as $row) {
                $out[] = [
                    'entity' => $target['name'],
                    'id' => (string) $row['k'],
                    'label' => ($row['label'] ?? $row['k']) . ' — ' . $dep['label'],
                ];
            }
        }

        return $out;
    }

    private function labelColumn(array $target): string
    {
        return $target['fields'][$target['label']]['column'] ?? ResourceRegistry::snake($target['label']);
    }

    private function tableHasDeletedAt(string $table): bool
    {
        static $cache = [];

        if (isset($cache[$table])) {
            return $cache[$table];
        }

        global $pdo;
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        $rows = $driver === 'sqlite'
            ? $pdo->query("PRAGMA table_info({$table})")->fetchAll()
            : $pdo->query("SHOW COLUMNS FROM {$table}")->fetchAll();

        $columns = array_map(static fn ($row) => $row['name'] ?? $row['Field'] ?? '', $rows);

        return $cache[$table] = in_array('deleted_at', $columns, true);
    }

    /* ---------------------------------------------------------
       Writing the parts that are not columns
       --------------------------------------------------------- */

    private function insert(array $r, array $columns): int
    {
        $names = array_keys($columns);

        db_execute(
            'INSERT INTO ' . $r['table'] . ' (' . implode(', ', $names) . ') VALUES ('
            . implode(', ', array_fill(0, count($names), '?')) . ')',
            array_values($columns)
        );

        global $pdo;

        return (int) $pdo->lastInsertId();
    }

    private function updateRow(array $r, int $id, array $columns): void
    {
        if (!$columns) {
            return;
        }

        $set = implode(', ', array_map(static fn ($c) => $c . ' = ?', array_keys($columns)));

        db_execute(
            'UPDATE ' . $r['table'] . ' SET ' . $set . ' WHERE id = ?',
            array_merge(array_values($columns), [$id])
        );
    }

    /**
     * Replace rather than reconcile.
     *
     * A join here is a short, ordered list a person picked in a multi-select.
     * Working out which rows to add and which to remove would cost more than
     * writing the four the form sent, and would lose the order they were
     * chosen in.
     */
    private function writeJoins(array $r, int $id, array $body): void
    {
        foreach ($r['fields'] as $field) {
            if ($field['type'] !== 'join' || !array_key_exists($field['name'], $body)) {
                continue;
            }

            $keys = is_array($body[$field['name']]) ? $body[$field['name']] : [];
            $target = ResourceRegistry::get($field['target']);

            db_execute('DELETE FROM ' . $field['table'] . ' WHERE ' . $field['local'] . ' = ?', [$id]);

            $position = 0;

            foreach ($keys as $key) {
                $targetId = db_scalar(
                    'SELECT id FROM ' . $target['table'] . ' WHERE ' . $target['key'] . ' = ?',
                    [$key]
                );

                if ($targetId === false || $targetId === null) {
                    continue;
                }

                $columns = [$field['local'] => $id, $field['foreign'] => (int) $targetId];

                if ($this->joinHasOrder($field['table'])) {
                    $columns['sort_order'] = $position++;
                }

                db_execute(
                    'INSERT INTO ' . $field['table'] . ' (' . implode(', ', array_keys($columns)) . ')'
                    . ' VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')',
                    array_values($columns)
                );
            }
        }
    }

    private function joinHasOrder(string $table): bool
    {
        static $cache = [];

        if (isset($cache[$table])) {
            return $cache[$table];
        }

        global $pdo;
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        $rows = $driver === 'sqlite'
            ? $pdo->query("PRAGMA table_info({$table})")->fetchAll()
            : $pdo->query("SHOW COLUMNS FROM {$table}")->fetchAll();

        $columns = array_map(static fn ($row) => $row['name'] ?? $row['Field'] ?? '', $rows);

        return $cache[$table] = in_array('sort_order', $columns, true);
    }

    private function writeSeo(array $r, int $id, array $body): void
    {
        if ($r['seo']) {
            SeoMeta::write($r['seo'], $id, $body);
        }
    }

    private function describe(array $r, ?array $dbRow): string
    {
        if (!$dbRow) {
            return '';
        }

        $label = $r['fields'][$r['label']]['column'] ?? ResourceRegistry::snake($r['label']);

        return (string) ($dbRow[$label] ?? $dbRow[$r['key']] ?? '');
    }
}
