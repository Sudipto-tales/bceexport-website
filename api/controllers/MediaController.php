<?php

/**
 * The media library — docs/07-api-contract.md §Media.
 *
 *     GET    api/media?folder=&type=&unused=&missingAlt=&q=
 *     POST   api/media                multipart/form-data
 *     GET    api/media/{id}/usage
 *     PATCH  api/media/{id}           {alt, caption, folder}
 *     DELETE api/media/{id}?force=false
 *     POST   api/media/{id}/restore
 *
 * Media is not in config/resources.php and cannot be: an upload is a file
 * before it is a row, `usedBy` is computed rather than stored, and a delete has
 * to answer for a file on disk as well as a record. So this is the one CRUD
 * screen with a controller of its own.
 *
 * Every row carries `usedBy`. The panel's prototype worked it out client-side
 * by loading every entity that can hold an image (gallery.js); against a real
 * database that would be eight list requests to render one screen, and the
 * server already has to know the answer to refuse a delete.
 */
class MediaController extends ApiController
{
    /** What `?type=` accepts, and what each means in terms of the MIME column. */
    private const TYPES = ['images', 'documents'];

    public function index(): never
    {
        $where = ['deleted_at IS NULL'];
        $params = [];

        $folder = trim((string) ($this->query('folder') ?? ''));

        if ($folder !== '' && $folder !== 'all') {
            $where[] = 'folder = ?';
            $params[] = $folder;
        }

        $type = (string) ($this->query('type') ?? '');

        if (in_array($type, self::TYPES, true)) {
            /* An empty mime is treated as an image: the seeded library is all
               pictures, and a row with nothing in the column is not a reason
               to hide it from the one filter it belongs in. */
            $where[] = $type === 'images'
                ? "(mime IS NULL OR mime = '' OR mime LIKE 'image/%')"
                : "(mime IS NOT NULL AND mime <> '' AND mime NOT LIKE 'image/%')";
        }

        if (filter_var($this->query('missingAlt') ?? 'false', FILTER_VALIDATE_BOOL)) {
            $where[] = "(alt IS NULL OR alt = '')";
        }

        $q = trim((string) ($this->query('q') ?? ''));

        if ($q !== '') {
            $where[] = '(filename LIKE ? OR alt LIKE ? OR caption LIKE ? OR folder LIKE ?)';
            array_push($params, "%{$q}%", "%{$q}%", "%{$q}%", "%{$q}%");
        }

        $sql = 'FROM media WHERE ' . implode(' AND ', $where);

        /* Newest first. There is no sort_order column — a media library is not
           dragged into an order, and the file somebody just uploaded is the
           one they are looking for. */
        $rows = db_fetch_all('SELECT * ' . $sql . ' ORDER BY created_at DESC, id DESC', $params);
        $rows = array_map(fn ($row) => $this->row($row), $rows);

        /* Applied last because it is the one filter the database cannot
           answer: "unused" is the result of the back-reference scan. */
        if (filter_var($this->query('unused') ?? 'false', FILTER_VALIDATE_BOOL)) {
            $rows = array_values(array_filter($rows, static fn ($row) => !$row['usedBy']));
        }

        $total = count($rows);
        $page = max(1, (int) ($this->query('page') ?? 1));
        $pageSize = (int) ($this->query('pageSize') ?? 0);
        $pageSize = $pageSize === 0 ? 0 : max(1, min(200, $pageSize));

        if ($pageSize > 0) {
            $rows = array_slice($rows, ($page - 1) * $pageSize, $pageSize);
        }

        Api::ok($rows, [
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
            'folders' => $this->folders(),
        ]);
    }

