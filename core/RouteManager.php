<?php

class RouteManager
{
    public static function dispatch(array $routes): void
    {
        $route = self::resolveRoute();

        if (str_starts_with($route, 'api/')) {
            self::dispatchApi($route, $routes);
            return;
        }

        self::dispatchView($route, $routes);
    }

    /* ---------------------------------------------------------
       Frontend
       --------------------------------------------------------- */

    private static function dispatchView(string $route, array $routes): void
    {
        [$handler, $params] = self::matchViewRoute($route, $routes);

        if (!$handler) {
            self::notFound($routes);
            return;
        }

        [$class, $method] = $handler;

        if (!class_exists($class)) {
            self::fail("Controller not found ({$class})");
            return;
        }

        $controller = new $class();

        if (!method_exists($controller, $method)) {
            self::fail("Controller method not found ({$class}::{$method})");
            return;
        }

        if (method_exists($controller, 'setRouteParams')) {
            $controller->setRouteParams($params);
        }

        call_user_func([$controller, $method]);
    }

    /**
     * Exact match wins, then patterns.
     *
     * The framework originally matched frontend routes by array key alone,
     * which made a URL like /blog/how-to-read-a-blood-report impossible to
     * express — every post would have needed its own route entry, written at
     * boot, before the database connection exists. API routes already had
     * {param} matching; this is the same matcher, applied to both.
     *
     * Exact keys are tried first, so a literal route always beats a pattern
     * that would also match it: 'blog/archive' wins over 'blog/{slug}'
     * whatever order they are declared in.
     */
    private static function matchViewRoute(string $route, array $routes): array
    {
        if (isset($routes[$route]) && !self::isApiKey($route)) {
            return [$routes[$route], []];
        }

        foreach ($routes as $pattern => $handler) {
            if (self::isApiKey($pattern) || !str_contains($pattern, '{')) {
                continue;
            }

            $params = self::matchPattern($pattern, $route);
            if ($params !== null) {
                return [$handler, $params];
            }
        }

        return [null, []];
    }

    /** API keys carry a leading "GET:" and are never frontend routes. */
    private static function isApiKey(string $key): bool
    {
        return (bool) preg_match('/^[A-Z]+:/', $key);
    }

    /**
     * Renders the 404 through the '404' route when one is declared, so the
     * error page is a normal controller with the site's normal chrome. Falls
     * back to plain text — a framework that fatals while reporting a missing
     * page is worse than one that just says "Not found".
     */
    private static function notFound(array $routes): void
    {
        http_response_code(404);

        if (isset($routes['404'])) {
            [$class, $method] = $routes['404'];

            if (class_exists($class) && method_exists($class, $method)) {
                call_user_func([new $class(), $method]);
                return;
            }
        }

        header('Content-Type: text/plain; charset=utf-8');
        echo 'Not found';
    }

    private static function fail(string $message): void
    {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');

        if (defined('APP_DEBUG') && APP_DEBUG) {
            echo "Server Error: {$message}";
            return;
        }

        error_log("[Vayu] {$message}");
        echo 'Server Error';
    }

    /* ---------------------------------------------------------
       API
       --------------------------------------------------------- */

    private static function dispatchApi(string $route, array $routes): void
    {
        Cors::handle();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
            return;
        }

        $method = HttpMethod::fromRequest();
        [$handler, $params] = self::matchApiRoute($method, $route, $routes);

        if (!$handler) {
            ApiResponse::notFound('API endpoint not found');
        }

        [$class, $action] = $handler;

        self::applyMiddleware($handler[2] ?? null);

        if (!class_exists($class)) {
            ApiResponse::error("Controller not found: {$class}", 500);
        }

        $controller = new $class();

        if (!method_exists($controller, $action)) {
            ApiResponse::error("Method not found: {$action}", 500);
        }

        if (method_exists($controller, 'setRouteParams')) {
            $controller->setRouteParams($params);
        }

