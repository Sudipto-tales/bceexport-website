<?php

/**
 * The two endpoints the website posts to — docs/07-api-contract.md
 * §Public intake.
 *
 *     POST api/public/enquiry       the contact and appointment-request forms
 *     POST api/public/application   a job application, with the CV attached
 *
 * These are the only routes in the table with no middleware, because a visitor
 * has no session to check. Four things stand in for one:
 *
 *   CSRF        the page that rendered the form issued a token; the form
 *               sends it back. A visitor with cookies blocked cannot post,
 *               which is the price of the check.
 *   honeypot    a field no person can see and every naive bot fills in. It is
 *               answered with a success, not a refusal — telling a bot it was
 *               caught is telling it what to change.
 *   rate limit  per IP, per action, in the database.
 *   reCAPTCHA   when keys are configured. See core/Recaptcha.php for why it
 *               passes when Google cannot be reached.
 *
 * ---------------------------------------------------------------------------
 * The row is written before any mail is attempted, always.
 *
 * A job application that is lost because SMTP was down is a person's
 * employment gone from the record with nothing to retry from. So both handlers
 * insert first, then mail, then record whether the mail went — `notified_at`
 * on success, `notify_error` on failure, and the panel can see the difference.
 * ---------------------------------------------------------------------------
 */
class PublicIntakeController extends ApiController
{
    /**
     * The field no visitor can see.
     *
     * Named after something a form plausibly asks for, because a bot that
     * fills in everything is caught either way and a bot that reads names
     * fills this one in first.
     */
    private const HONEYPOT = 'website';

    /** What a visitor may say an enquiry is. Anything else is 'contact'. */
    private const SOURCES = ['contact', 'appointment', 'chat', 'phone', 'landing'];

    /** Column widths, so a long paste is a named 422 and not a driver error. */
    private const LIMITS = [
        'name' => 160,
        'email' => 191,
        'phone' => 40,
        'subject' => 255,
        'message' => 5000,
        'experience' => 120,
        'employer' => 191,
        'location' => 160,
        'coverNote' => 5000,
    ];

    /* =========================================================
       Enquiries
       ========================================================= */

    public function enquiry(): never
    {
        if ($this->trapped('enquiry')) {
            Api::created(['id' => null, 'receivedAt' => iso_datetime(now_iso())]);
        }

        $this->guard('enquiry', 5, 3600);

        $body = $this->body();
        $fields = [];

        $name = $this->text($body, 'name', 'name', $fields, true);
        $email = $this->text($body, 'email', 'email', $fields);
        $phone = $this->text($body, 'phone', 'phone', $fields);
        $message = $this->text($body, 'message', 'message', $fields);
        $subject = $this->text($body, 'subject', 'subject', $fields);

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $fields['email'] = 'That does not look like an email address';
        }

        /* One way to reach them back, whichever it is. An enquiry nobody can
           answer is not an enquiry. */
        if ($email === '' && $phone === '') {
            $fields['phone'] = 'A phone number or an email address, so we can reply';
        }

        if ($fields) {
            Api::validationFailed($fields);
        }

        $source = in_array($body['source'] ?? '', self::SOURCES, true) ? $body['source'] : 'contact';

        /* The contract calls these preferredDate and slot; the form on the
           site calls them date and time. Both are accepted rather than one of
           them being renamed, because the form's names are what the markup
           has said for as long as the site has existed. */
        $preferredDate = trim((string) ($body['preferredDate'] ?? $body['date'] ?? ''));
        $slot = trim((string) ($body['slot'] ?? $body['time'] ?? ''));

        $department = $this->lookup('departments', 'slug', $body['department'] ?? '');
        $doctor = $this->lookup('doctors', 'slug', $body['doctor'] ?? '');

        if ($subject === '') {
            $subject = $source === 'appointment'
                ? 'Appointment request' . ($department ? ' — ' . $this->label('departments', $department) : '')
                : 'Website enquiry';
        }

        $columns = [
            'name' => $name,
            'email' => $email ?: null,
            'phone' => $phone ?: null,
            'subject' => $subject,
            'message' => $message ?: null,
            'source' => $source,
            'department_id' => $department,
            'doctor_id' => $doctor,
            'preferred_date' => $preferredDate === '' ? null : substr($preferredDate, 0, 10),
            'preferred_slot' => $slot === '' ? null : substr($slot, 0, 40),
            'status' => 'new',
            'priority' => 'normal',
            'replies' => json_encode([]),
            'internal_notes' => json_encode([]),
            'received_at' => now_iso(),
            'ip' => RateLimit::clientIp(),
            'created_at' => now_iso(),
            'updated_at' => now_iso(),
        ];

