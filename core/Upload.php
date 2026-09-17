<?php

/**
 * File uploads.
 *
 * Two destinations with deliberately different rules:
 *
 *   media  — images for the gallery and every media picker in the panel.
 *            Public. Lands under assets/uploads/<year>/<month>/ and is served
 *            by URL, beside the site's other images.
 *   video  — gallery clips. Public, and stored beside the images for the same
 *            reason. Its own kind rather than a wider extension list on media,
 *            because the two differ in more than type: a video arrives from a
 *            phone at a couple of hundred megabytes, so the ceiling is its own
 *            (UPLOAD_MAX_VIDEO_MB), and what lands is then handed to
 *            VideoTranscode before anything records it.
 *   cv     — job applicants' CVs. Not public, not served by URL, and never
 *            reachable by guessing one; storage/cv/ is denied by .htaccess and
 *            the file only comes back through an authenticated endpoint. A CV
 *            is a named person's address, phone number and work history.
 *
 * Media used to be written to storage/uploads/, which cannot work: storage/ is
 * denied wholesale by both the root .htaccess and its own, precisely so that a
 * CV can never be reached by URL. An image that has to be public does not
 * belong in the directory whose rule is "nothing here is served" — one
 * exception in that rule is how a CV eventually leaks. assets/uploads/ carries
 * its own .htaccess refusing anything executable.
 *
 * The stored name is random in both cases. Keeping the browser's filename
 * invites a collision at best and a path-traversal attempt at worst, and the
 * original is kept in the database for display anyway.
 */
class Upload
{
    public const MEDIA = 'media';
    public const VIDEO = 'video';
    public const CV = 'cv';

