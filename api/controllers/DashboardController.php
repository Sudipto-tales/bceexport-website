<?php

/**
 * The three screens that read across every table rather than one of them —
 * docs/07-api-contract.md §Support.
 *
 *     GET api/dashboard/summary   stat tiles, needs-attention, recent feeds
 *     GET api/activity            the audit trail, filtered
 *     GET api/search              global search across every collection
 *
 * None of them writes anything, and none of them has a resource entry: a
 * summary is not a record, and search is every record at once. That is why
 * this extends ApiController directly instead of ResourceController.
 *
 * `POST api/activity/{id}/revert` is in the contract and is deliberately not
 * here. ActivityLog::diff() stores what changed, not what a record was —
 * `summarise()` replaces long text with "1,240 characters" and an array with
 * "6 item(s)" so the log does not outgrow the content it describes. Reverting
 * from that would write the summary back into the record. What the panel can
 * honestly offer is the undo that already exists: a `delete` row links to
 * `POST api/{resource}/{id}/restore`, and an `update` row links to the record.
 * docs/php/06-decisions.md.
 */
class DashboardController extends ApiController
{
    /** Rows per page on the activity screen when the caller does not say. */
    private const ACTIVITY_PAGE = 25;

    /** Hits per collection in a global search. It is a jump list, not a report. */
    private const SEARCH_PER_ENTITY = 5;

    /* ---------------------------------------------------------
       GET api/dashboard/summary
       --------------------------------------------------------- */

    /**
     * Everything docs/03-page-specs.md §3 puts on the dashboard, in one
     * request. Six or seven round trips to build one screen is six or seven
     * chances for it to render in pieces.
     */
    public function summary(): never
    {
        $window = $this->monthWindow();

        Api::ok([
            'stats' => $this->stats($window),
            'attention' => $this->attention(),
            'recentEnquiries' => $this->recentEnquiries(),
            'recentActivity' => $this->activityRows($this->activityQuery([], [], 8)),
            'setup' => $this->setup(),
        ]);
    }

    /**
     * The four tiles, each with the change against the comparable period.
     *
     * Comparable is the point. On the 3rd of the month, this month holds three
     * days and last month held thirty-one, so a raw month-against-month
     * comparison shows every tile falling off a cliff for the first week of
     * every month. The previous window is therefore the same number of seconds,
     * ending one month earlier — month-to-date against month-to-date.
     *
     * Counts and totals need different comparisons, so `deltaOf` says which
     * each tile used rather than leaving the panel to guess.
     */
    private function stats(array $w): array
    {
        $enquiries = 'FROM enquiries WHERE deleted_at IS NULL AND status <> \'spam\' AND received_at ';

        /* Appointment requests are enquiries with source = appointment
           (docs/02-content-model.md §20), so the first tile counts them too.
           "Enquiries this month" that excluded half the month's enquiries
           would be the surprising reading, not the useful one. */
        $appointments = 'FROM enquiries WHERE deleted_at IS NULL AND status <> \'spam\''
            . ' AND source = \'appointment\' AND received_at ';

        return [
            $this->tile(
                'enquiries',
                'Enquiries this month',
                (int) db_scalar('SELECT COUNT(*) ' . $enquiries . '>= ?', [$w['start']]),
                (int) db_scalar('SELECT COUNT(*) ' . $enquiries . '>= ? AND received_at < ?', [$w['prevStart'], $w['prevEnd']]),
                'the same days last month'
            ),
            $this->tile(
                'appointmentRequests',
                'Appointment requests',
                (int) db_scalar('SELECT COUNT(*) ' . $appointments . '>= ?', [$w['start']]),
                (int) db_scalar('SELECT COUNT(*) ' . $appointments . '>= ? AND received_at < ?', [$w['prevStart'], $w['prevEnd']]),
                'the same days last month'
            ),
            /* A total, not a period count — so the comparison is how many were
               already live when the month began, and the delta reads as "four
               more posts than at the start of the month". A post with no
               published_at cannot be placed in time and is left out of the
               earlier figure rather than guessed at. */
            $this->tile(
                'publishedPosts',
                'Published posts',
                (int) db_scalar('SELECT COUNT(*) FROM posts WHERE deleted_at IS NULL AND status = \'published\''),
                (int) db_scalar(
                    'SELECT COUNT(*) FROM posts WHERE deleted_at IS NULL AND status = \'published\'
                        AND published_at IS NOT NULL AND published_at < ?',
                    [$w['start']]
                ),
                'the start of the month'
            ),
            /* A vacancy closes by going hidden or by its closing date passing,
               so "active" is both conditions and neither alone. */
            $this->tile(
                'activeVacancies',
                'Active vacancies',
                (int) db_scalar(
                    'SELECT COUNT(*) FROM jobs WHERE deleted_at IS NULL AND status = \'published\'
                        AND (closes_at IS NULL OR closes_at >= ?)',
                    [$w['today']]
                ),
                (int) db_scalar(
                    'SELECT COUNT(*) FROM jobs WHERE deleted_at IS NULL AND status = \'published\'
                        AND created_at < ? AND (closes_at IS NULL OR closes_at >= ?)',
                    [$w['start'], $w['startDate']]
                ),
                'the start of the month'
            ),
        ];
    }

