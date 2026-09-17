<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

if (file_exists(__BASEDIR__ . '/vendor/autoload.php')) {
    require_once __BASEDIR__ . '/vendor/autoload.php';
}

/**
 * SMTP, over PHPMailer.
 *
 * Credentials come from the environment. They used to be literals in this
 * file, which meant the repository carried a working Gmail app password and
 * every deployment sent as the same personal account.
 *
 * send() returns a bool and never throws. Callers are intake endpoints: a job
 * application must be saved whether or not the notification goes out, so a
 * dead SMTP server has to be a false, not an exception unwinding the request
 * before the row is written.
 */
class Mailer
{
    /** Set by the last failed send, for the caller to log or store. */
    public static string $lastError = '';

    /**
     * One SMTP value: the environment first, the `integrations` settings group
     * second.
     *
     * Both have to work. A secret belongs in `.env` and not in a database
     * table a panel user can read — but the panel has an SMTP screen, and a
     * screen whose fields do nothing is worse than no screen. So a value set
     * in the environment wins, and the panel fills in whatever the environment
     * leaves blank. `.env.example` ships the host, port and encryption filled
     * in and the credentials empty, which is the split this is built around.
     */
    private static function conf(string $envKey, string $settingKey, string $default = ''): string
    {
        $fromEnv = trim((string) env($envKey, ''));

        if ($fromEnv !== '') {
            return $fromEnv;
        }

        $fromSettings = self::integrations()[$settingKey] ?? '';

        return is_scalar($fromSettings) && trim((string) $fromSettings) !== ''
            ? trim((string) $fromSettings)
            : $default;
    }

    /**
     * The `integrations` settings group, or [].
     *
     * Guarded twice over. Mail is sent from the console as well as from a
     * request, and a mailer that fataled because the models were not loaded or
     * the database was unreachable would take the send with it — the whole
     * point of this class is that a failure to mail is a false, not an
     * exception.
     */
    private static function integrations(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        if (!function_exists('settings_group')) {
            return $cache = [];
        }

        try {
            return $cache = settings_group('integrations');
        } catch (Throwable $e) {
            error_log('[Mailer] Could not read the integrations settings: ' . $e->getMessage());
            return $cache = [];
        }
    }

    /**
     * What a send would use, minus the password. For the panel's SMTP test to
     * report the host it actually tried.
     *
     * @return array{host: string, port: int, secure: string, username: string, from: string, fromName: string}
     */
    public static function describe(): array
    {
        $username = self::conf('MAIL_USERNAME', 'smtpUser');

        return [
            'host' => self::conf('MAIL_HOST', 'smtpHost', 'localhost'),
            'port' => (int) self::conf('MAIL_PORT', 'smtpPort', '587'),
            'secure' => strtolower(self::conf('MAIL_SECURE', 'smtpSecure', 'tls')),
            'username' => $username,
            'from' => self::conf('MAIL_FROM_ADDRESS', 'smtpFromEmail') ?: $username,
            'fromName' => self::conf('MAIL_FROM_NAME', 'smtpFromName', (string) env('APP_NAME', 'Vayu')),
        ];
    }

    protected static function getMailer(): PHPMailer
    {
        $config = self::describe();
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->Port = $config['port'];
        $mail->CharSet = 'UTF-8';

        if ($config['username'] !== '') {
            $mail->SMTPAuth = true;
            $mail->Username = $config['username'];
            $mail->Password = self::conf('MAIL_PASSWORD', 'smtpPass');
        }

        if ($config['secure'] === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($config['secure'] === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        }

        if ($config['from'] !== '') {
            $mail->setFrom($config['from'], $config['fromName']);
        }

        return $mail;
    }

    /**
     * @param string|string[] $to
     * @param array $options  replyTo:  string|['address' => …, 'name' => …]
     *                        cc, bcc:  string|string[]
     *                        attachments: [['path' => …, 'name' => …], …]
     *                                     or a plain list of paths
     */
    public static function send($to, string $subject, string $body, bool $isHtml = false, array $options = []): bool
    {
        self::$lastError = '';

        $recipients = array_filter(
            array_map('trim', (array) $to),
            static fn ($address) => filter_var($address, FILTER_VALIDATE_EMAIL) !== false
        );

        if (!$recipients) {
            self::$lastError = 'No valid recipient';
            error_log('[Mailer] ' . self::$lastError . ': ' . implode(', ', (array) $to));
            return false;
        }

        try {
            $mail = self::getMailer();

            foreach ($recipients as $address) {
                $mail->addAddress($address);
            }

            foreach ((array) ($options['cc'] ?? []) as $address) {
                $mail->addCC($address);
            }

            foreach ((array) ($options['bcc'] ?? []) as $address) {
                $mail->addBCC($address);
            }

            /* Reply-To matters on the application and enquiry mails: HR hits
               reply and reaches the applicant, not the server's own mailbox. */
            if (!empty($options['replyTo'])) {
                $reply = $options['replyTo'];
                if (is_array($reply)) {
                    $mail->addReplyTo($reply['address'], $reply['name'] ?? '');
                } else {
                    $mail->addReplyTo($reply);
                }
            }

            foreach ((array) ($options['attachments'] ?? []) as $attachment) {
                $path = is_array($attachment) ? ($attachment['path'] ?? '') : $attachment;
                $name = is_array($attachment) ? ($attachment['name'] ?? '') : '';

                if ($path === '' || !is_readable($path)) {
                    /* A CV that has gone missing from disk should not stop HR
                       hearing about the application — the details are in the
                       body, and the panel can still stream the file. */
                    error_log("[Mailer] Attachment not readable, skipped: {$path}");
                    continue;
                }

                $mail->addAttachment($path, $name);
            }

            $mail->isHTML($isHtml);
            $mail->Subject = $subject;
            $mail->Body = $body;

            if ($isHtml) {
                $mail->AltBody = trim(html_entity_decode(strip_tags($body), ENT_QUOTES, 'UTF-8'));
            }

            $mail->send();
            return true;
        } catch (PHPMailerException $e) {
            self::$lastError = $e->getMessage();
            error_log('[Mailer] ' . self::$lastError);
            return false;
        } catch (Throwable $e) {
            self::$lastError = $e->getMessage();
            error_log('[Mailer] ' . self::$lastError);
            return false;
        }
    }

    /**
     * Is there enough configuration to attempt a send at all?
     *
     * Asked of the configured values, not of describe(), whose defaults would
     * make an empty install look like a working one pointed at localhost.
     */
    public static function isConfigured(): bool
    {
        $from = self::conf('MAIL_FROM_ADDRESS', 'smtpFromEmail')
            ?: self::conf('MAIL_USERNAME', 'smtpUser');

        return self::conf('MAIL_HOST', 'smtpHost') !== '' && $from !== '';
    }
}
