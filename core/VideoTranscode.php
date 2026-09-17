<?php

/**
 * ffmpeg, for the gallery's video uploads.
 *
 * A clip off a phone is 1080p or 4K at a bitrate chosen for recording rather
 * than for delivery — thirty seconds of it is routinely 150 MB, and served as
 * uploaded it is both the largest file on the server and the slowest thing on
 * the page. Everything here exists to make that one number smaller: re-encode
 * to H.264 at 720p-ish, cap the audio, and move the moov atom to the front so
 * the browser can start playing before the file has finished arriving.
 *
 * The rule throughout: a failure here never fails the upload. ffmpeg is not
 * installed everywhere, shared hosts disable exec(), and a codec can be
 * missing on a host that has the binary. In every one of those cases the file
 * that arrived is kept exactly as it arrived and `compressed` comes back
 * false — a gallery holding one uncompressed clip is a working gallery, and an
 * upload rejected because a poster frame could not be extracted is not.
 *
 * Nothing here interpolates a caller's string into a shell command: every path
 * goes through escapeshellarg(), and the binaries are read from the
 * environment rather than found on PATH at request time.
 */
class VideoTranscode
{
    /** Longest edge of the output. 1280 is the point where a gallery tile stops improving. */
    private const MAX_WIDTH = 1280;

    /** Constant Rate Factor. 28 is visibly lossy on a still and invisible in motion. */
    private const CRF = 28;

    /** Set whenever a step gave up, for the log; never shown to the visitor. */
    public static string $lastError = '';

    /* ---------------------------------------------------------
       Availability
       --------------------------------------------------------- */

    public static function available(): bool
    {
        return self::binary('FFMPEG_BIN', 'ffmpeg') !== null && self::canExec();
    }

    /**
     * The absolute path to a working binary, or null.
     *
     * `which` is asked only once per name per request: it is a process spawn,
     * and store() calls this three times for one upload.
     */
    private static function binary(string $envKey, string $name): ?string
    {
        static $cache = [];

        if (array_key_exists($envKey, $cache)) {
            return $cache[$envKey];
        }

        $configured = trim((string) env($envKey, ''));

        if ($configured !== '') {
            return $cache[$envKey] = is_executable($configured) ? $configured : null;
        }

        if (!self::canExec()) {
            return $cache[$envKey] = null;
        }

        $found = trim((string) @shell_exec('command -v ' . escapeshellarg($name) . ' 2>/dev/null'));

        return $cache[$envKey] = ($found !== '' && is_executable($found)) ? $found : null;
    }

    /** exec() is disabled on a good number of shared hosts, and says so in ini. */
    private static function canExec(): bool
    {
        static $ok = null;

        if ($ok !== null) {
            return $ok;
        }

        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return $ok = function_exists('exec') && !in_array('exec', $disabled, true);
    }

    /* ---------------------------------------------------------
       The work
       --------------------------------------------------------- */