    private function tile(string $key, string $label, int $value, int $previous, string $against): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'value' => $value,
            'previous' => $previous,
            'delta' => $value - $previous,
            /* Null rather than 0 when there is nothing to divide by: a chip
               reading "+100%" against a month with no enquiries at all says
               less than one reading "first this month". */
            'deltaPercent' => $previous === 0 ? null : (int) round((($value - $previous) / $previous) * 100),
            'deltaOf' => $against,
        ];
    }

    /**
     * The needs-attention card — docs/03-page-specs.md §3.
     *
     * Only what has something in it. A card listing four zeroes is four lines
     * of nothing on the first screen somebody sees every morning.
     */
    private function attention(): array
    {
        $items = [];
        $weekAgo = $this->utc('-7 days');

        /* Drafts across every collection that has a draft state, rather than a
           list of tables written out here: a resource added to the registry
           later should appear on this card without anyone remembering to. */
        $stale = [];
        $staleTotal = 0;

        foreach (ResourceRegistry::all() as $name => $_) {
            $r = ResourceRegistry::get($name);

            if (!$r['hasStatus'] || !in_array('draft', $r['statusValues'], true)) {
                continue;
            }

            $n = (int) db_scalar(
                'SELECT COUNT(*) FROM ' . $r['table'] . ' WHERE deleted_at IS NULL
                    AND status = \'draft\' AND (updated_at IS NULL OR updated_at < ?)',
                [$weekAgo]
            );

            if ($n > 0) {
                $stale[] = ['entity' => $name, 'count' => $n];
                $staleTotal += $n;
            }
        }

        if ($staleTotal > 0) {
            $items[] = [
                'key' => 'staleDrafts',
                'label' => $staleTotal === 1
                    ? '1 draft untouched for over a week'
                    : $staleTotal . ' drafts untouched for over a week',
                'count' => $staleTotal,
                'breakdown' => $stale,
            ];
        }

        $unanswered = (int) db_scalar(
            'SELECT COUNT(*) FROM enquiries WHERE deleted_at IS NULL AND status = \'new\''
        );

        if ($unanswered > 0) {
            $items[] = [
                'key' => 'unansweredEnquiries',
                'label' => $unanswered === 1 ? '1 enquiry with no reply' : $unanswered . ' enquiries with no reply',
                'count' => $unanswered,
                'entity' => 'enquiries',
                'query' => ['status' => 'new'],
            ];
        }

        $closing = (int) db_scalar(
            'SELECT COUNT(*) FROM jobs WHERE deleted_at IS NULL AND status = \'published\'
                AND closes_at IS NOT NULL AND closes_at >= ? AND closes_at <= ?',
            [$this->localDate(), $this->localDate('+7 days')]
        );

        if ($closing > 0) {
            $items[] = [
                'key' => 'closingVacancies',
                'label' => $closing === 1 ? '1 vacancy closes this week' : $closing . ' vacancies close this week',
                'count' => $closing,
                'entity' => 'jobs',
                'query' => ['closingWithinDays' => 7],
            ];
        }

        /* Alt text is what a screen reader has to work with and what an image
           search reads. Missing is a content problem, not a technical one,
           which is why it belongs on this card and not in a log. */
        $noAlt = (int) db_scalar(
            'SELECT COUNT(*) FROM media WHERE deleted_at IS NULL AND (alt IS NULL OR alt = \'\')'
        );

        if ($noAlt > 0) {
            $items[] = [
                'key' => 'mediaMissingAlt',
                'label' => $noAlt === 1 ? '1 image with no alt text' : $noAlt . ' images with no alt text',
                'count' => $noAlt,
                'entity' => 'media',
            ];
        }

        return $items;
    }

    /** The five most recent enquiries, in the shape the dashboard table draws. */
    private function recentEnquiries(): array
    {
        $rows = db_fetch_all(
            'SELECT public_id, name, email, subject, source, status, priority, received_at
               FROM enquiries
              WHERE deleted_at IS NULL AND status <> \'spam\'
              ORDER BY received_at DESC, id DESC
              LIMIT 5'
        );

        return array_map(static fn ($row) => [
            'id' => $row['public_id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'subject' => $row['subject'],
            'source' => $row['source'],
            'status' => $row['status'],
            'priority' => $row['priority'],
            'receivedAt' => iso_datetime($row['received_at']),
        ], $rows);
    }

    /**
     * The fresh-install checklist.
     *
     * `complete` is what the panel switches on: with every step done the card
     * is not rendered at all, and nobody who has been running the site for a
     * year is told to add their first doctor.
     */
    private function setup(): array
    {
        $steps = [
            [
                'key' => 'settings',
                'label' => 'Fill in the hospital\'s name and contact details',
                'href' => 'settings-general',
                /* `phones` is a repeater of {label, number} rather than one
                   string — a hospital has a reception line, an emergency line
                   and a line per department. One entry is enough to say the
                   step was done. */
                'done' => trim((string) setting('general', 'name', '')) !== ''
                    && count(array_filter(
                        (array) setting('contact', 'phones', []),
                        static fn ($entry) => trim((string) (is_array($entry) ? ($entry['number'] ?? '') : $entry)) !== ''
                    )) > 0,
            ],
            [
                'key' => 'departments',
                'label' => 'Publish your departments',
                'href' => 'departments',
                'done' => (int) db_scalar(
                    'SELECT COUNT(*) FROM departments WHERE deleted_at IS NULL AND status = \'published\''
                ) > 0,
            ],
            [
                'key' => 'doctors',
                'label' => 'Publish your consultants',
                'href' => 'doctors',
                'done' => (int) db_scalar(
                    'SELECT COUNT(*) FROM doctors WHERE deleted_at IS NULL AND status = \'published\''
                ) > 0,
            ],
        ];

        $remaining = array_filter($steps, static fn ($step) => !$step['done']);

        return [
            'complete' => $remaining === [],
            'steps' => $steps,
        ];
    }

    /* ---------------------------------------------------------
       GET api/activity
       --------------------------------------------------------- */

    /**
     * The audit trail — docs/03-page-specs.md §41.
     *
     *     ?user=usr-002&entity=doctors&action=update&from=&to=&q=&page=&pageSize=
     *
     * The filter dropdowns come back in `meta` rather than from a second
     * request, and they list only the users and collections that actually
     * appear in the log: a menu of all eighteen collections when six have ever
     * been touched is a menu of fifteen dead ends.
     */
    public function activity(): never
    {
        $page = max(1, (int) ($this->query('page') ?? 1));
        $pageSize = (int) ($this->query('pageSize') ?? self::ACTIVITY_PAGE);
        $pageSize = max(1, min(200, $pageSize));

        [$where, $params] = $this->activityFilters();

        $sql = 'FROM activity_log WHERE ' . implode(' AND ', $where);
        $total = (int) db_scalar('SELECT COUNT(*) ' . $sql, $params);

        $rows = db_fetch_all(
            'SELECT * ' . $sql . $this->activityOrder() . ' LIMIT ' . $pageSize
                . ' OFFSET ' . (($page - 1) * $pageSize),
            $params
        );

        Api::ok($this->activityRows($rows), [
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
            'users' => $this->activityUsers(),
            'entities' => array_column(
                db_fetch_all('SELECT DISTINCT entity FROM activity_log WHERE entity IS NOT NULL ORDER BY entity'),
                'entity'
            ),
            'actions' => array_column(
                db_fetch_all('SELECT DISTINCT action FROM activity_log ORDER BY action'),
                'action'
            ),
        ]);
    }

    /**
     * The ORDER BY for the activity screen's sortable headers.
     *
     * A whitelist, so `?sort=` never reaches SQL as anything but one of these
     * four column names. Newest first by default, because that is what a log
     * is; every ordering falls back to id so a page boundary cannot repeat or
     * skip a row when two entries share a timestamp — the seeded rows do, and
     * so will two writes in the same second.
     */
    private function activityOrder(): string
    {
        $columns = [
            'at' => 'created_at',
            'userName' => 'user_name',
            'action' => 'action',
            'entity' => 'entity',
        ];

        $key = (string) ($this->query('sort') ?? 'at');
        $column = $columns[$key] ?? 'created_at';
        $dir = strtolower((string) ($this->query('dir') ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

        return ' ORDER BY ' . $column . ' ' . $dir . ', id ' . $dir;
    }

    /**
     * @return array{0: string[], 1: array}
     */
    private function activityFilters(): array
    {
        $where = ['1 = 1'];
        $params = [];

        /* Callers name a user by the public id the panel shows, not by the
           row number this table stores.
           `userId` as well as the contract's `user`, because the panel's list
           controller sends a filter under the name of the field it filters on
           and the field on a log row is `userId`. Two spellings here is one
           less special case in api.js. */
        $user = trim((string) ($this->query('userId') ?? $this->query('user') ?? ''));

        if ($user !== '' && $user !== 'all') {
            $where[] = 'user_id = (SELECT id FROM users WHERE public_id = ?)';
            $params[] = $user;
        }

        foreach (['entity', 'action'] as $key) {
            $value = trim((string) ($this->query($key) ?? ''));

            if ($value !== '' && $value !== 'all') {
                $where[] = $key . ' = ?';
                $params[] = $value;
            }
        }

        /* The screen sends plain dates. Stored timestamps are UTC, so a day
           picked in Kolkata is not the same 24 hours the string names — the
           conversion is what stops "today" losing the last five and a half
           hours of yesterday's entries. */
        $from = trim((string) ($this->query('from') ?? ''));

        if ($from !== '') {
            $where[] = 'created_at >= ?';
            $params[] = $this->utc($from . ' 00:00:00');
        }

        $to = trim((string) ($this->query('to') ?? ''));

        if ($to !== '') {
            $where[] = 'created_at <= ?';
            $params[] = $this->utc($to . ' 23:59:59');
        }

        /* The screen offers "today / last 7 days / last 30 days" rather than
           two date pickers, which is the same shape as the registry's
           `withinDays` filter and is the reason it is spelled the same way. A
           window of 1 is today, from local midnight — not the last 24 hours. */
        $within = (int) ($this->query('withinDays') ?? 0);

        if ($within > 0) {
            $where[] = 'created_at >= ?';
            $params[] = $this->utc('today -' . ($within - 1) . ' days');
        }

        $q = trim((string) ($this->query('q') ?? ''));

        if ($q !== '') {
            $where[] = '(summary LIKE ? OR user_name LIKE ? OR entity_id LIKE ?)';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }

        return [$where, $params];
    }

    /** The log, with no filters, for the dashboard's recently-edited feed. */
    private function activityQuery(array $where, array $params, int $limit): array
    {
        $sql = 'SELECT * FROM activity_log' . ($where ? ' WHERE ' . implode(' AND ', $where) : '');

        return db_fetch_all($sql . ' ORDER BY created_at DESC, id DESC LIMIT ' . $limit, $params);
    }

    /**
     * A log row as the panel reads it.
     *
     * `userName` is the name stored on the row, not one looked up now. The
     * whole point of this table is that it still answers "who changed the
     * emergency number" after that account is gone — a join would print an
     * empty cell for exactly the entries that matter most. `userId` is still
     * returned, so a row whose author still exists can link to them.
     *
     * `revertable` is what the screen's Restore action switches on. Only a
     * delete can be undone, and only through the collection's own restore
     * endpoint; see this class's docblock for why an update cannot.
     */
    private function activityRows(array $rows): array
    {
        if (!$rows) {
            return [];
        }

        $keys = array_column(
            db_fetch_all(
                'SELECT id, public_id FROM users WHERE id IN ('
                    . implode(',', array_fill(0, count($rows), '?')) . ')',
                array_map(static fn ($row) => (int) $row['user_id'], $rows)
            ),
            'public_id',
            'id'
        );

        $out = [];

        foreach ($rows as $row) {
            $entity = (string) ($row['entity'] ?? '');
            $entityId = (string) ($row['entity_id'] ?? '');

            $out[] = [
                'id' => (string) $row['id'],
                'userId' => $keys[(int) $row['user_id']] ?? null,
                'userName' => $row['user_name'],
                'action' => $row['action'],
                'entity' => $entity === '' ? null : $entity,
                'entityId' => $entityId === '' ? null : $entityId,
                'summary' => (string) ($row['summary'] ?? ''),
                'diff' => json_column($row['diff']),
                'ip' => $row['ip'],
                'at' => iso_datetime($row['created_at']),
                'revertable' => $row['action'] === 'delete'
                    && $entityId !== ''
                    && ResourceRegistry::has($entity),
            ];
        }

        return $out;
    }

    /** The distinct authors in the log, for the user filter. */
    private function activityUsers(): array
    {
        $rows = db_fetch_all(
            'SELECT DISTINCT a.user_id, a.user_name, u.public_id
               FROM activity_log a
               LEFT JOIN users u ON u.id = a.user_id
              WHERE a.user_id IS NOT NULL
              ORDER BY a.user_name'
        );

        $seen = [];
        $out = [];

        foreach ($rows as $row) {
            /* A renamed account appears once per name it has acted under.
               The filter needs one entry per account, under its current name
               where there still is one. */
            $id = $row['public_id'] ?? ('#' . $row['user_id']);

            if (isset($seen[$id])) {
                continue;
            }

            $seen[$id] = true;
            $out[] = ['id' => $id, 'name' => $row['user_name'] ?: 'Deleted account'];
        }

        return $out;
    }

    /* ---------------------------------------------------------
       GET api/search
       --------------------------------------------------------- */

    /**
     * The header's global search — every collection at once.
     *
     * Which columns are searched per collection is `search` in
     * config/resources.php, the same list the collection's own screen filters
     * on, so this cannot go looking in a column that no longer exists.
     *
     * One query per collection is twenty round trips to SQLite for a keystroke,
     * which is why there is a minimum length and a hard cap per collection.
     * This is a jump list: five doctors and a "see all" is the answer, and
     * anyone wanting the sixth wants the doctors screen.
     */
    public function search(): never
    {
        $q = trim((string) ($this->query('q') ?? ''));

        if (mb_strlen($q) < 2) {
            Api::ok([], ['q' => $q, 'total' => 0, 'truncated' => false]);
        }

        $groups = [];
        $total = 0;
        $truncated = false;

        foreach (ResourceRegistry::all() as $name => $_) {
            $r = ResourceRegistry::get($name);

            if (empty($r['search'])) {
                continue;
            }

            $clauses = [];
            $params = [];

            foreach ($r['search'] as $key) {
                $column = $r['fields'][$key]['column'] ?? ResourceRegistry::snake($key);

                if ($column === null) {
                    continue;   /* a join has no column to match against */
                }

                $clauses[] = 't.' . $column . ' LIKE ?';
                $params[] = '%' . $q . '%';
            }

            if (!$clauses) {
                continue;
            }

            $labelColumn = $r['fields'][$r['label']]['column'] ?? ResourceRegistry::snake($r['label']);

            $rows = db_fetch_all(
                'SELECT t.' . $r['key'] . ' AS k, t.' . $labelColumn . ' AS label'
                    . ($r['hasStatus'] ? ', t.status AS status' : '')
                    . ' FROM ' . $r['table'] . ' t'
                    . ' WHERE t.deleted_at IS NULL AND (' . implode(' OR ', $clauses) . ')'
                    . ' ORDER BY t.' . $labelColumn
                    /* One more than the cap, purely to know whether to say
                       "and more" — a second COUNT per collection would double
                       the queries to answer a question about five rows. */
                    . ' LIMIT ' . (self::SEARCH_PER_ENTITY + 1),
                $params
            );

            if (!$rows) {
                continue;
            }

            $more = count($rows) > self::SEARCH_PER_ENTITY;
            $rows = array_slice($rows, 0, self::SEARCH_PER_ENTITY);
            $truncated = $truncated || $more;
            $total += count($rows);

            $groups[] = [
                'entity' => $name,
                'more' => $more,
                'items' => array_map(static fn ($row) => [
                    'id' => $row['k'],
                    'label' => $row['label'],
                    'status' => $row['status'] ?? null,
                ], $rows),
            ];
        }

        /* Pages and media are not registry resources — one is a fixed set of
           section editors and the other has its own controller — but "contact"
           and a filename are exactly what somebody types into a search box. */
        foreach ($this->searchExtras($q) as $group) {
            $groups[] = $group;
            $total += count($group['items']);
            $truncated = $truncated || $group['more'];
        }

        Api::ok($groups, ['q' => $q, 'total' => $total, 'truncated' => $truncated]);
    }

    /** @return array<int,array{entity:string,more:bool,items:array}> */
    private function searchExtras(string $q): array
    {
        $out = [];
        $like = '%' . $q . '%';

        $pages = db_fetch_all(
            'SELECT slug, title, status FROM pages WHERE title LIKE ? OR slug LIKE ? ORDER BY title LIMIT ' . (self::SEARCH_PER_ENTITY + 1),
            [$like, $like]
        );

        if ($pages) {
            $more = count($pages) > self::SEARCH_PER_ENTITY;
            $out[] = [
                'entity' => 'pages',
                'more' => $more,
                'items' => array_map(static fn ($row) => [
                    'id' => $row['slug'],
                    'label' => $row['title'],
                    'status' => $row['status'],
                ], array_slice($pages, 0, self::SEARCH_PER_ENTITY)),
            ];
        }

        $media = db_fetch_all(
            'SELECT public_id, filename, alt FROM media
              WHERE deleted_at IS NULL AND (filename LIKE ? OR alt LIKE ? OR caption LIKE ?)
              ORDER BY filename LIMIT ' . (self::SEARCH_PER_ENTITY + 1),
            [$like, $like, $like]
        );

        if ($media) {
            $more = count($media) > self::SEARCH_PER_ENTITY;
            $out[] = [
                'entity' => 'media',
                'more' => $more,
                'items' => array_map(static fn ($row) => [
                    'id' => $row['public_id'],
                    'label' => $row['alt'] ?: $row['filename'],
                    'status' => null,
                ], array_slice($media, 0, self::SEARCH_PER_ENTITY)),
            ];
        }

        return $out;
    }

    /* ---------------------------------------------------------
       Time
       --------------------------------------------------------- */

    /**
     * This month so far, and the same span one month earlier.
     *
     * Every boundary is a local one converted to UTC, because the columns hold
     * UTC (now_iso()) and the hospital thinks in Asia/Kolkata. A month that
     * began at UTC midnight would put five and a half hours of the previous
     * month's enquiries into this month's tile.
     *
     * @return array{start:string,prevStart:string,prevEnd:string,today:string,startDate:string}
     */
    private function monthWindow(): array
    {
        $local = new DateTimeZone(date_default_timezone_get());
        $now = new DateTimeImmutable('now', $local);
        $start = $now->setDate((int) $now->format('Y'), (int) $now->format('n'), 1)->setTime(0, 0, 0);
        $prevStart = $start->modify('-1 month');

        return [
            'start' => $this->toUtc($start),
            'prevStart' => $this->toUtc($prevStart),
            /* The same elapsed time, not the same calendar day — comparing
               the 31st against a February that has none would otherwise drop a
               day of history on the floor. */
            'prevEnd' => $this->toUtc($prevStart->add($start->diff($now))),
            'today' => $now->format('Y-m-d'),
            'startDate' => $start->format('Y-m-d'),
        ];
    }

    /** A local expression ('-7 days', '2026-08-01 00:00:00') as a stored UTC timestamp. */
    private function utc(string $expression): string
    {
        return $this->toUtc(new DateTimeImmutable($expression, new DateTimeZone(date_default_timezone_get())));
    }

    /** A local date, for the DATE columns that hold one (jobs.closes_at). */
    private function localDate(string $expression = 'now'): string
    {
        return (new DateTimeImmutable($expression, new DateTimeZone(date_default_timezone_get())))->format('Y-m-d');
    }

    private function toUtc(DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private function query(string $key, mixed $default = null): mixed
    {
        return ApiRequest::query($key, $default);
    }
}
