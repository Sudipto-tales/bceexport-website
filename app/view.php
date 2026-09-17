<?php

require_once __DIR__ . '/../core/RouteProvider.php';

class ViewRouteProvider extends RouteProvider
{
    public static function routes(): array
    {
        return [
            /* -------------------------------------------------------
               Public site
               ------------------------------------------------------- */
            ''                    => ['PublicController', 'home'],
            'about'               => ['PublicController', 'about'],
            'services'            => ['PublicController', 'services'],
            'contact'             => ['PublicController', 'contact'],
            'quote'               => ['PublicController', 'quote'],
            'team'                => ['PublicController', 'team'],
            'testimonials'        => ['PublicController', 'testimonials'],
            'products/{slug}'     => ['PublicController', 'category'],
            '404'                 => ['PublicController', 'notFoundPage'],

            /* -------------------------------------------------------
               Admin panel
               ------------------------------------------------------- */
            'admin'               => ['AdminController', 'index'],
            'admin/login'         => ['AdminController', 'login'],
            'admin/logout'        => ['AdminController', 'logout'],
            'admin/{screen}'      => ['AdminController', 'screen'],
        ];
    }
}

return ViewRouteProvider::routes();