    /* `maxEnv` names the environment variable holding this kind's ceiling in
       megabytes, and `maxMb` is what applies when it is unset. One number for
       every kind would have to be the video number, which is not a size any
       screen should be allowed to accept a JPEG at. */
    private const RULES = [
        self::MEDIA => [
            'dir' => 'assets/uploads',
            'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif'],
            'mimes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'image/avif'],
            'maxEnv' => 'UPLOAD_MAX_MB',
            'maxMb' => 8,
        ],
        self::VIDEO => [
            'dir' => 'assets/uploads',
            'extensions' => ['mp4', 'mov', 'webm', 'mkv', 'm4v'],
            'mimes' => [
                'video/mp4',
                'video/quicktime',
                'video/webm',
                'video/x-matroska',
                'video/x-m4v',
            ],
            'maxEnv' => 'UPLOAD_MAX_VIDEO_MB',
            'maxMb' => 128,
        ],
        self::CV => [
            'dir' => 'storage/cv',
            'extensions' => ['pdf', 'doc', 'docx', 'odt', 'rtf'],
            'mimes' => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.oasis.opendocument.text',
                'application/rtf',
                'text/rtf',
            ],
            'maxEnv' => 'UPLOAD_MAX_MB',
            'maxMb' => 8,
        ],
    ];

    /** Set when store() returns null. */
    public static string $lastError = '';

    /**
     * @param array $file One entry from $_FILES.
     * @return array{path: string, relativePath: string, filename: string,
     *               original: string, size: int, mime: string,
     *               width: ?int, height: ?int}|null
     */
    public static function store(array $file, string $kind = self::MEDIA): ?array
    {
        self::$lastError = '';

        $rules = self::RULES[$kind] ?? null;
        if (!$rules) {
            self::$lastError = "Unknown upload kind: {$kind}";
            return null;
        }

        if (!isset($file['tmp_name'], $file['error'])) {
            self::$lastError = 'No file received';
            return null;
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            self::$lastError = self::errorMessage((int) $file['error']);
            return null;
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            self::$lastError = 'Not an uploaded file';
            return null;
        }

        $maxBytes = self::maxBytes($kind);
        if ($file['size'] > $maxBytes) {
            self::$lastError = 'That file is larger than ' . self::megabytes($maxBytes);
            return null;
        }

        $original = self::sanitiseName((string) ($file['name'] ?? 'file'));
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));

        if (!in_array($extension, $rules['extensions'], true)) {
            self::$lastError = 'That file type is not accepted (' . implode(', ', $rules['extensions']) . ')';
            return null;
        }

        /* Checked from the file's own bytes, not from the browser's
           Content-Type header, which the client controls entirely. */
        $mime = self::detectMime($file['tmp_name']);
        if ($mime !== null && !in_array($mime, $rules['mimes'], true)) {
            self::$lastError = 'That file is not what its name says it is';
            return null;
        }

        $relativeDir = $rules['dir'];
        if ($kind === self::MEDIA || $kind === self::VIDEO) {
            $relativeDir .= '/' . date('Y') . '/' . date('m');
        }

        $absoluteDir = __BASEDIR__ . '/' . $relativeDir;
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0755, true) && !is_dir($absoluteDir)) {
            self::$lastError = 'Could not create the upload directory';
            return null;
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $absolutePath = $absoluteDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
            self::$lastError = 'Could not save the file';
            return null;
        }

        chmod($absolutePath, 0644);

        $width = null;
        $height = null;
        if ($kind === self::MEDIA && $extension !== 'svg') {
            $size = @getimagesize($absolutePath);
            if ($size) {
                [$width, $height] = $size;
            }
        }

        return [
            'path' => $absolutePath,
            'relativePath' => $relativeDir . '/' . $filename,
            'filename' => $filename,
            'original' => $original,
            'size' => (int) $file['size'],
            'mime' => $mime ?? 'application/octet-stream',
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * The real ceiling for a kind, in bytes.
     *
     * Whichever is smallest of the kind's own limit, `upload_max_filesize` and
     * `post_max_size`. Configuring UPLOAD_MAX_VIDEO_MB=128 against a PHP that
     * refuses anything over 8 MB does not accept 128 MB uploads — it accepts
     * 8 MB and reports the wrong number for everything above it, which is a
     * bug report about the panel rather than about php.ini.
     */
    public static function maxBytes(string $kind = self::MEDIA): int
    {
        $rules = self::RULES[$kind] ?? self::RULES[self::MEDIA];

        $limits = [(int) env($rules['maxEnv'], $rules['maxMb']) * 1024 * 1024];

        foreach (['upload_max_filesize', 'post_max_size'] as $setting) {
            $bytes = self::iniBytes((string) ini_get($setting));

            /* 0 or -1 means unlimited, and is not a candidate for the minimum. */
            if ($bytes > 0) {
                $limits[] = $bytes;
            }
        }

        return min($limits);
    }

    /** '8M' → 8388608. The shorthand php.ini uses and nothing else parses. */
    private static function iniBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '') {
            return 0;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    /** For the message only: 134217728 → '128 MB'. */
    private static function megabytes(int $bytes): string
    {
        return round($bytes / 1024 / 1024, 1) . ' MB';
    }

    /** Deletes a stored file. Missing is not an error — the goal is "gone". */
    public static function delete(string $relativePath): bool
    {
        $path = realpath(__BASEDIR__ . '/' . ltrim($relativePath, '/'));

        if (!$path) {
            return true;
        }

        /* Never delete outside the two upload directories, whatever the caller
           passed. A media row's `path` is written by store() and nothing else,
           but this is the guard that holds if that ever stops being true. */
        foreach (['storage', 'assets/uploads'] as $allowed) {
            $root = realpath(__BASEDIR__ . '/' . $allowed);

            if ($root && str_starts_with($path, $root)) {
                return !file_exists($path) || unlink($path);
            }
        }

        return false;
    }

    private static function detectMime(string $path): ?string
    {
        if (!function_exists('finfo_open')) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if (!$finfo) {
            return null;
        }

        $mime = finfo_file($finfo, $path);
        finfo_close($finfo);

        return $mime ?: null;
    }

    /** Strips directories and anything that is not a plain filename character. */
    private static function sanitiseName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) ?? 'file';
        $name = trim($name, '-.');

        return $name === '' ? 'file' : substr($name, 0, 120);
    }

    private static function errorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'That file is too large',
            UPLOAD_ERR_PARTIAL => 'The upload was interrupted — try again',
            UPLOAD_ERR_NO_FILE => 'No file was chosen',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'The server could not write the file',
            UPLOAD_ERR_EXTENSION => 'The upload was blocked by the server',
            default => 'The upload failed',
        };
    }
}
