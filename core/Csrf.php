<?php

/**
 * CSRF tokens.
 *
 * One token per session, checked on every state-changing request. The panel
 * is a set of fetch() calls from JavaScript, so the token travels in the
 * X-CSRF-Token header; the public forms are ordinary posts, so it also travels
 * in a hidden field.
 *
 * SameSite=Lax on the session cookie already blocks the cross-site form post,
 * but not a same-site subdomain or a stray <img> triggering a GET-shaped
 * mutation. This is the check that does not depend on browser behaviour.
 */
class Csrf
{
    private const KEY = '_csrf';
    public const HEADER = 'X-CSRF-Token';
    public const FIELD = '_token';

    public static function token(): string
    {
        Auth::start();

        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::KEY];
    }

    /** A hidden input for a plain HTML form. */
    public static function field(): string
    {
        return '<input type="hidden" name="' . self::FIELD . '" value="' . e(self::token()) . '">';
    }

    public static function verify(?string $candidate): bool
    {
        Auth::start();

        $expected = $_SESSION[self::KEY] ?? '';

        if ($expected === '' || $candidate === null || $candidate === '') {
            return false;
        }

        return hash_equals($expected, $candidate);
    }

    /**
     * Checks the current request. Reads the header first, then the body — the
     * panel sends the header, the public forms send the field.
     *
     * Safe methods pass without a token: a GET that changes state is a bug to
     * fix at the endpoint, not something to paper over here.
     */
    public static function verifyRequest(): bool
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return true;
        }

        $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if ($header !== null && self::verify($header)) {
            return true;
        }

        $body = $_POST[self::FIELD] ?? null;
        if ($body === null && class_exists('ApiRequest')) {
            $parsed = ApiRequest::body();
            $body = $parsed[self::FIELD] ?? null;
        }

        return self::verify(is_string($body) ? $body : null);
    }
}
