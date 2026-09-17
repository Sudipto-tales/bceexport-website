<?php

/**
 * The settings singleton — docs/07-api-contract.md §Settings.
 *
 *     GET   api/settings                          every group in one object
 *     PATCH api/settings/{group}                  one group, partially
 *     POST  api/settings/integrations/test-smtp   send one mail and say what happened
 *
 * The table is one row per key, not one row per group (docs/php/02-schema.md),
 * and this controller keeps it that way: a PATCH writes only the keys it was
 * sent. Two people saving different settings screens at the same moment
 * therefore cannot overwrite each other, which is also why there is no
 * `updatedAt` concurrency check here — there is no shared record to be stale
 * about.
 *
 * `value` is always JSON, even for a plain string, so a scalar setting and a
 * repeater read back through the same code path.
 */
class SettingsController extends ApiController
{
    /**
     * The groups that may be written.
     *
     * A fixed list rather than "whatever is already in the table": a typo in a
     * URL should be a 404, not a seventh group nobody can see because no
     * screen renders it. `seo` is the seventh real one — the content model
     * calls popups the sixth, and SEO defaults arrived with the SEO screen.
     */
    private const GROUPS = ['general', 'contact', 'social', 'integrations', 'theme', 'popups', 'seo'];

    /** A setting key has to be a JavaScript property name; the panel indexes by it. */
    private const KEY_PATTERN = '/^[A-Za-z][A-Za-z0-9_]{0,63}$/';

    /** Room for a map embed or a long maintenance notice, not for a pasted file. */
    private const MAX_VALUE_BYTES = 65535;

    public function index(): never
    {
        $stored = all_settings(true);
        $out = [];

        /* Every group is present even when it holds nothing, so a settings
           screen can bind straight to its own group without checking. Cast,
           because an empty PHP array encodes as [] and the panel expects an
           object it can read properties off. */
        foreach (self::GROUPS as $group) {
            $out[$group] = (object) ($stored[$group] ?? []);
        }

        Api::ok($out);
    }

    public function update(): never
    {
        $group = (string) $this->param('group');

        if (!in_array($group, self::GROUPS, true)) {
            Api::notFound('No such settings group');
        }

        $body = $this->body();

        if (!$body) {
            Api::validationFailed(['_' => 'Nothing to save']);
        }

        $fields = [];
        $encoded = [];

        foreach ($body as $key => $value) {
            $key = (string) $key;

            if (!preg_match(self::KEY_PATTERN, $key)) {
                $fields[$key] = 'Not a usable setting name';
                continue;
            }

            $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if ($json === false) {
                $fields[$key] = 'That value cannot be stored';
                continue;
            }

            if (strlen($json) > self::MAX_VALUE_BYTES) {
                $fields[$key] = 'Too long — the limit is 64 KB';
                continue;
            }

            $encoded[$key] = $json;
        }

        if ($fields) {
            Api::validationFailed($fields);
        }

        $before = all_settings(true)[$group] ?? [];
        $userId = $this->userId();

        db_transaction(function () use ($group, $encoded, $userId) {
            foreach ($encoded as $key => $json) {
                $id = db_scalar(
                    'SELECT id FROM settings WHERE group_name = ? AND setting_key = ?',
                    [$group, $key]
                );

                if ($id) {
                    db_execute(
                        'UPDATE settings SET value = ?, updated_by = ?, updated_at = ? WHERE id = ?',
                        [$json, $userId, now_iso(), $id]
                    );
                    continue;
                }

                db_execute(
                    'INSERT INTO settings (group_name, setting_key, value, updated_by, updated_at)
                     VALUES (?, ?, ?, ?, ?)',
                    [$group, $key, $json, $userId, now_iso()]
                );
            }
        });

        $after = all_settings(true)[$group] ?? [];
        $diff = ActivityLog::diff($before, $after);

        /* Named in the log because these are the changes somebody asks about
           later: "who changed the emergency number" is answered by the field
           list, not by "settings were updated". */
        ActivityLog::record(
            'update',
            'settings',
            $group,
            $diff
                ? ucfirst($group) . ' settings — ' . implode(', ', array_keys($diff))
                : ucfirst($group) . ' settings — no change',
            $diff
        );

        Api::ok((object) $after);
    }

    /**
     * Sends one real message with the credentials the application will
     * actually use, and reports what happened.
     *
     * A refused login or a dead host comes back as 200 with `ok: false`. The
     * request worked; SMTP is what failed, and the reason is the useful part
     * of the answer. A 500 here would make the panel say "something went
     * wrong" instead of showing the server's own words.
     */
    public function testSmtp(): never
    {
        $user = Auth::user();

        /* Authenticated, but still a button that makes the server send mail to
           an address in the request. */
        if (!RateLimit::attempt('test-smtp', 5, 600, (string) ($user['id'] ?? ''))) {
            Api::rateLimited(600);
        }

        $addresses = $this->notifyAddresses();

        if (!$addresses) {
            Api::validationFailed(['notifyEnquiryTo' => 'Add a notification address first, and save']);
        }

        $to = trim((string) ($this->body()['to'] ?? ''));

        /* Only an address already saved on the notification list. A free
           choice of recipient would make an authenticated button into a way
           to send mail from the hospital's own server to anywhere. */
        if ($to === '') {
            $to = $addresses[0];
        } elseif (!in_array($to, $addresses, true)) {
            Api::validationFailed(['to' => 'Not one of the saved notification addresses']);
        }

        $config = Mailer::describe();

        if (!Mailer::isConfigured()) {
            Api::ok([
                'ok' => false,
                'message' => 'No SMTP host or sender address is configured — fill in the fields above, or set MAIL_* in the environment.',
                'config' => $config,
            ]);
        }

        $sent = Mailer::send(
            $to,
            'Test message from ' . (string) setting('general', 'name', 'the website'),
            '<p>This is a test of the website\'s mail settings.</p>'
            . '<p>It was sent by ' . e((string) ($user['name'] ?? 'the panel'))
            . ' at ' . e(now_iso()) . ' UTC, through ' . e($config['host'] . ':' . $config['port'])
            . '. Nothing else was changed.</p>',
            true
        );

        ActivityLog::record(
            'update',
            'settings',
            'integrations',
            ($sent ? 'SMTP test sent to ' : 'SMTP test failed to ') . $to
        );

        Api::ok([
            'ok' => $sent,
            'message' => $sent
                ? 'Sent. Check ' . $to . ' — allow a minute, and look in the spam folder.'
                : (Mailer::$lastError ?: 'The mail server refused the message.'),
            'config' => $config,
        ]);
    }

    /* --------------------------------------------------------- */

    /**
     * The enquiry notification list — a repeater of `{email}` rows.
     *
     * @return string[] valid addresses, in the order they are saved
     */
    private function notifyAddresses(): array
    {
        $list = setting('integrations', 'notifyEnquiryTo', []);
        $out = [];

        foreach (is_array($list) ? $list : [] as $entry) {
            $address = trim((string) (is_array($entry) ? ($entry['email'] ?? '') : $entry));

            if ($address !== '' && filter_var($address, FILTER_VALIDATE_EMAIL)) {
                $out[] = $address;
            }
        }

        return $out;
    }

    private function userId(): ?int
    {
        $user = Auth::user();

        return isset($user['id']) ? (int) $user['id'] : null;
    }
}
