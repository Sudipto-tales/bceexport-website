<?php

abstract class BaseController
{
    /** Captured from the URL pattern — 'departments/{slug}' gives ['slug' => …]. */
    protected array $routeParams = [];

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    protected function param(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    /** A query-string value, trimmed. Absent and empty are the same thing here. */
    protected function query(string $key, mixed $default = null): mixed
    {
        $value = $_GET[$key] ?? null;

        if (is_string($value)) {
            $value = trim($value);
        }

        return ($value === null || $value === '') ? $default : $value;
    }

    protected function viewData(array $data = [], array $extras = []): array
    {
        return view_data($data, $extras);
    }

    protected function respond(string $view, array $data = [])
    {
        return render_view($view, $data);
    }

    /**
     * A page that was asked for by URL but does not exist — an unpublished
     * doctor, a deleted post. Renders the site's own 404 rather than an empty
     * shell, because a page that looks fine but is missing its content reads
     * as a bug to the visitor and to a crawler alike.
     */
    protected function notFound(string $message = ''): void
    {
        http_response_code(404);
        $this->respond('/app/page/site/404.php', $this->viewData([
            'title' => 'Page not found',
            'notFoundMessage' => $message,
        ]));
    }

    protected function redirect(string $path, int $status = 302): never
    {
        header('Location: ' . (str_starts_with($path, 'http') ? $path : base_url($path)), true, $status);
        exit;
    }

    protected function apiGet(string $url, array $query = [], array $headers = [])
    {
        return api_get($url, $query, $headers);
    }

    protected function apiPost(string $url, array $payload = [], array $headers = [])
    {
        return api_post($url, $payload, $headers);
    }

    protected function defaultAppName(): string
    {
        $appName = env('APP_NAME', null);
        if ($appName !== null && $appName !== '') {
            return $appName;
        }

        $constant = static::class . '::DEFAULT_APP_NAME';
        if (defined($constant)) {
            return constant($constant);
        }

        return 'vayu';
    }
}
