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
        if (($data['showPageHeader'] ?? true) !== false && !empty($data['pageTitle'])) {
            App::render('site/page-header', [
                'pageTitle'   => $data['pageTitle']   ?? ($data['title'] ?? 'Page'),
                'breadcrumbs' => $data['breadcrumbs'] ?? [],
                'headerClass' => $data['headerClass'] ?? '',
            ]);
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
            'showPageHeader' => false,
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
            'description' => 'Learn about BCE Export — wholesale exporter of authentic Indian handicrafts, Dhokra, terracotta, leather and jute from Bankura, West Bengal.',
            'pageTitle' => 'About Us',
            'headerClass' => 'about',
            'breadcrumbs' => ['About' => ''],
            'ogImage' => base_url('img/about01.webp'),
            'team' => get_team_members(),
        ], 'about');
    }

    /** GET /services */
    public function services(): void
    {
        $this->page('services', [
            'title' => 'Our Services',
            'description' => 'Worldwide air & sea freight, customs clearance, secure packaging, and supply chain management by BCE Export.',
            'pageTitle' => 'Services',
            'headerClass' => 'services',
            'breadcrumbs' => ['Services' => ''],
            'ogImage' => base_url('img/services01.webp'),
            'testimonials' => get_testimonials(),
        ], 'services');
    }

    /** GET /contact */
    public function contact(): void
    {
        $this->page('contact', [
            'title' => 'Contact Us',
            'description' => 'Get in touch with BCE Export for wholesale handicraft inquiries, product quotations, sample orders, and global export shipping details.',
            'pageTitle' => 'Contact Us',
            'headerClass' => 'Contact',
            'breadcrumbs' => ['Contact' => ''],
            'ogImage' => base_url('img/Contact.webp'),
        ], 'contact');
    }

    /** GET /quote */
    public function quote(): void
    {
        $this->page('quote', [
            'title' => 'Request a Quote',
            'description' => 'Request a free price quote for bulk and wholesale orders of Indian handicrafts, Dhokra, terracotta, leather, and jute from BCE Export.',
            'pageTitle' => 'Free Quote',
            'headerClass' => '',
            'breadcrumbs' => ['Quote' => ''],
            'ogImage' => base_url('img/about01.webp'),
        ], 'contact');
    }

    /** GET /team */
    public function team(): void
    {
        $this->page('team', [
            'title' => 'Our Team',
            'description' => 'Meet the experienced team and leadership driving BCE Export — delivering quality Indian handicrafts and goods worldwide.',
            'pageTitle' => 'Our Team',
            'headerClass' => '',
            'breadcrumbs' => ['Team' => ''],
            'ogImage' => base_url('img/about01.webp'),
            'team' => get_team_members(),
        ], 'about');
    }

    /** GET /testimonials */
    public function testimonials(): void
    {
        $this->page('testimonials', [
            'title' => 'Client Testimonials',
            'description' => 'Read testimonials and reviews from global buyers and importers who trust BCE Export for authentic Indian handicrafts.',
            'pageTitle' => 'Testimonials',
            'headerClass' => '',
            'breadcrumbs' => ['Testimonials' => ''],
            'ogImage' => base_url('img/about01.webp'),
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

        $products = get_products_by_category($category);

        $classMap = [
            'leather'           => 'leather',
            'handicraft'        => 'Handicraft',
            'wooden-handicraft' => 'Handicraft',
            'wooden_handicraft' => 'Handicraft',
            'furniture'         => 'Furniture',
            'jute'              => 'Jute',
            'dhokra'            => 'Dhokra',
            'terracotta'        => 'Terracotta',
            'fruit'             => 'Fruit',
            'fruit-vegetable'   => 'Fruit',
            'fruit_vegetable'   => 'Fruit',
        ];
        $headerClass = $classMap[strtolower($slug)] ?? '';

        $catImage = !empty($category['image'])
            ? site_url($category['image'])
            : base_url('img/' . ($headerClass ? $headerClass . '01.webp' : 'about.webp'));

        $this->page('category', [
            'title' => $category['name'],
            'description' => !empty($category['description'])
                ? strip_tags($category['description'])
                : 'Wholesale exporter of authentic Indian ' . $category['name'] . ' products from Bankura, West Bengal.',
            'pageTitle' => $category['name'] . ' Products',
            'headerClass' => $headerClass,
            'breadcrumbs' => [
                'Products' => base_url('/#categories'),
                $category['name'] => '',
            ],
            'ogType' => 'product',
            'ogImage' => $catImage,
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
            'headerClass' => '',
            'breadcrumbs' => ['404' => ''],
        ]);
    }
}
