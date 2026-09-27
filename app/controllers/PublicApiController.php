<?php

/**
 * Public API Controller for BCE Export.
 * Serves paginated products, categories, blog posts, and blog categories
 * without session/auth requirements.
 */
class PublicApiController extends ApiController
{
    /**
     * GET /api/products
     */
    public function products(): never
    {
        $page = max(1, (int) (ApiRequest::query('page') ?? 1));
        $perPage = (int) (ApiRequest::query('perPage') ?? ApiRequest::query('pageSize') ?? 12);
        $perPage = max(1, min(24, $perPage));

        $cat = trim((string) (ApiRequest::query('categoryId') ?? ApiRequest::query('category_id') ?? ''));
        $q = trim((string) (ApiRequest::query('q') ?? ''));
        $featured = ApiRequest::query('featured');

        $where = ["status = 'published'", "deleted_at IS NULL"];
        $params = [];

        if ($cat !== '' && $cat !== 'all') {
            // Check if $cat is id or slug or match category
            $where[] = "(category_id = ? OR category_id = (SELECT id FROM categories WHERE slug = ?) OR category_id = (SELECT slug FROM categories WHERE id = ?))";
            $params[] = $cat;
            $params[] = $cat;
            $params[] = $cat;
        }

        if ($q !== '') {
            $where[] = "(name LIKE ? OR short_description LIKE ? OR description LIKE ?)";
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }

        if ($featured !== null && $featured !== '') {
            $where[] = "featured = ?";
            $params[] = filter_var($featured, FILTER_VALIDATE_BOOL) ? 1 : 0;
        }

        $sqlWhere = implode(' AND ', $where);
        $total = (int) db_scalar("SELECT COUNT(*) FROM products WHERE {$sqlWhere}", $params);

        $totalPages = max(1, (int) ceil($total / $perPage));
        $offset = ($page - 1) * $perPage;

        $rows = db_fetch_all(
            "SELECT * FROM products WHERE {$sqlWhere} ORDER BY sort_order ASC, id ASC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        // Normalize image URLs
        foreach ($rows as &$r) {
            if (!empty($r['image'])) {
                $img = ltrim($r['image'], '/');
                if (!str_starts_with($img, 'img/') && !str_starts_with($img, 'assets/')) {
                    $img = 'img/' . $img;
                }
                $r['imageUrl'] = base_url($img);
            } else {
                $r['imageUrl'] = base_url('img/placeholder.png');
            }
        }
        unset($r);

        Api::ok($rows, [
            'page' => $page,
            'perPage' => $perPage,
            'pageSize' => $perPage,
            'total' => $total,
            'totalPages' => $totalPages,
        ]);
    }

    /**
     * GET /api/categories
     */
    public function categories(): never
    {
        $rows = db_fetch_all("SELECT * FROM categories WHERE status = 'published' AND deleted_at IS NULL ORDER BY sort_order ASC");
        Api::ok($rows);
    }

    /**
     * GET /api/blog-posts
     */
    public function blogPosts(): never
    {
        $page = max(1, (int) (ApiRequest::query('page') ?? 1));
        $perPage = (int) (ApiRequest::query('perPage') ?? ApiRequest::query('pageSize') ?? 9);
        $perPage = max(1, min(24, $perPage));

        $cat = trim((string) (ApiRequest::query('category') ?? ApiRequest::query('categoryId') ?? ''));
        $q = trim((string) (ApiRequest::query('q') ?? ''));
        $featured = ApiRequest::query('featured');

        $where = ["status = 'published'", "deleted_at IS NULL"];
        $params = [];

        if ($cat !== '' && $cat !== 'all') {
            $where[] = "(category_id = ? OR category_id = (SELECT slug FROM blog_categories WHERE slug = ?) OR category_id = (SELECT CAST(id AS TEXT) FROM blog_categories WHERE slug = ?))";
            $params[] = $cat;
            $params[] = $cat;
            $params[] = $cat;
        }

        if ($q !== '') {
            $where[] = "(title LIKE ? OR excerpt LIKE ? OR body LIKE ?)";
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }

        if ($featured !== null && $featured !== '') {
            $where[] = "featured = ?";
            $params[] = filter_var($featured, FILTER_VALIDATE_BOOL) ? 1 : 0;
        }

        $sqlWhere = implode(' AND ', $where);
        $total = (int) db_scalar("SELECT COUNT(*) FROM blog_posts WHERE {$sqlWhere}", $params);

        $totalPages = max(1, (int) ceil($total / $perPage));
        $offset = ($page - 1) * $perPage;

        $rows = db_fetch_all(
            "SELECT * FROM blog_posts WHERE {$sqlWhere} ORDER BY published_at DESC, id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        // Fetch category mapping
        $catMap = [];
        $cats = db_fetch_all("SELECT * FROM blog_categories WHERE deleted_at IS NULL");
        foreach ($cats as $c) {
            $catMap[$c['slug']] = $c['name'];
            $catMap[(string) $c['id']] = $c['name'];
        }

        foreach ($rows as &$r) {
            $r['categoryName'] = $catMap[$r['category_id'] ?? ''] ?? 'General';
            if (!empty($r['cover_image'])) {
                $img = ltrim($r['cover_image'], '/');
                if (!str_starts_with($img, 'img/') && !str_starts_with($img, 'assets/')) {
                    $img = 'img/' . $img;
                }
                $r['coverUrl'] = base_url($img);
            } else {
                $r['coverUrl'] = base_url('img/about01.webp');
            }
            $r['url'] = base_url('blog/' . $r['slug']);
        }
        unset($r);

        Api::ok($rows, [
            'page' => $page,
            'perPage' => $perPage,
            'pageSize' => $perPage,
            'total' => $total,
            'totalPages' => $totalPages,
        ]);
    }

    /**
     * GET /api/blog-categories
     */
    public function blogCategories(): never
    {
        $rows = db_fetch_all("SELECT * FROM blog_categories WHERE status = 'published' AND deleted_at IS NULL ORDER BY sort_order ASC");
        Api::ok($rows);
    }
}
