<?php

/**
 * The two things that happen to an enquiry after it arrives —
 * docs/07-api-contract.md §Enquiries & applications.
 *
 *     POST api/enquiries/{id}/reply   {body, templateId}
 *     POST api/enquiries/{id}/note    {body}
 *
 * Everything else about an enquiry — listing, filtering, assigning, closing,
 * marking spam — is a PATCH through the generic controller, which is why this
 * extends it: both endpoints answer with a whole enquiry record, and it has to
 * be the same record, field for field, that GET /api/enquiries/{id} returns.
 *
 * `replies` and `internal_notes` are JSON arrays rather than a messages table
 * (docs/php/02-schema.md): they are appended to and read as a block on one
 * screen and never searched across enquiries.
 */
class EnquiryController extends ResourceController
{
    /**
     * This controller is only ever routed to for enquiries, so the resource is
     * named rather than read from the URL.
     */
    protected function resource(): array
    {
        return $this->resource ??= ResourceRegistry::get('enquiries');
    }

    /**
     * A reply to the sender: recorded first, sent second.
     *
     * The record is the point. A desk that typed a reply into the panel has
     * answered the enquiry whether or not the mail server was reachable, and
     * an exception on the way out must not lose what they wrote — so the row
     * is written before anything is attempted, and the entry carries whether
     * it actually went.
     *
     * An enquiry with no email address is a phone enquiry. Recording the reply
     * is still the right thing to do; there is simply nothing to send.
     */
    public function reply(): never
    {
        $r = $this->resource();
        $id = (string) $this->param('id');
        $row = $this->find($r, $id, true);

        if (!$row) {
            Api::notFound();
        }

        $body = $this->body();
        $message = trim((string) ($body['body'] ?? ''));

        if ($message === '') {
            Api::validationFailed(['body' => 'Write a reply first']);
        }

        $user = Auth::user();
        $at = iso_datetime(now_iso());

        $entry = [
            'by' => (string) ($user['name'] ?? 'The desk'),
            'at' => $at,
            'body' => $message,
            'emailed' => false,
        ];

        /* Which canned reply it started from, so a desk reading the thread
           later can tell a template from something somebody wrote. */
        if (!empty($body['templateId'])) {
            $entry['templateId'] = (string) $body['templateId'];
        }

        $replies = json_column($row['replies']);
        $replies[] = $entry;

        /* Replying to something filed as spam should not quietly un-file it —
           that is a deliberate act, not a side effect of answering. */
        $status = $row['status'] === 'spam' ? 'spam' : 'replied';

        /* Whoever answers it owns it, unless somebody already does. */
        $assigned = $row['assigned_to'] ?: $this->userId();

        db_execute(
            'UPDATE enquiries SET replies = ?, status = ?, assigned_to = ?,
                    updated_at = ?, updated_by = ? WHERE id = ?',
            [
                json_encode($replies, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $status,
                $assigned,
                now_iso(),
                $this->userId(),
                $row['id'],
            ]
        );

        $recipient = trim((string) ($row['email'] ?? ''));

        if ($recipient !== '' && filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->deliver($row, $message, $recipient, (int) $row['id'], count($replies));
        }

        ActivityLog::record(
            'update',
            'enquiries',
            $id,
            'Replied to ' . (string) $row['name']
                . ($recipient === '' ? ' (no email on file — recorded only)' : '')
        );

        Api::ok($this->row($r, $this->find($r, $id, true)));
    }

    /**
     * An internal note. Never sent anywhere, never shown to the sender.
     */
    public function note(): never
    {
        $r = $this->resource();
        $id = (string) $this->param('id');
        $row = $this->find($r, $id, true);

        if (!$row) {
            Api::notFound();
        }

        $message = trim((string) ($this->body()['body'] ?? ''));

        if ($message === '') {
            Api::validationFailed(['body' => 'Write the note first']);
        }

        $user = Auth::user();
        $notes = json_column($row['internal_notes']);

        $notes[] = [
            'by' => (string) ($user['name'] ?? 'The desk'),
            'at' => iso_datetime(now_iso()),
            'body' => $message,
        ];

        db_execute(
            'UPDATE enquiries SET internal_notes = ?, updated_at = ?, updated_by = ? WHERE id = ?',
            [
                json_encode($notes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                now_iso(),
                $this->userId(),
                $row['id'],
            ]
        );

        ActivityLog::record('update', 'enquiries', $id, 'Note added to ' . (string) $row['name']);

        Api::ok($this->row($r, $this->find($r, $id, true)));
    }

    /* --------------------------------------------------------- */

    /**
     * Sends the reply and marks the stored entry as sent.
     *
     * The second write only touches the entry this request added, and only
     * while nothing else has appended in between — two people answering the
     * same enquiry in the same second is unlikely, and losing one of their
     * replies to a flag update would not be.
     */
    private function deliver(array $row, string $message, string $recipient, int $id, int $expected): void
    {
        $hospital = (string) setting('general', 'name', 'Teresa Memorial Hospital');
        $subject = trim((string) ($row['subject'] ?? ''));

        $sent = Mailer::send(
            $recipient,
            $subject === '' ? 'A reply from ' . $hospital : 'Re: ' . $subject,
            /* The desk types plain text into a textarea. Escaped and turned
               into paragraphs — never passed through as markup, whatever it
               contains. */
            '<p>' . implode('</p><p>', array_map('e', preg_split('/\n{2,}/', trim($message)) ?: [])) . '</p>'
            . '<hr><p style="color:#667">' . e($hospital)
            . ($this->replyTo() === '' ? '' : ' — reply to this message and it reaches the desk.') . '</p>',
            true,
            $this->replyTo() === '' ? [] : ['replyTo' => $this->replyTo()]
        );

        if (!$sent) {
            error_log('[enquiry] Reply to ' . $recipient . ' was not sent: ' . Mailer::$lastError);
            return;
        }

        $stored = json_column(db_scalar('SELECT replies FROM enquiries WHERE id = ?', [$id]));

        if (count($stored) !== $expected) {
            return;
        }

        $stored[$expected - 1]['emailed'] = true;

        db_execute(
            'UPDATE enquiries SET replies = ? WHERE id = ?',
            [json_encode($stored, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $id]
        );
    }

    /**
     * The desk's own address, so an answer to a reply lands in a mailbox a
     * person reads rather than at whatever the server sends as.
     */
    private function replyTo(): string
    {
        $list = setting('integrations', 'notifyEnquiryTo', []);

        foreach (is_array($list) ? $list : [] as $entry) {
            $address = trim((string) (is_array($entry) ? ($entry['email'] ?? '') : $entry));

            if ($address !== '' && filter_var($address, FILTER_VALIDATE_EMAIL)) {
                return $address;
            }
        }

        return '';
    }
}
