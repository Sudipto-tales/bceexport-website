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

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $pagedData = get_products_by_category_paged($category, $page, 12);

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
            'products' => $pagedData['products'],
            'total' => $pagedData['total'],
            'currentPage' => $pagedData['page'],
            'totalPages' => $pagedData['totalPages'],
        ], 'products');
    }

    /** GET /blog */
    public function blogList(): void
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $cat = trim((string) ($_GET['category'] ?? ''));
        $q = trim((string) ($_GET['q'] ?? ''));

        $data = get_blog_posts($page, 9, $cat, $q);
        $categories = get_blog_categories();

        $this->page('blog', [
            'title' => 'Blog & Sourcing Insights — BCE Export',
            'description' => 'Latest articles, sourcing guides, material insights, and export logistics news from BCE Export.',
            'pageTitle' => 'Blog & Insights',
            'headerClass' => 'about',
            'breadcrumbs' => ['Blog' => ''],
            'ogImage' => base_url('img/about01.webp'),
            'posts' => $data['posts'],
            'total' => $data['total'],
            'currentPage' => $data['page'],
            'totalPages' => $data['totalPages'],
            'blogCategories' => $categories,
            'currentCategory' => $cat,
            'searchQuery' => $q,
        ], 'blog');
    }

    /** GET /blog/{slug} */
    public function blogDetail(): void
    {
        $slug = (string) $this->param('slug', '');
        $post = get_blog_post_by_slug($slug);

        if (!$post) {
            $this->notFoundPage();
            return;
        }

        $related = get_related_blog_posts($post, 4);

        $coverImage = !empty($post['cover_image'])
            ? site_url($post['cover_image'])
            : base_url('img/about01.webp');

        $this->page('blog-post', [
            'title' => $post['title'] . ' — BCE Export Blog',
            'description' => !empty($post['excerpt']) ? strip_tags($post['excerpt']) : substr(strip_tags($post['body'] ?? ''), 0, 160),
            'pageTitle' => $post['title'],
            'headerClass' => 'about',
            'breadcrumbs' => [
                'Blog' => base_url('blog'),
                $post['title'] => '',
            ],
            'ogType' => 'article',
            'ogImage' => $coverImage,
            'post' => $post,
            'relatedPosts' => $related,
        ], 'blog');
    }

    /** GET /sitemap.xml */
    public function sitemap(): void
    {
        header('Content-Type: application/xml; charset=utf-8');

        $baseUrl = rtrim(base_url('/'), '/');

        $staticPages = [
            '/' => '1.0',
            '/about' => '0.8',
            '/services' => '0.8',
            '/contact' => '0.9',
            '/quote' => '0.8',
            '/team' => '0.7',
            '/testimonials' => '0.7',
            '/blog' => '0.8',
        ];

        $categories = get_categories();
        $blogPosts = get_blog_posts(1, 200)['posts'] ?? [];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($staticPages as $path => $priority) {
            $xml .= '  <url><loc>' . htmlspecialchars($baseUrl . $path, ENT_XML1, 'UTF-8') . '</loc><priority>' . $priority . '</priority></url>' . "\n";
        }

        foreach ($categories as $cat) {
            $slug = $cat['slug'] ?? '';
            if ($slug !== '') {
                $xml .= '  <url><loc>' . htmlspecialchars($baseUrl . '/products/' . $slug, ENT_XML1, 'UTF-8') . '</loc><priority>0.9</priority></url>' . "\n";
            }
        }

        foreach ($blogPosts as $post) {
            $slug = $post['slug'] ?? '';
            if ($slug !== '') {
                $xml .= '  <url><loc>' . htmlspecialchars($baseUrl . '/blog/' . $slug, ENT_XML1, 'UTF-8') . '</loc><priority>0.8</priority></url>' . "\n";
            }
        }

        $xml .= '</urlset>';

        echo $xml;
        exit;
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