    /**
     * One upload, one row.
     *
     * multipart, not JSON — the panel posts a FormData with the file in
     * `file`. Several files are several requests, which is what
     * assets/admin/js/core/media.js already does, and it means one
     * rejected file does not take the others with it.
     */
    public function store(): never
    {
        $file = $_FILES['file'] ?? null;

        if (!is_array($file)) {
            Api::validationFailed(['file' => 'No file was sent']);
        }

        if (is_array($file['name'] ?? null)) {
            Api::validationFailed(['file' => 'Send one file per request']);
        }

        $stored = Upload::store($file, Upload::MEDIA);

        if (!$stored) {
            Api::validationFailed(['file' => Upload::$lastError]);
        }

        $body = $this->body();
        $publicId = next_public_id('media', 'med');
        $userId = $this->userId();

        db_execute(
            'INSERT INTO media
                (public_id, url, path, filename, mime, alt, caption, folder,
                 width, height, size_bytes, uploaded_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $publicId,
                /* Root-relative, not absolute: the stored value has to survive
                   the site moving between a staging domain and its own, which
                   is the find-and-replace this conversion exists to remove. */
                '/' . $stored['relativePath'],
                $stored['relativePath'],
                $stored['original'],
                $stored['mime'],
                trim((string) ($body['alt'] ?? '')) ?: null,
                trim((string) ($body['caption'] ?? '')) ?: null,
                trim((string) ($body['folder'] ?? '')) ?: 'Uploads',
                $stored['width'],
                $stored['height'],
                $stored['size'],
                $userId,
                now_iso(),
                now_iso(),
            ]
        );

        $row = $this->find($publicId, true);

        ActivityLog::record('create', 'media', $publicId, $stored['original']);

        Api::created($this->row($row));
    }

    public function usage(): never
    {
        $row = $this->find((string) $this->param('id'), true);

        if (!$row) {
            Api::notFound();
        }

        Api::ok(['usedBy' => MediaUsage::forUrl($row['url'])]);
    }

    /**
     * Alt text, caption and folder. Nothing else — the file itself, its
     * dimensions and its type are facts about the upload, not fields.
     */
    public function update(): never
    {
        $id = (string) $this->param('id');
        $row = $this->find($id, true);

        if (!$row) {
            Api::notFound();
        }

        $body = $this->body();
        $before = $this->row($row);

        if (!empty($body['updatedAt']) && $body['updatedAt'] !== ($before['updatedAt'] ?? null)) {
            Api::conflict('Somebody else saved this file while you were editing — reload to see their version');
        }

        $columns = [];

        foreach (['alt' => 'alt', 'caption' => 'caption', 'folder' => 'folder'] as $field => $column) {
            if (array_key_exists($field, $body)) {
                $columns[$column] = trim((string) $body[$field]) ?: null;
            }
        }

        /* A file has to sit somewhere. An emptied folder box means the default
           one, not a file that belongs to no folder and appears in no list. */
        if (array_key_exists('folder', $columns) && $columns['folder'] === null) {
            $columns['folder'] = 'Uploads';
        }

        if ($columns) {
            $columns['updated_at'] = now_iso();

            $set = implode(', ', array_map(static fn ($c) => $c . ' = ?', array_keys($columns)));

            db_execute(
                'UPDATE media SET ' . $set . ' WHERE id = ?',
                array_merge(array_values($columns), [(int) $row['id']])
            );
        }

        $after = $this->row($this->find($id, true));

        ActivityLog::record(
            'update',
            'media',
            $id,
            (string) $row['filename'],
            ActivityLog::diff($before, $after)
        );

        Api::ok($after);
    }

    /**
     * Soft delete, refused while anything still points at the file.
     *
     * `?force=true` is the opt-out, and it is what the panel's confirm dialog
     * offers once it has listed the records — docs/04-crud-flows.md §Delete.
     *
     * The file on disk stays either way. The panel offers Undo for eight
     * seconds and a deleted row can be restored for as long as it is in the
     * bin; unlinking here would make both a lie.
     */
    public function destroy(): never
    {
        $id = (string) $this->param('id');
        $row = $this->find($id, true);

        if (!$row) {
            Api::notFound();
        }

        $force = filter_var($this->query('force') ?? 'false', FILTER_VALIDATE_BOOL);
        $usedBy = MediaUsage::forUrl($row['url']);

        if ($usedBy && !$force) {
            Api::hasDependents($usedBy);
        }

        db_execute(
            'UPDATE media SET deleted_at = ?, updated_at = ? WHERE id = ?',
            [now_iso(), now_iso(), (int) $row['id']]
        );

        ActivityLog::record(
            'delete',
            'media',
            $id,
            (string) $row['filename']
                . ($usedBy ? ' — force-deleted while used by ' . count($usedBy) . ' record(s)' : '')
        );

        Api::noContent();
    }

