<?php

require_once __DIR__ . '/../core/RouteProvider.php';

class ApiGatewayProvider extends RouteProvider
{
    public static function routes(): array
    {
        return [
            /* Auth */
            'POST:api/auth/login' => ['AuthController', 'login'],
            'POST:api/auth/logout' => ['AuthController', 'logout', 'session'],
            'GET:api/auth/me' => ['AuthController', 'me', 'session'],

            /* Public intake */
            'POST:api/public/enquiry' => ['PublicIntakeController', 'enquiry'],
            'POST:api/enquiries' => ['PublicIntakeController', 'enquiry'],

            /* Settings */
            'GET:api/settings' => ['SettingsController', 'index', 'session'],
            'PATCH:api/settings/{group}' => ['SettingsController', 'update', 'session'],

            /* Media & Uploads */
            'GET:api/media' => ['MediaController', 'index', 'session'],
            'POST:api/media' => ['MediaController', 'store', 'session'],
            'DELETE:api/media/{id}' => ['MediaController', 'destroy', 'session'],

            /* Dashboard & Bootstrap */
            'GET:api/bootstrap' => ['BootstrapController', 'index', 'session'],
            'GET:api/dashboard/summary' => ['DashboardController', 'summary', 'session'],

            /* Generic Resource Block */
            'POST:api/{resource}/reorder' => ['ResourceController', 'reorder', 'session'],
            'POST:api/{resource}/bulk' => ['ResourceController', 'bulk', 'session'],
            'POST:api/{resource}/{id}/restore' => ['ResourceController', 'restore', 'session'],

            'GET:api/{resource}' => ['ResourceController', 'index', 'session'],
            'POST:api/{resource}' => ['ResourceController', 'store', 'session'],
            'GET:api/{resource}/{id}' => ['ResourceController', 'show', 'session'],
            'PATCH:api/{resource}/{id}' => ['ResourceController', 'update', 'session'],
            'DELETE:api/{resource}/{id}' => ['ResourceController', 'destroy', 'session'],
        ];
    }
}

return ApiGatewayProvider::routes();