        $publicId = $this->insert('enquiries', 'enq', $columns);

        RateLimit::hit('enquiry', RateLimit::clientIp());
        ActivityLog::record('create', 'enquiries', $publicId, $name . ' — ' . $subject);

        $this->notify(
            'enquiries',
            $publicId,
            $this->notifyAddresses(),
            ($source === 'appointment' ? 'Appointment request' : 'Enquiry') . ' — ' . $name,
            $this->enquiryMail($columns, $department, $doctor),
            $email
        );

        Api::created([
            'id' => $publicId,
            'receivedAt' => iso_datetime($columns['received_at']),
        ]);
    }

    /* =========================================================
       Applications
       ========================================================= */

    public function application(): never
    {
        if ($this->trapped('application')) {
            Api::created(['id' => null, 'appliedAt' => iso_datetime(now_iso())]);
        }

        /* Tighter than the enquiry form: an application carries two file
           uploads, and nobody applies for four jobs in an hour. */
        $this->guard('application', 3, 3600);

        $body = $this->body();
        $fields = [];

        $name = $this->text($body, 'name', 'name', $fields, true);
        $email = $this->text($body, 'email', 'email', $fields, true);
        $phone = $this->text($body, 'phone', 'phone', $fields, true);
        $experience = $this->text($body, 'experience', 'experience', $fields, true);
        $location = $this->text($body, 'location', 'location', $fields);
        $employer = $this->text($body, 'employer', 'employer', $fields);
        $coverNote = $this->text($body, 'coverLetter', 'coverNote', $fields);

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $fields['email'] = 'That does not look like an email address';
        }

        [$jobId, $jobTitle] = $this->resolveJob($body, $fields);

        if (empty($_FILES['resume']['name'])) {
            $fields['resume'] = 'Attach your CV';
        }

        if ($fields) {
            Api::validationFailed($fields);
        }

        /* The files land before the row, because the row has to record where
           they went. Anything that fails after this point deletes them again
           rather than leaving a stranger's CV on disk with nothing pointing at
           it. */
        $cv = Upload::store($_FILES['resume'], Upload::CV);

        if (!$cv) {
            Api::validationFailed(['resume' => Upload::$lastError]);
        }

        $letter = null;

        if (!empty($_FILES['coverLetterFile']['name'])) {
            $letter = Upload::store($_FILES['coverLetterFile'], Upload::CV);

            if (!$letter) {
                Upload::delete($cv['relativePath']);
                Api::validationFailed(['coverLetterFile' => Upload::$lastError]);
            }
        }

        $columns = [
            'job_id' => $jobId,
            'job_title' => $jobTitle,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'experience' => $experience,
            'current_employer' => $employer ?: null,
            'location' => $location ?: null,
            'cv_file' => $cv['original'],
            'cv_path' => $cv['relativePath'],
            'cv_size' => $cv['size'],
            'cover_note' => $coverNote ?: null,
            'cover_letter_file' => $letter['original'] ?? null,
            'cover_letter_path' => $letter['relativePath'] ?? null,
            'cover_letter_size' => $letter['size'] ?? null,
            'details' => json_encode($this->details($body), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'stage' => 'new',
            'notes' => json_encode([]),
            'applied_at' => now_iso(),
            'ip' => RateLimit::clientIp(),
            'created_at' => now_iso(),
            'updated_at' => now_iso(),
        ];

        try {
            $publicId = $this->insert('applications', 'app', $columns);
        } catch (Throwable $e) {
            Upload::delete($cv['relativePath']);

            if ($letter) {
                Upload::delete($letter['relativePath']);
            }

            throw $e;
        }

        RateLimit::hit('application', RateLimit::clientIp());
        ActivityLog::record('create', 'applications', $publicId, $name . ' — ' . $jobTitle);

        $attachments = [['path' => __BASEDIR__ . '/' . $cv['relativePath'], 'name' => $cv['original']]];

        if ($letter) {
            $attachments[] = ['path' => __BASEDIR__ . '/' . $letter['relativePath'], 'name' => $letter['original']];
        }

        $this->notify(
            'applications',
            $publicId,
            $this->careersAddresses($jobId),
            'Application — ' . $jobTitle . ' — ' . $name,
            $this->applicationMail($columns, $body),
            $email,
            $attachments
        );

        /* Third and last, and the only one whose failure costs nothing but a
           courtesy: the applicant's acknowledgement. */
        $this->acknowledge($email, $name, $jobTitle);

        Api::created([
            'id' => $publicId,
            'appliedAt' => iso_datetime($columns['applied_at']),
        ]);
    }

    /* =========================================================
       Guards
       ========================================================= */

    /**
     * True when the honeypot was filled in.
     *
     * The caller answers with a success. A bot told that it failed is a bot
     * that tries something else; a bot told it succeeded goes away.
     */
    private function trapped(string $action): bool
    {
        $value = trim((string) ($this->body()[self::HONEYPOT] ?? ''));

        if ($value === '') {
            return false;
        }

        error_log('[intake] Honeypot caught a ' . $action . ' from ' . RateLimit::clientIp());

        return true;
    }

    /**
     * The limiter is checked here and only counted after a row is written.
     *
     * `RateLimit::attempt()` would count every call, which reads well until an
     * applicant picks the wrong file twice and is locked out of the careers
     * page for an hour for it. What is worth limiting is submissions that
     * actually land — those cost a row, a stored file and two mails. A refused
     * one costs a validation pass and writes nothing.
     */
    private function guard(string $action, int $limit, int $seconds): void
    {
        if (!Csrf::verifyRequest()) {
            Api::fail(419, 'CSRF_EXPIRED', 'This page has been open a while — reload it and send again');
        }

        if (RateLimit::tooMany($action, $limit, $seconds, RateLimit::clientIp())) {
            Api::rateLimited($seconds);
        }

        if (!Recaptcha::verify($this->body()['recaptcha'] ?? $this->body()['g-recaptcha-response'] ?? null)) {
            Api::validationFailed(['recaptcha' => 'The spam check did not pass — try again']);
        }
    }

    /* =========================================================
       Reading the body
       ========================================================= */

    /**
     * A trimmed, length-checked string.
     *
     * The limit is the column's, so an over-long value is a named field in a
     * 422 rather than a driver error on MySQL — which rejects it outright in
     * strict mode and silently truncates it without.
     */
    private function text(array $body, string $key, string $limitKey, array &$fields, bool $required = false): string
    {
        $value = trim((string) ($body[$key] ?? ''));
        $limit = self::LIMITS[$limitKey] ?? 255;

        if ($required && $value === '') {
            $fields[$key] = 'Required';
            return '';
        }

        if (mb_strlen($value) > $limit) {
            $fields[$key] = 'Too long — the limit is ' . $limit . ' characters';
            return '';
        }

        return $value;
    }

    /**
     * The optional half of the application form.
     *
     * Kept as sent, in one JSON column, because nothing queries any of it and
     * every one of them is read at the same moment by the same person — see
     * the JSON-column rule in docs/php/02-schema.md.
     */
    private function details(array $body): array
    {
        $keys = [
            'qualification' => 'Highest qualification',
            'registration' => 'Council / registration number',
            'notice' => 'Notice period',
            'ctc' => 'Current CTC',
            'expectedCtc' => 'Expected CTC',
            'availableFrom' => 'Available from',
            'link' => 'LinkedIn or portfolio',
            'source' => 'Heard about us through',
        ];

        $out = [];

        foreach ($keys as $key => $label) {
            $value = trim((string) ($body[$key] ?? ''));

            if ($value !== '') {
                $out[$key] = ['label' => $label, 'value' => mb_substr($value, 0, 500)];
            }
        }

        return $out;
    }

    /**
     * The vacancy applied for.
     *
     * By slug where the form sends one, by title where it does not — the
     * position field is read-only on the page and carries the job's own title.
     * A title that matches nothing is still an application: it is recorded
     * against no job rather than refused, because the applicant did nothing
     * wrong and `job_title` is denormalised for exactly this.
     *
     * @return array{0: ?int, 1: string}
     */
    private function resolveJob(array $body, array &$fields): array
    {
        $slug = trim((string) ($body['job'] ?? ''));
        $position = trim((string) ($body['position'] ?? ''));

        if ($slug !== '') {
            $job = db_fetch_one(
                'SELECT id, title FROM jobs WHERE slug = ? AND deleted_at IS NULL',
                [$slug]
            );

            if ($job) {
                return [(int) $job['id'], (string) $job['title']];
            }
        }

        if ($position === '') {
            $fields['position'] = 'Which role is this for?';
            return [null, ''];
        }

        $job = db_fetch_one(
            'SELECT id, title FROM jobs WHERE title = ? AND deleted_at IS NULL',
            [$position]
        );

        return $job
            ? [(int) $job['id'], (string) $job['title']]
            : [null, mb_substr($position, 0, 191)];
    }

    /** A slug to its integer key, or null — an unknown one is not an error. */
    private function lookup(string $table, string $column, mixed $value): ?int
    {
        $value = trim((string) $value);

        if ($value === '' || $value === 'other') {
            return null;
        }

        $id = db_scalar(
            "SELECT id FROM {$table} WHERE {$column} = ? AND deleted_at IS NULL",
            [$value]
        );

        return $id === null || $id === false ? null : (int) $id;
    }

    private function label(string $table, ?int $id): string
    {
        if (!$id) {
            return '';
        }

        return (string) db_scalar("SELECT name FROM {$table} WHERE id = ?", [$id], '');
    }

    /* =========================================================
       Writing
       ========================================================= */

    /**
     * Inserts with a generated public id, retrying if two submissions raced
     * for the same number.
     *
     * The unique index is what decides the race; three attempts is more than
     * enough for a form nobody is submitting in bulk, and the alternative —
     * swallowing the error — would lose the row.
     */
    private function insert(string $table, string $prefix, array $columns): string
    {
        for ($attempt = 1; ; $attempt++) {
            $publicId = next_public_id($table, $prefix);
            $row = ['public_id' => $publicId] + $columns;

            try {
                db_execute(
                    'INSERT INTO ' . $table . ' (' . implode(', ', array_keys($row)) . ') VALUES ('
                    . implode(', ', array_fill(0, count($row), '?')) . ')',
                    array_values($row)
                );

                return $publicId;
            } catch (PDOException $e) {
                if ($attempt >= 3) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Mails the desk and records whether it went.
     *
     * Called after the row exists, and its failure never reaches the visitor:
     * they submitted a form and it was received, which is true whatever the
     * mail server did. The panel sees `notify_error` and can send it again.
     */
    private function notify(
        string $table,
        string $publicId,
        array $recipients,
        string $subject,
        string $html,
        string $replyTo = '',
        array $attachments = []
    ): void {
        if (!$recipients) {
            $this->recordNotification($table, $publicId, false, 'No notification address is configured');
            return;
        }

        $options = $attachments ? ['attachments' => $attachments] : [];

        if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            /* So that Reply reaches the person who wrote in, not the server. */
            $options['replyTo'] = $replyTo;
        }

        $sent = Mailer::send($recipients, $subject, $html, true, $options);

        $this->recordNotification($table, $publicId, $sent, Mailer::$lastError);
    }

    private function recordNotification(string $table, string $publicId, bool $sent, string $error): void
    {
        if (!$sent) {
            error_log('[intake] ' . $table . ' ' . $publicId . ' not notified: ' . $error);
        }

        db_execute(
            'UPDATE ' . $table . ' SET notified_at = ?, notify_error = ? WHERE public_id = ?',
            [
                $sent ? now_iso() : null,
                $sent ? null : mb_substr($error ?: 'The mail server refused the message', 0, 500),
                $publicId,
            ]
        );
    }

    /** The applicant's acknowledgement. Best effort, and nothing depends on it. */
    private function acknowledge(string $email, string $name, string $jobTitle): void
    {
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $hospital = (string) setting('general', 'name', 'Teresa Memorial Hospital');

        Mailer::send(
            $email,
            'We have your application — ' . $jobTitle,
            '<p>Dear ' . e($name) . ',</p>'
            . '<p>Thank you for applying for <strong>' . e($jobTitle) . '</strong> at ' . e($hospital)
            . '. Your CV has reached the HR desk.</p>'
            . '<p>Applications are reviewed in the order they arrive, and you will hear from us '
            . 'either way. There is no need to send it again.</p>'
            . '<p>' . e($hospital) . '</p>',
            true
        );
    }

    /* =========================================================
       Mail bodies
       ========================================================= */

    private function enquiryMail(array $columns, ?int $department, ?int $doctor): string
    {
        $rows = [
            'Name' => $columns['name'],
            'Email' => $columns['email'],
            'Phone' => $columns['phone'],
            'Source' => $columns['source'],
            'Department' => $this->label('departments', $department),
            'Doctor' => $doctor ? $this->label('doctors', $doctor) : '',
            'Preferred date' => $columns['preferred_date'],
            'Preferred time' => $columns['preferred_slot'],
            'Received' => $columns['received_at'] . ' UTC',
        ];

        return '<p>A new ' . ($columns['source'] === 'appointment' ? 'appointment request' : 'enquiry')
            . ' came in through the website.</p>'
            . $this->table($rows)
            . ($columns['message'] ? '<p><strong>Message</strong></p><p>' . nl2br(e($columns['message'])) . '</p>' : '')
            . '<p style="color:#667">Reply to this mail and it reaches the sender.</p>';
    }

    private function applicationMail(array $columns, array $body): string
    {
        $rows = [
            'Name' => $columns['name'],
            'Email' => $columns['email'],
            'Phone' => $columns['phone'],
            'Applying for' => $columns['job_title'],
            'Experience' => $columns['experience'],
            'Currently at' => $columns['current_employer'],
            'Location' => $columns['location'],
            'CV' => $columns['cv_file'] . ' (attached)',
        ];

        if ($columns['cover_letter_file']) {
            $rows['Cover letter'] = $columns['cover_letter_file'] . ' (attached)';
        }

        foreach ($this->details($body) as $detail) {
            $rows[$detail['label']] = $detail['value'];
        }

        $rows['Received'] = $columns['applied_at'] . ' UTC';

        return '<p>A new application came in through the careers page.</p>'
            . $this->table($rows)
            . ($columns['cover_note']
                ? '<p><strong>Cover letter</strong></p><p>' . nl2br(e($columns['cover_note'])) . '</p>'
                : '')
            . '<p style="color:#667">Reply to this mail and it reaches the applicant.</p>';
    }

    /** A definition table, skipping whatever is empty. */
    private function table(array $rows): string
    {
        $html = '<table cellpadding="6" style="border-collapse:collapse">';

        foreach ($rows as $label => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $html .= '<tr><td style="color:#667">' . e($label) . '</td>'
                . '<td><strong>' . e((string) $value) . '</strong></td></tr>';
        }

        return $html . '</table>';
    }

    /* =========================================================
       Where the mail goes
       ========================================================= */

    /** @return string[] */
    private function notifyAddresses(): array
    {
        return $this->addresses(setting('integrations', 'notifyEnquiryTo', []), 'email');
    }

    /**
     * HR, most specific first: the vacancy's own address, then the Careers
     * entry in the contact settings, then CAREERS_EMAIL. A department that
     * does its own hiring sets `applyEmail` on the posting and nothing else
     * has to change.
     *
     * @return string[]
     */
    private function careersAddresses(?int $jobId): array
    {
        if ($jobId) {
            $applyEmail = trim((string) db_scalar('SELECT apply_email FROM jobs WHERE id = ?', [$jobId], ''));

            if ($applyEmail !== '' && filter_var($applyEmail, FILTER_VALIDATE_EMAIL)) {
                return [$applyEmail];
            }
        }

        foreach ($this->addresses(setting('contact', 'emails', []), 'address', 'Careers') as $address) {
            return [$address];
        }

        $fallback = trim((string) env('CAREERS_EMAIL', ''));

        return $fallback === '' ? [] : [$fallback];
    }

    /**
     * Valid addresses out of a settings repeater.
     *
     * @param string $label when given, only rows carrying it
     * @return string[]
     */
    private function addresses(mixed $list, string $key, string $label = ''): array
    {
        $out = [];

        foreach (is_array($list) ? $list : [] as $entry) {
            if (!is_array($entry)) {
                $entry = [$key => $entry];
            }

            if ($label !== '' && ($entry['label'] ?? '') !== $label) {
                continue;
            }

            $address = trim((string) ($entry[$key] ?? ''));

            if ($address !== '' && filter_var($address, FILTER_VALIDATE_EMAIL)) {
                $out[] = $address;
            }
        }

        return $out;
    }
}
