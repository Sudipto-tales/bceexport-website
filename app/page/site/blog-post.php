<?php
/**
 * Blog Post Detail Template — BCE Export
 */
$coverImg = !empty($post['cover_image']) ? ltrim($post['cover_image'], '/') : 'img/about01.webp';
if (!str_starts_with($coverImg, 'img/') && !str_starts_with($coverImg, 'assets/')) {
    $coverImg = 'img/' . $coverImg;
}
$whatsappNumber = isset($phone) ? preg_replace('/[^0-9]/', '', $phone) : '918900379037';
$postUrl = base_url('blog/' . $post['slug']);
$pubDate = date('c', strtotime($post['published_at'] ?? $post['created_at']));
?>
<!-- BlogPosting Schema JSON-LD -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "BlogPosting",
  "headline": <?= json_encode($post['title']) ?>,
  "description": <?= json_encode(!empty($post['excerpt']) ? strip_tags($post['excerpt']) : substr(strip_tags($post['body'] ?? ''), 0, 160)) ?>,
  "image": [<?= json_encode(base_url($coverImg)) ?>],
  "datePublished": <?= json_encode($pubDate) ?>,
  "dateModified": <?= json_encode($pubDate) ?>,
  "mainEntityOfPage": {
    "@type": "WebPage",
    "@id": <?= json_encode($postUrl) ?>
  },
  "author": {
    "@type": "Organization",
    "name": <?= json_encode($post['author_name'] ?? 'BCE Export') ?>
  },
  "publisher": {
    "@type": "Organization",
    "name": "BCE Export",
    "logo": {
      "@type": "ImageObject",
      "url": "https://www.bceexport.com/img/logo.webp"
    }
  }
}
</script>
<!-- Blog Post Content Start -->
<div class="container-xxl py-5">
    <div class="container py-5">
        <div class="row g-5">
            <!-- Article Main Column (col-lg-8) -->
            <div class="col-lg-8 wow fadeInUp" data-wow-delay="0.1s">
                <article class="blog-article bg-white p-4 p-md-5 rounded shadow-sm">
                    <!-- Category Badge & Date -->
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                        <span class="badge bg-primary px-3 py-2 fs-6 fw-normal">
                            <?= e($post['category_name']) ?>
                        </span>
                        <div class="text-muted small">
                            <i class="fa fa-calendar-alt text-secondary me-1"></i><?= date('F d, Y', strtotime($post['published_at'] ?? $post['created_at'])) ?>
                            <span class="mx-2">•</span>
                            <i class="fa fa-user text-secondary me-1"></i><?= e($post['author_name'] ?? 'BCE Export') ?>
                        </div>
                    </div>

                    <!-- Title -->
                    <h1 class="mb-4 text-dark" style="line-height: 1.3; font-weight: 700;"><?= e($post['title']) ?></h1>

                    <!-- Cover Image -->
                    <div class="position-relative overflow-hidden rounded mb-4" style="max-height: 420px; background: #f8f9fa;">
                        <img src="<?= base_url($coverImg) ?>" alt="<?= e($post['title']) ?>" class="w-100 h-100 img-fluid" style="object-fit: cover;" loading="lazy" decoding="async">
                    </div>

                    <!-- Article Excerpt -->
                    <?php if (!empty($post['excerpt'])): ?>
                        <div class="lead bg-light p-4 rounded border-start border-4 border-primary mb-4 fst-italic">
                            <?= e($post['excerpt']) ?>
                        </div>
                    <?php endif; ?>

                    <!-- Body Content -->
                    <div class="article-body text-dark lh-lg" style="font-size: 1.05rem;">
                        <?= $post['body'] ?>
                    </div>

                    <!-- Share & Social Buttons -->
                    <div class="border-top mt-5 pt-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div class="fw-bold text-dark">Share this article:</div>
                        <div class="d-flex gap-2">
                            <?php 
                            $shareUrl = urlencode(base_url('blog/' . $post['slug']));
                            $shareText = urlencode($post['title']);
                            ?>
                            <a href="https://wa.me/?text=<?= $shareText ?>%20<?= $shareUrl ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success">
                                <i class="fab fa-whatsapp me-1"></i> WhatsApp
                            </a>
                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $shareUrl ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                                <i class="fab fa-facebook-f me-1"></i> Facebook
                            </a>
                            <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= $shareUrl ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-info">
                                <i class="fab fa-linkedin-in me-1"></i> LinkedIn
                            </a>
                        </div>
                    </div>
                </article>
            </div>

            <!-- Sidebar Column (col-lg-4) -->
            <div class="col-lg-4 wow fadeInUp" data-wow-delay="0.3s">
                <div class="sticky-top" style="top: 100px; z-index: 10;">
                    
                    <!-- Quick Quote CTA Card -->
                    <div class="card bg-primary text-white border-0 shadow-sm p-4 mb-4 rounded">
                        <h4 class="text-white mb-3"><i class="fa fa-paper-plane me-2"></i>Sourcing Indian Goods?</h4>
                        <p class="small text-white-50 mb-4">Partner with BCE Export for direct factory pricing on Dhokra metal craft, terracotta, leather products, and jute packaging.</p>
                        <a href="<?= base_url('quote') ?>" class="btn btn-light text-primary fw-bold py-2 px-4 shadow-sm mb-2">Request Free Quote</a>
                        <a href="https://wa.me/<?= $whatsappNumber ?>?text=<?= urlencode('Hi BCE Export, I read your article "' . $post['title'] . '" and would like to inquire about export sourcing.') ?>" target="_blank" rel="noopener" class="btn btn-outline-light py-2 px-4">
                            <i class="fab fa-whatsapp me-1"></i> Chat on WhatsApp
                        </a>
                    </div>

                    <!-- Related Articles -->
                    <div class="card border-0 shadow-sm p-4 rounded bg-white">
                        <h5 class="mb-4 pb-2 border-bottom"><i class="fa fa-newspaper text-primary me-2"></i>Related Articles</h5>
                        <?php if (empty($relatedPosts)): ?>
                            <p class="text-muted small">No related articles available.</p>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-3">
                                <?php foreach ($relatedPosts as $rPost): ?>
                                    <?php 
                                    $rImg = !empty($rPost['cover_image']) ? ltrim($rPost['cover_image'], '/') : 'img/about01.webp';
                                    if (!str_starts_with($rImg, 'img/') && !str_starts_with($rImg, 'assets/')) {
                                        $rImg = 'img/' . $rImg;
                                    }
                                    ?>
                                    <div class="d-flex gap-3 align-items-center">
                                        <div class="flex-shrink-0 overflow-hidden rounded" style="width: 70px; height: 70px; background: #f8f9fa;">
                                            <img src="<?= base_url($rImg) ?>" alt="<?= e($rPost['title']) ?>" class="w-100 h-100" style="object-fit: cover;" loading="lazy" decoding="async">
                                        </div>
                                        <div class="grow">
                                            <span class="badge bg-light text-dark small mb-1"><?= e($rPost['category_name']) ?></span>
                                            <h6 class="mb-0 small" style="line-height: 1.3;">
                                                <a href="<?= base_url('blog/' . e($rPost['slug'])) ?>" class="text-dark text-decoration-none hover-primary">
                                                    <?= e($rPost['title']) ?>
                                                </a>
                                            </h6>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>
<!-- Blog Post Content End -->
