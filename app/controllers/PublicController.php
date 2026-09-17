<?php

/**
 * Public-facing pages for BCE Export.
 *
 * Each action renders the shared layout chrome (head, navbar, footer, scripts)
 * around a page-specific body template from app/page/site/.
 */
class PublicController extends BaseController
{
    /**
     * Render a public page with the shared layout.
     *
     * @param string $body   Template name under app/page/site/ (without .php)
     * @param array  $data   Variables passed to the body template
     * @param string $active Which nav item is highlighted
     */
    private function page(string $body, array $data = [], string $active = ''): void
    {
        $phone = site_primary_phone();

        $layoutData = [
            'active' => $active,
            'phone' => $phone['number'],
            'email' => site_primary_email(),
            'address' => implode(', ', site_address_lines()),
        ];

        App::render('site/head', array_merge($layoutData, $data));
        App::render('site/navbar', $layoutData);

        /* Subpages get a page-header banner with breadcrumbs.
           The homepage has no pageTitle and goes straight to the carousel. */
        if (!empty($data['pageTitle'])) {
            App::render('site/page-header', $data);
        }

        render_view('/app/page/site/' . $body . '.php', array_merge($layoutData, $data));

        App::render('site/footer', $layoutData);
        App::render('site/scripts', $layoutData);
    }

    /* ---------------------------------------------------------
       Pages
       --------------------------------------------------------- */

    /** GET / */
    public function home(): void
    {
        $this->page('home', [
            'title' => 'BCE Export — Global Export & Import Solutions',
            'categories' => get_categories(),
            'testimonials' => get_testimonials(),
            'certificates' => get_certificates(),
            'team' => get_team_members(),
        ], 'home');
    }

    /** GET /about */
    public function about(): void
    {
        $this->page('about', [
            'title' => 'About Us',
            'pageTitle' => 'About Us',
            'breadcrumbs' => ['About' => base_url('about')],
            'team' => get_team_members(),
        ], 'about');
    }

    /** GET /services */
    public function services(): void
    {
        $this->page('services', [
            'title' => 'Our Services',
            'pageTitle' => 'Services',
            'breadcrumbs' => ['Services' => base_url('services')],
            'testimonials' => get_testimonials(),
        ], 'services');
    }

    /** GET /contact */
    public function contact(): void
    {
        $this->page('contact', [
            'title' => 'Contact Us',
            'pageTitle' => 'Contact Us',
            'breadcrumbs' => ['Contact' => base_url('contact')],
        ], 'contact');
    }

    /** GET /quote */
    public function quote(): void
    {
        $this->page('quote', [
            'title' => 'Request a Quote',
            'pageTitle' => 'Free Quote',
            'breadcrumbs' => ['Quote' => base_url('quote')],
        ], 'contact');
    }

    /** GET /team */
    public function team(): void
    {
        $this->page('team', [
            'title' => 'Our Team',
            'pageTitle' => 'Our Team',
            'breadcrumbs' => ['Team' => base_url('team')],
            'team' => get_team_members(),
        ], 'about');
    }

    /** GET /testimonials */
    public function testimonials(): void
    {
        $this->page('testimonials', [
            'title' => 'Client Testimonials',
            'pageTitle' => 'Testimonials',
            'breadcrumbs' => ['Testimonials' => base_url('testimonials')],
            'testimonials' => get_testimonials(),
        ], 'about');
    }

    /** GET /products/{slug} */
    public function category(): void
    {
        $slug = (string) $this->param('slug', '');

        /* Map old HTML filenames to category slugs for backward compat */
        $aliases = [
            'wooden_handicraft' => 'wooden-handicraft',
            'fruit-vegetable'   => 'fruit-vegetable',
        ];
        $slug = $aliases[$slug] ?? $slug;

        $category = get_category_by_slug($slug);

        if (!$category) {
            $this->notFoundPage();
            return;
        }

        $products = get_products_by_category((int) $category['id']);

        $this->page('category', [
            'title' => $category['name'],
            'pageTitle' => $category['name'] . ' Products',
            'breadcrumbs' => [
                'Products' => '#',
                $category['name'] => base_url('products/' . $slug),
            ],
            'category' => $category,
            'products' => $products,
        ], 'products');
    }

    /** 404 error page */
    public function notFoundPage(): void
    {
        http_response_code(404);
        $this->page('404', [
            'title' => 'Page Not Found',
            'pageTitle' => '404 — Page Not Found',
            'breadcrumbs' => ['404' => '#'],
        ]);
    }
}