        /**
         * An uncaught throwable in an API handler is worse than it looks. PHP
         * prints its message and stack trace as HTML and leaves the status at
         * 200, so the caller gets a success carrying a page of file paths and
         * SQL. The panel then tries to parse it as JSON and reports something
         * unrelated.
         *
         * Every failure that reaches here leaves as a 500 in the contract's
         * envelope, with the detail in the error log where it belongs.
         */
        try {
            $controller->{$action}();
        } catch (Throwable $e) {
            error_log('[api] ' . $e::class . ': ' . $e->getMessage()
                . ' in ' . $e->getFile() . ':' . $e->getLine());

            if (class_exists('Api')) {
                Api::serverError(
                    defined('APP_DEBUG') && APP_DEBUG
                        ? $e::class . ': ' . $e->getMessage()
                        : 'Something went wrong'
                );
            }

            ApiResponse::error('Server Error', 500);
        }
    }

    /**
     * 'session' — the admin panel. A cookie session plus a CSRF token on
     *             anything that writes. This is what the panel actually uses;
     *             see docs/07-api-contract.md.
     * 'auth'    — a JWT bearer token. Kept for anything machine-to-machine
     *             that may want it later, and because the framework shipped
     *             with it.
     *
     * A route naming a middleware that does not exist is refused rather than
     * waved through — a typo in a route table should not silently open an
     * endpoint.
     */
    private static function applyMiddleware(?string $middleware): void
    {
        if ($middleware === null || $middleware === '') {
            return;
        }

        switch ($middleware) {
            case 'session':
                /* The contract's envelope, not the framework's: the panel
                   switches on error.code, and a refusal it cannot classify is
                   a refusal it reports as something else. */
                if (!Auth::isAuthenticated()) {
                    Api::unauthenticated();
                }

                if (!Csrf::verifyRequest()) {
                    Api::fail(419, 'CSRF_EXPIRED', 'Your session expired — reload the page and try again');
                }
                break;

            case 'auth':
                if (!JwtAuth::authenticate()) {
                    ApiResponse::unauthorized();
                }
                break;

            default:
                ApiResponse::error("Unknown middleware: {$middleware}", 500);
        }
    }

    private static function matchApiRoute(HttpMethod $method, string $route, array $routes): array
    {
        $key = $method->value . ':' . $route;

        if (isset($routes[$key])) {
            return [$routes[$key], []];
        }

        foreach ($routes as $pattern => $handler) {
            if (!str_starts_with($pattern, $method->value . ':')) {
                continue;
            }

            $pathPattern = substr($pattern, strlen($method->value) + 1);

            if (!str_contains($pathPattern, '{')) {
                continue;
            }

            $params = self::matchPattern($pathPattern, $route);
            if ($params !== null) {
                return [$handler, $params];
            }
        }

        return [null, []];
    }

    /* ---------------------------------------------------------
       Shared
       --------------------------------------------------------- */

    /**
     * Turns 'departments/{slug}' into a regex and matches the request path
     * against it. Returns the captured parameters, or null for no match —
     * which is deliberately distinct from an empty array, meaning a pattern
     * that matched and captured nothing.
     *
     * A {param} never spans a slash, so 'blog/{slug}' does not swallow
     * 'blog/2026/january'. Everything that is not a placeholder is quoted, so
     * a dot in a route is a dot and not "any character".
     */
    private static function matchPattern(string $pattern, string $route): ?array
    {
        $parts = preg_split('/(\{\w+\})/', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE);
        $regex = '';

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if (preg_match('/^\{(\w+)\}$/', $part, $m)) {
                $regex .= '(?P<' . $m[1] . '>[^/]+)';
            } else {
                $regex .= preg_quote($part, '#');
            }
        }

        if (!preg_match('#^' . $regex . '$#', $route, $matches)) {
            return null;
        }

        return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    }

    public static function resolveRoute(): string
    {
        if (!empty($_GET['route'])) {
            return trim($_GET['route'], '/');
        }

        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath = str_replace('\\', '/', dirname($scriptName));

        if ($basePath !== '/' && $basePath !== '.' && str_starts_with($requestUri, $basePath)) {
            $requestUri = substr($requestUri, strlen($basePath));
        }

        $route = trim($requestUri, '/');
        return $route === '' ? 'default' : $route;
    }
}
