<?php

require_once __DIR__ . '/../core/RouteProvider.php';

class ViewRouteProvider extends RouteProvider
{
    public static function routes(): array
    {
        return [
            /* The admin panel */
            'admin' => ['AdminController', 'index'],
            'admin/login' => ['AdminController', 'login'],
            'admin/logout' => ['AdminController', 'logout'],
            'admin/{screen}' => ['AdminController', 'screen'],
        ];
    }
}

return ViewRouteProvider::routes();