    /**
     * Re-encode in place.
     *
     * Written to a sibling temp file and only then moved over the original, so
     * an ffmpeg that dies halfway leaves the uploaded file intact rather than
     * a truncated one. The result always ends `.mp4`, whatever arrived — a
     * .mov that is now H.264 in an MP4 container and still named .mov is a
     * file Safari will play and Chrome will download.
     *
     * @return array{path: string, relativePath: string, size: int, compressed: bool}
     */
    public static function compress(string $absolutePath, string $relativePath): array
    {
        $unchanged = [
            'path' => $absolutePath,
            'relativePath' => $relativePath,
            'size' => (int) @filesize($absolutePath),
            'compressed' => false,
        ];

        $ffmpeg = self::binary('FFMPEG_BIN', 'ffmpeg');

        if ($ffmpeg === null) {
            self::$lastError = 'ffmpeg is not available';
            return $unchanged;
        }

        $target = preg_replace('/\.[^.\/]+$/', '', $absolutePath) . '.mp4';
        $temp = $target . '.tmp.mp4';

        /* No time limit: a two-minute clip is a two-minute clip, and the panel
           holds the request open with a progress bar. The alternative is a job
           queue this application does not have. */
        @set_time_limit(0);

        $ok = self::run([
            $ffmpeg, '-y', '-i', $absolutePath,
            /* -2 keeps the height even, which libx264 requires; min() means a
               clip that is already small is never scaled up. */
            '-vf', "scale='min(" . self::MAX_WIDTH . ",iw)':-2",
            '-c:v', 'libx264', '-crf', (string) self::CRF, '-preset', 'medium',
            '-pix_fmt', 'yuv420p',
            '-c:a', 'aac', '-b:a', '128k',
            '-movflags', '+faststart',
            $temp,
        ]);

        if (!$ok || !is_file($temp) || filesize($temp) === 0) {
            @unlink($temp);
            self::$lastError = self::$lastError ?: 'ffmpeg did not produce an output file';
            return $unchanged;
        }

        /* A clip already encoded for delivery can come out larger than it went
           in. Keeping the bigger file would be the opposite of the point. */
        if (filesize($temp) >= filesize($absolutePath) && str_ends_with(strtolower($absolutePath), '.mp4')) {
            @unlink($temp);
            return $unchanged;
        }

        if (!@rename($temp, $target)) {
            @unlink($temp);
            self::$lastError = 'Could not replace the uploaded file';
            return $unchanged;
        }

        /* The source only goes once the replacement is in place, and only when
           it was a different name — .mov in, .mp4 out. */
        if ($target !== $absolutePath) {
            @unlink($absolutePath);
        }

        @chmod($target, 0644);

        return [
            'path' => $target,
            'relativePath' => preg_replace('/\.[^.\/]+$/', '', $relativePath) . '.mp4',
            'size' => (int) filesize($target),
            'compressed' => true,
        ];
    }

    /**
     * A still from one second in, as the tile's poster.
     *
     * One second rather than zero: the first frame of a hand-held clip is
     * usually the moment before the camera settled, and often black.
     */
    public static function poster(string $absolutePath, string $outPath): bool
    {
        $ffmpeg = self::binary('FFMPEG_BIN', 'ffmpeg');

        if ($ffmpeg === null) {
            return false;
        }

        $ok = self::run([
            $ffmpeg, '-y', '-ss', '1', '-i', $absolutePath,
            '-frames:v', '1',
            '-vf', "scale='min(" . self::MAX_WIDTH . ",iw)':-2",
            '-q:v', '3',
            $outPath,
        ]);

        if ($ok && is_file($outPath) && filesize($outPath) > 0) {
            @chmod($outPath, 0644);
            return true;
        }

        @unlink($outPath);

        return false;
    }

    /**
     * Dimensions and duration, for the panel to display.
     *
     * @return array{width: ?int, height: ?int, duration: ?int}
     */
    public static function probe(string $absolutePath): array
    {
        $empty = ['width' => null, 'height' => null, 'duration' => null];
        $ffprobe = self::binary('FFPROBE_BIN', 'ffprobe');

        if ($ffprobe === null) {
            return $empty;
        }

        $out = [];
        $code = 1;

        @exec(self::command([
            $ffprobe, '-v', 'error',
            '-select_streams', 'v:0',
            '-show_entries', 'stream=width,height:format=duration',
            '-of', 'json', $absolutePath,
        ]), $out, $code);

        if ($code !== 0) {
            return $empty;
        }

        $json = json_decode(implode('', $out), true);

        if (!is_array($json)) {
            return $empty;
        }

        $stream = $json['streams'][0] ?? [];
        $duration = $json['format']['duration'] ?? null;

        return [
            'width' => isset($stream['width']) ? (int) $stream['width'] : null,
            'height' => isset($stream['height']) ? (int) $stream['height'] : null,
            'duration' => $duration !== null ? (int) round((float) $duration) : null,
        ];
    }

    /* ---------------------------------------------------------
       Shell
       --------------------------------------------------------- */

    /** @param string[] $parts */
    private static function run(array $parts): bool
    {
        if (!self::canExec()) {
            self::$lastError = 'exec() is disabled on this host';
            return false;
        }

        $out = [];
        $code = 1;

        /* stderr folded into stdout and captured rather than printed: ffmpeg
           writes its whole progress report there, and this runs inside a
           request that is going to answer JSON. */
        @exec(self::command($parts) . ' 2>&1', $out, $code);

        if ($code !== 0) {
            self::$lastError = trim(implode("\n", array_slice($out, -3)));
        }

        return $code === 0;
    }

    /** @param string[] $parts */
    private static function command(array $parts): string
    {
        return implode(' ', array_map('escapeshellarg', $parts));
    }
}
