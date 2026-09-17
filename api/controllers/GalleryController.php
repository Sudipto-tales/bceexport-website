<?php

/**
 * The gallery's one endpoint that is not generic CRUD.
 *
 * Everything else about /api/gallery — list, create, patch, delete, reorder —
 * is ResourceController's, driven by config/resources.php. This adds the video
 * upload, which cannot be: it is multipart rather than JSON, it runs ffmpeg,
 * and it answers with four fields the form then saves into an ordinary record.
 *
 * Deliberately not part of POST /api/media. That endpoint is the asset library
 * every picker in the panel reads, and widening it to accept video would put a
 * 40 MB clip in front of every screen that asks for a logo. This one writes no
 * row at all: it stores a file, transcodes it, and hands back where it went.
 */
class GalleryController extends ResourceController
{
    public function video(): never
    {
        $file = $_FILES['file'] ?? null;

        if (!is_array($file)) {
            /* A body over post_max_size is discarded by PHP before this
               method runs: $_FILES and $_POST are both empty and there is no
               error code to read. Only Content-Length still says what was
               attempted, and without this the panel would be told "no file was
               sent" about a file it spent two minutes sending. */
            $sent = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
            $limit = Upload::maxBytes(Upload::VIDEO);

            if ($sent > $limit) {
                Api::validationFailed([
                    'file' => 'That video is larger than this server accepts ('
                        . round($limit / 1024 / 1024, 1) . ' MB). Raise upload_max_filesize and '
                        . 'post_max_size in php.ini, or compress the clip before uploading it.',
                ]);
            }

            Api::validationFailed(['file' => 'No file was sent']);
        }

        if (is_array($file['name'] ?? null)) {
            Api::validationFailed(['file' => 'Send one file per request']);
        }

        $stored = Upload::store($file, Upload::VIDEO);

        if (!$stored) {
            Api::validationFailed(['file' => Upload::$lastError]);
        }

        /* Both of these keep the upload when they fail — see VideoTranscode.
           A clip that is merely large is still a clip; a rejected upload is
           nothing at all, and the editor has no way to fix a host that is
           missing a codec. */
        $result = VideoTranscode::compress($stored['path'], $stored['relativePath']);
        $probe = VideoTranscode::probe($result['path']);

        $poster = $this->poster($result['path'], $result['relativePath']);

        ActivityLog::record('create', 'gallery', 'video', $stored['original']);

        Api::ok([
            /* Root-relative, like media.url: the stored value has to survive
               the site moving between a staging domain and its own. */
            'videoPath' => '/' . ltrim($result['relativePath'], '/'),
            'poster' => $poster,
            'filename' => $stored['original'],
            'sizeBytes' => $result['size'],
            'duration' => $probe['duration'],
            'width' => $probe['width'],
            'height' => $probe['height'],
            /* The panel says so plainly rather than implying it: an editor who
               uploaded 180 MB and got 180 MB back should know why. */
            'compressed' => $result['compressed'],
            'originalSize' => (int) ($stored['size'] ?? 0),
            'note' => $result['compressed'] ? '' : (VideoTranscode::$lastError ?: 'Stored as uploaded'),
        ]);
    }

    /**
     * The extracted still, beside the clip and named after it.
     *
     * Returns '' rather than failing when there is no ffmpeg: the form then
     * asks for a poster from the media picker, which is the same field an
     * editor would have overridden anyway.
     */
    private function poster(string $absolutePath, string $relativePath): string
    {
        $out = preg_replace('/\.[^.\/]+$/', '', $absolutePath) . '-poster.jpg';

        if (!VideoTranscode::poster($absolutePath, $out)) {
            return '';
        }

        return '/' . ltrim(preg_replace('/\.[^.\/]+$/', '', $relativePath) . '-poster.jpg', '/');
    }
}
