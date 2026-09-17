<?php

/**
 * Applying a content pack to a database nothing else can reach.
 *
 * The standing limitation in docs/09-deployment.md: this site is deployed by a
 * git checkout onto shared hosting with no shell, so there is no moment at
 * which anybody can run a command against the live database. A deploy copies
 * files and stops. Content that is not typed into the panel — a photo set off
 * a camera, the rows that describe it — has no way in.
 *
 * This is that way in, kept as small as the job allows:
 *
 *   - It runs a pack that is already in the repository, by name. There is no
 *     endpoint here that accepts SQL, a file, or a row. The request chooses
 *     between reviewed, committed bundles; it does not supply one.
 *   - It is invisible unless CONTENT_IMPORT_TOKEN is set. An unset token
 *     answers 404, the same as a route that does not exist, because a 403
 *     tells a scanner that the thing it was looking for is here.
 *   - It never deletes. See ContentPack: rows are inserted or updated by key,
 *     files are copied only into a path that does not already hold one.
 *
 * The token is not a session. That is deliberate — the panel's session belongs
 * to an editor, and this is not an editing action; it is the deploy step that
 * the host cannot perform, run by whoever performs deploys. It is also why the
 * token goes in a header rather than the URL: query strings are written to
 * access logs, and shared hosting keeps those where support staff can read
 * them.
 *
 * Once a pack has been applied, the token has done its job. Removing the line
 * from .env closes the route again, and the docs say so.
 */
class ContentController extends ApiController
{
    /** GET — what packs exist, and what applying one would change. */
    public function index(): never
    {
        $this->authorise();

        $pack = trim((string) ($this->query('pack') ?? ''));

        if ($pack === '') {
            Api::ok(['packs' => ContentPack::available()]);
        }

        try {
            /* A dry run reports counts from the same code that would do the
               work — a preview computed by a second implementation is a
               preview of the wrong thing. */
            Api::ok(ContentPack::apply($pack, true));
        } catch (RuntimeException $e) {
            Api::validationFailed(['pack' => $e->getMessage()]);
        }
    }

    /** POST — apply it. */
    public function import(): never
    {
        $this->authorise();

        $pack = trim((string) ($this->input('pack') ?? ''));

        if ($pack === '') {
            Api::validationFailed(['pack' => 'Name the pack to apply']);
        }

        try {
            $result = ContentPack::apply($pack, false);
        } catch (RuntimeException $e) {
            Api::validationFailed(['pack' => $e->getMessage()]);
        }

        /* Logged like any other write, so the panel's activity feed shows the
           day the gallery gained nine items and does not present it as
           something an editor did. */
        ActivityLog::record(
            'import',
            'content',
            $pack,
            sprintf(
                '%d rows inserted, %d updated, %d files copied',
                $result['rows']['inserted'],
                $result['rows']['updated'],
                $result['files']['copied']
            )
        );

        Api::ok($result);
    }

    /* ---------------------------------------------------------
       The guard
       --------------------------------------------------------- */

    private function authorise(): void
    {
        $expected = trim((string) env('CONTENT_IMPORT_TOKEN', ''));

        /* Unset means the route is not open, and the answer says nothing about
           why. A site that has finished importing its content should be in
           this state. */
        if ($expected === '') {
            Api::notFound('Not found');
        }

        /* Short tokens are the ones a script would sit and guess at. Rejecting
           them here rather than trusting whoever pasted the .env means the
           rate limit below is a second line of defence and not the only one. */
        if (strlen($expected) < 32) {
            error_log('[content] CONTENT_IMPORT_TOKEN is set but shorter than 32 characters; refusing to use it');
            Api::notFound('Not found');
        }

        $ip = RateLimit::clientIp();

        if (RateLimit::tooMany('content.import', 20, 3600, $ip)) {
            Api::rateLimited(3600);
        }

        RateLimit::hit('content.import', $ip);

        $given = (string) ($_SERVER['HTTP_X_CONTENT_TOKEN'] ?? '');

        /* hash_equals rather than ===, so the comparison takes the same time
           whatever the first wrong byte is. */
        if ($given === '' || !hash_equals($expected, $given)) {
            error_log('[content] Rejected an import from ' . $ip);
            Api::notFound('Not found');
        }
    }

    private function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }
}
