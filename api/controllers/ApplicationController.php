<?php

/**
 * Job applications — the list, and the file that comes with one.
 *
 *     GET    api/applications              list, filtered and paged
 *     GET    api/applications/{id}         one record
 *     PATCH  api/applications/{id}         the panel's own pipeline
 *     GET    api/applications/{id}/cv      the CV, streamed
 *     GET    api/applications/{id}/cv?file=cover-letter
 *
 * The first three are the generic controller's, unchanged, which is why this
 * extends it: an application is an ordinary resource with one extra field that
 * cannot be a column — the URL of the endpoint that streams its CV. The panel
 * reads `cvUrl` and opens it (assets/admin/js/pages/applications.js), so
 * adding it here keeps that screen working without touching its JavaScript.
 *
 * Applications are received, never authored. `create => false` in the registry
 * refuses POST; the only thing that writes one is
 * POST api/public/application.
 *
 * ---------------------------------------------------------------------------
 * Why the file is streamed rather than linked
 *
 * A CV is a named person's address, phone number and employment history. It
 * lives in storage/cv/, which both .htaccess files refuse outright, under a
 * random filename, and it leaves this server only through this endpoint —
 * behind the session, with no-store, and with a Content-Disposition that makes
 * a browser save it rather than run it.
 * ---------------------------------------------------------------------------
 */
class ApplicationController extends ResourceController
{
    protected function resource(): array
    {
        return $this->resource ??= ResourceRegistry::get('applications');
    }

    /**
     * The two files an application can carry, and the columns behind each.
     * A caller names one of these keys or gets the CV.
     */
    private const FILES = [
        'cv' => ['path' => 'cv_path', 'name' => 'cv_file', 'label' => 'CV'],
        'cover-letter' => ['path' => 'cover_letter_path', 'name' => 'cover_letter_file', 'label' => 'cover letter'],
    ];

    protected function decorate(array $r, array $row, array $dbRow): array
    {
        $row['cvUrl'] = empty($dbRow['cv_path'])
            ? null
            : base_url('api/applications/' . $dbRow[$r['key']] . '/cv');

        $row['coverLetterUrl'] = empty($dbRow['cover_letter_path'])
            ? null
            : base_url('api/applications/' . $dbRow[$r['key']] . '/cv?file=cover-letter');

        return $row;
    }

    public function cv(): never
    {
        $r = $this->resource();
        $id = (string) $this->param('id');
        $row = $this->find($r, $id, true);

        if (!$row) {
            Api::notFound();
        }

        $which = (string) (ApiRequest::query('file') ?? 'cv');
        $spec = self::FILES[$which] ?? self::FILES['cv'];

        $relative = (string) ($row[$spec['path']] ?? '');

        if ($relative === '') {
            /* Seeded and older rows carry a filename with no file — the admin
               prototype's data named a CV it never had. Say so plainly rather
               than sending a zero-byte download. */
            Api::notFound('No ' . $spec['label'] . ' is stored for this application');
        }

        $absolute = realpath(__BASEDIR__ . '/' . ltrim($relative, '/'));
        $root = realpath(__BASEDIR__ . '/storage/cv');

        /* The path comes from a column this application wrote, but the check
           costs nothing and is what stops a doctored row turning this endpoint
           into a way to read any file on the server. */
        if (!$absolute || !$root || !str_starts_with($absolute, $root) || !is_file($absolute)) {
            error_log('[applications] Missing file for ' . $id . ': ' . $relative);
            Api::notFound('That file is no longer on the server');
        }

        ActivityLog::record(
            'view',
            'applications',
            $id,
            'Downloaded the ' . $spec['label'] . ' of ' . (string) $row['name']
        );

        $this->stream($absolute, (string) ($row[$spec['name']] ?: basename($absolute)));
    }

    /**
     * Sends the file and stops.
     *
     * `attachment`, always: a PDF rendered inline is a PDF rendered by the
     * browser's own reader on a page that also holds a session. And no
     * caching — a CV in a shared proxy's cache is the whole problem again.
     */
    private function stream(string $path, string $filename): never
    {
        /* Nothing has been echoed yet, but a stray notice in an include would
           corrupt the file. Discard whatever is buffered before the bytes. */
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $filename = str_replace(['"', "\r", "\n"], '', $filename);

        http_response_code(200);
        header('Content-Type: ' . $this->mime($path));
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: attachment; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');

        readfile($path);
        exit;
    }

    private function mime(string $path): string
    {
        if (function_exists('finfo_open') && ($finfo = finfo_open(FILEINFO_MIME_TYPE))) {
            $mime = finfo_file($finfo, $path);
            finfo_close($finfo);

            if ($mime) {
                return $mime;
            }
        }

        return 'application/octet-stream';
    }
}