    /** The other half of the Undo on the delete toast. */
    public function restore(): never
    {
        $id = (string) $this->param('id');
        $row = $this->find($id, false);

        if (!$row) {
            Api::notFound();
        }

        db_execute(
            'UPDATE media SET deleted_at = NULL, updated_at = ? WHERE id = ?',
            [now_iso(), (int) $row['id']]
        );

        $restored = $this->find($id, true);

        ActivityLog::record('restore', 'media', $id, (string) $row['filename']);

        Api::ok($this->row($restored));
    }

    /**
     * Bulk actions — only delete is supported for media.
     *
     * Mirrors ResourceController::bulk() but uses MediaUsage for the
     * dependency check. Returns {succeeded: [], failed: [{id, reason}]}.
     */
    public function bulk(): never
    {
        $body = $this->body();
        $ids = $body['ids'] ?? [];
        $action = $body['action'] ?? '';
        $payload = $body['payload'] ?? [];

        if (!is_array($ids) || !$ids) {
            Api::validationFailed(['ids' => 'Nothing was selected']);
        }

        if ($action !== 'delete') {
            Api::validationFailed(['action' => 'Only delete is supported in bulk for media']);
        }

        $force = filter_var($payload['force'] ?? false, FILTER_VALIDATE_BOOL);
        $succeeded = [];
        $failed = [];

        foreach ($ids as $id) {
            $row = $this->find((string) $id, true);

            if (!$row) {
                $failed[] = ['id' => $id, 'reason' => 'Not found'];
                continue;
            }

            $usedBy = MediaUsage::forUrl($row['url']);

            if ($usedBy && !$force) {
                $failed[] = ['id' => $id, 'reason' => 'In use by ' . count($usedBy) . ' record(s)'];
                continue;
            }

            db_execute(
                'UPDATE media SET deleted_at = ?, updated_at = ? WHERE id = ?',
                [now_iso(), now_iso(), (int) $row['id']]
            );
            $succeeded[] = $id;
        }

        ActivityLog::record(
            'delete',
            'media',
            null,
            'Bulk delete: ' . count($succeeded) . ' of ' . count($ids) . ' file(s)'
        );

        Api::ok(['succeeded' => $succeeded, 'failed' => $failed]);
    }

    /* ---------------------------------------------------------
       Helpers
       --------------------------------------------------------- */

    private function query(string $key, mixed $default = null): mixed
    {
        return ApiRequest::query($key, $default);
    }

    private function find(string $publicId, bool $liveOnly): ?array
    {
        $sql = 'SELECT * FROM media WHERE public_id = ?';

        if ($liveOnly) {
            $sql .= ' AND deleted_at IS NULL';
        }

        return db_fetch_one($sql, [$publicId]) ?: null;
    }

    private function row(array $raw): array
    {
        return [
            'id' => $raw['public_id'],
            'url' => $raw['url'],
            'filename' => $raw['filename'],
            'mime' => $raw['mime'],
            'alt' => $raw['alt'],
            'caption' => $raw['caption'],
            'folder' => $raw['folder'] ?: 'Uploads',
            'width' => $raw['width'] === null ? null : (int) $raw['width'],
            'height' => $raw['height'] === null ? null : (int) $raw['height'],
            'sizeBytes' => $raw['size_bytes'] === null ? null : (int) $raw['size_bytes'],
            /* Whether the file is an upload or a URL somebody pasted. The
               panel shows the difference; a hosted image has nothing on disk
               to worry about. */
            'uploaded' => !empty($raw['path']),
            'usedBy' => MediaUsage::forUrl($raw['url']),
            'createdAt' => iso_datetime($raw['created_at'] ?? null),
            'updatedAt' => iso_datetime($raw['updated_at'] ?? null),
            'uploadedBy' => user_display_name($raw['uploaded_by'] ?? null),
        ];
    }

    /**
     * The folder list, with a count each — the gallery's left rail.
     *
     * @return array<int, array{name: string, count: int}>
     */
    private function folders(): array
    {
        $rows = db_fetch_all(
            "SELECT COALESCE(NULLIF(folder, ''), 'Uploads') AS name, COUNT(*) AS n
             FROM media WHERE deleted_at IS NULL GROUP BY name ORDER BY name"
        );

        return array_map(
            static fn ($row) => ['name' => (string) $row['name'], 'count' => (int) $row['n']],
            $rows
        );
    }

    private function userId(): ?int
    {
        $id = Auth::id();

        return $id === null ? null : (int) $id;
    }
}
