<?php

/**
 * reCAPTCHA verification for the two endpoints anyone can reach.
 *
 * Configured through `settings.integrations` — `recaptchaSiteKey` renders the
 * widget, `recaptchaSecret` checks the token here. Both ship empty, and with
 * no secret this passes everything: an install that has not been given keys
 * has to be able to take an enquiry, and the honeypot and the rate limiter are
 * still in the way.
 *
 * It also passes when Google itself cannot be reached.
 *
 * That is a deliberate choice and it is worth being explicit about. Failing
 * closed would mean that a network problem at the hospital's end silently
 * stops every contact form and every job application — for a hospital, losing
 * a patient's message costs more than accepting a spam one, and the two other
 * defences do not depend on anything outside this server.
 */
final class Recaptcha
{
    private const ENDPOINT = 'https://www.google.com/recaptcha/api/siteverify';

    /** Seconds. A form submit must not sit waiting on somebody else's server. */
    private const TIMEOUT = 5;

    /** The reason the last check failed, for the caller to log. */
    public static string $lastError = '';

    public static function isConfigured(): bool
    {
        return self::secret() !== '';
    }

    public static function verify(?string $token, ?string $ip = null): bool
    {
        self::$lastError = '';
        $secret = self::secret();

        if ($secret === '') {
            return true;
        }

        $token = trim((string) $token);

        /* A missing token with a secret configured is the one case that is
           refused outright: the widget is on the page, so nothing legitimate
           submits without one. */
        if ($token === '') {
            self::$lastError = 'No captcha token was sent';
            return false;
        }

        $response = self::post([
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $ip ?? RateLimit::clientIp(),
        ]);

        if ($response === null) {
            error_log('[recaptcha] Could not reach the verification service — allowing the submission');
            return true;
        }

        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            error_log('[recaptcha] Unreadable response — allowing the submission');
            return true;
        }

        if (empty($decoded['success'])) {
            self::$lastError = implode(', ', (array) ($decoded['error-codes'] ?? ['failed']));
            return false;
        }

        return true;
    }

    private static function secret(): string
    {
        if (!function_exists('setting')) {
            return '';
        }

        return trim((string) setting('integrations', 'recaptchaSecret', ''));
    }

    /** @return string|null the body, or null if the service was unreachable */
    private static function post(array $fields): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init(self::ENDPOINT);

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($fields),
                CURLOPT_TIMEOUT => self::TIMEOUT,
                CURLOPT_CONNECTTIMEOUT => self::TIMEOUT,
            ]);

            $body = curl_exec($ch);
            curl_close($ch);

            return $body === false ? null : (string) $body;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query($fields),
                'timeout' => self::TIMEOUT,
            ],
        ]);

        $body = @file_get_contents(self::ENDPOINT, false, $context);

        return $body === false ? null : $body;
    }
}
