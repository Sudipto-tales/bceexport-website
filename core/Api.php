<?php

/**
 * The response envelope from docs/07-api-contract.md.
 *
 * The framework ships ApiResponse, which wraps everything as
 * {success, message, data}. The panel was written against a different shape
 * eight months before this backend existed:
 *
 *     { "data": {...}, "meta": {"page": 1, "pageSize": 20, "total": 47} }
 *     { "error": {"code": "VALIDATION_FAILED", "message": "...",
 *                 "fields": {"slug": "Already in use"}} }
 *
 * Changing the panel to suit the framework would mean touching every one of
 * roughly forty page scripts. Changing the envelope is this file. ApiResponse
 * is left alone for the framework's own JWT endpoints.
 *
 * The `code` is what the panel switches on; `message` is what it shows a
 * person, and `fields` is what puts a red line under an input.
 */
final class Api
{
    public static function ok(mixed $data = null, ?array $meta = null): never
    {
        self::send(200, $meta === null ? ['data' => $data] : ['data' => $data, 'meta' => $meta]);
    }

    public static function created(mixed $data = null): never
    {
        self::send(201, ['data' => $data]);
    }

    public static function noContent(): never
    {
        http_response_code(204);
        exit;
    }

    /* ---------------------------------------------------------
       Errors — one method per code in the contract
       --------------------------------------------------------- */

    public static function unauthenticated(string $message = 'Sign in to continue'): never
    {
        self::fail(401, 'UNAUTHENTICATED', $message);
    }

    public static function forbidden(string $message = 'You do not have access to that'): never
    {
        self::fail(403, 'FORBIDDEN', $message);
    }

    public static function notFound(string $message = 'That record no longer exists'): never
    {
        self::fail(404, 'NOT_FOUND', $message);
    }

    /**
     * @param array<string,string> $fields field name → what is wrong with it
     */
    public static function validationFailed(array $fields): never
    {
        $count = count($fields);

        self::fail(
            422,
            'VALIDATION_FAILED',
            $count === 1 ? '1 field needs attention' : "{$count} fields need attention",
            ['fields' => $fields]
        );
    }

    public static function conflict(string $message, array $fields = []): never
    {
        self::fail(409, 'CONFLICT', $message, $fields ? ['fields' => $fields] : []);
    }

    /**
     * A delete refused because something still points at the record.
     *
     * The list matters as much as the refusal: "in use by 3 records" sends
     * somebody hunting, and the panel renders these straight into the confirm
     * dialog.
     *
     * @param array<int,array{entity:string,id:string,label:string}> $dependents
     */
    public static function hasDependents(array $dependents): never
    {
        $count = count($dependents);

        self::fail(
            409,
            'HAS_DEPENDENTS',
            $count === 1 ? '1 record still uses this' : "{$count} records still use this",
            ['dependents' => $dependents]
        );
    }

    public static function rateLimited(int $retryAfter = 60): never
    {
        header('Retry-After: ' . $retryAfter);
        self::fail(429, 'RATE_LIMITED', 'Too many attempts — wait a moment and try again');
    }

    public static function serverError(string $message = 'Something went wrong'): never
    {
        self::fail(500, 'SERVER_ERROR', $message);
    }

    public static function fail(int $status, string $code, string $message, array $extra = []): never
    {
        self::send($status, ['error' => ['code' => $code, 'message' => $message] + $extra]);
    }

    /* --------------------------------------------------------- */

    private static function send(int $status, array $payload): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        /* The panel is same-origin, but these responses carry names,
           addresses and CVs. Nothing here should sit in a shared cache. */
        header('Cache-Control: no-store');

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
