<?php
/**
 * Blog Listing Template — BCE Export
 */
?>
<!-- Blog Section Start -->
<div class="container-xxl py-5">
    <div class="container py-5">
        <!-- Section Header -->
        <div class="text-center wow fadeInUp mb-5" data-wow-delay="0.1s">
            <h6 class="text-secondary text-uppercase mb-2">Industry Insights & Sourcing Guides</h6>
            <h1 class="mb-3">Latest Articles & Export News</h1>
            <p class="text-muted mx-auto" style="max-width: 600px;">Explore expert tips, handicraft stories, international trade guides, and material insights from Bankura, West Bengal.</p>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="row g-3 mb-5 align-items-center justify-content-between wow fadeInUp" data-wow-delay="0.2s">
            <div class="col-md-7 col-lg-6">
                <form action="<?= base_url('blog') ?>" method="GET" class="row g-2">
                    <?php if (!empty($currentCategory)): ?>
                        <input type="hidden" name="category" value="<?= e($currentCategory) ?>">
                    <?php endif; ?>
                    <div class="col-8 col-sm-9">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i class="fa fa-search text-muted"></i></span>
                            <input type="search" name="q" class="form-control border-start-0 ps-0" placeholder="Search blog posts..." value="<?= e($searchQuery) ?>">
                        </div>
                    </div>
                    <div class="col-4 col-sm-3">
                        <button type="submit" class="btn btn-primary w-100">Search</button>
                    </div>
                </form>
            </div>

            <div class="col-md-5 col-lg-4 text-md-end">
                <div class="d-flex align-items-center justify-content-md-end gap-2">
                    <span class="text-muted small d-none d-sm-inline">Category:</span>
                    <select class="form-select w-auto" onchange="location = this.value;">
                        <option value="<?= base_url('blog') ?>"<?= empty($currentCategory) ? ' selected' : '' ?>>All Categories</option>
                        <?php foreach ($blogCategories as $bCat): ?>
                            <option value="<?= base_url('blog?category=' . urlencode($bCat['slug'])) ?>"<?= $currentCategory === $bCat['slug'] ? ' selected' : '' ?>>
                                <?= e($bCat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Posts Grid -->
        <div class="row g-4">
            <?php if (empty($posts)): ?>
                <div class="col-12 text-center py-5 wow fadeInUp" data-wow-delay="0.3s">
                    <i class="fa fa-newspaper text-muted mb-3" style="font-size: 48px;"></i>
                    <h4>No Blog Posts Found</h4>
                    <p class="text-muted">No articles matched your search or category filter. Try clearing your search filters.</p>
                    <a href="<?= base_url('blog') ?>" class="btn btn-outline-primary mt-2">View All Posts</a>
                </div>
            <?php else: ?>
                <?php $delay = 0.1; ?>
                <?php foreach ($posts as $post): ?>
                <div class="col-lg-4 col-md-6 wow fadeInUp" data-wow-delay="<?= $delay ?>s">
                    <div class="card h-100 border-0 shadow-sm overflow-hidden d-flex flex-column transition-hover" style="border-radius: 8px;">
                        <div class="position-relative overflow-hidden" style="height: 220px; background: #f8f9fa;">
                            <?php 
                            $coverImg = !empty($post['cover_image']) ? ltrim($post['cover_image'], '/') : 'img/about01.webp';
                            if (!str_starts_with($coverImg, 'img/') && !str_starts_with($coverImg, 'assets/')) {
                                $coverImg = 'img/' . $coverImg;
                            }
                            ?>
                            <img src="<?= base_url($coverImg) ?>" alt="<?= e($post['title']) ?>" class="w-100 h-100" style="object-fit: cover;" loading="lazy" decoding="async">
                            <span class="badge bg-primary position-absolute top-0 end-0 m-3 px-3 py-2 shadow-sm" style="font-weight: 500;">
                                <?= e($post['category_name']) ?>
                            </span>
                        </div>
                        <div class="card-body p-4 d-flex flex-column justify-content-between grow">
                            <div>
                                <div class="d-flex align-items-center text-muted small mb-2 gap-3">
                                    <span><i class="fa fa-calendar-alt text-secondary me-1"></i><?= date('M d, Y', strtotime($post['published_at'] ?? $post['created_at'])) ?></span>
                                    <span><i class="fa fa-user text-secondary me-1"></i><?= e($post['author_name'] ?? 'BCE Export') ?></span>
                                </div>
                                <h5 class="card-title mb-3" style="line-height: 1.4;">
                                    <a href="<?= base_url('blog/' . e($post['slug'])) ?>" class="text-dark text-decoration-none hover-primary">
                                        <?= e($post['title']) ?>
                                    </a>
                                </h5>
                                <p class="card-text text-muted small mb-4" style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                                    <?= e($post['excerpt'] ?? strip_tags($post['body'] ?? '')) ?>
                                </p>
                            </div>
                            <div>
                                <a href="<?= base_url('blog/' . e($post['slug'])) ?>" class="btn btn-link text-primary p-0 text-decoration-none fw-bold">
                                    Read Full Article <i class="fa fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php 
                $delay += 0.1; 
                if ($delay > 0.4) $delay = 0.1;
                ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="row mt-5">
            <div class="col-12">
                <nav aria-label="Blog pagination">
                    <ul class="pagination justify-content-center m-0">
                        <!-- Prev Page -->
                        <li class="page-item<?= $currentPage <= 1 ? ' disabled' : '' ?>">
                            <a class="page-item-link page-link" href="<?= base_url('blog?page=' . ($currentPage - 1) . ($currentCategory ? '&category=' . urlencode($currentCategory) : '') . ($searchQuery ? '&q=' . urlencode($searchQuery) : '')) ?>">
                                <i class="fa fa-chevron-left"></i>
                            </a>
                        </li>

                        <!-- Page Numbers -->
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item<?= $i === $currentPage ? ' active' : '' ?>">
                                <a class="page-link" href="<?= base_url('blog?page=' . $i . ($currentCategory ? '&category=' . urlencode($currentCategory) : '') . ($searchQuery ? '&q=' . urlencode($searchQuery) : '')) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <!-- Next Page -->
                        <li class="page-item<?= $currentPage >= $totalPages ? ' disabled' : '' ?>">
                            <a class="page-link" href="<?= base_url('blog?page=' . ($currentPage + 1) . ($currentCategory ? '&category=' . urlencode($currentCategory) : '') . ($searchQuery ? '&q=' . urlencode($searchQuery) : '')) ?>">
                                <i class="fa fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>
<!-- Blog Section End -->
