<?php
// Category page template with API-backed pagination and lazy loading
$whatsappNumber = isset($phone) ? preg_replace('/[^0-9]/', '', $phone) : '918900379037';
$itemList = [];
$pos = 1;
foreach ($products as $p) {
    $itemList[] = [
        '@type' => 'ListItem',
        'position' => $pos++,
        'name' => $p['name'],
        'url' => base_url('products/' . $category['slug'])
    ];
}
?>
<!-- ItemList Schema JSON-LD -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "ItemList",
  "name": <?= json_encode($category['name'] . ' Products') ?>,
  "description": <?= json_encode(!empty($category['description']) ? strip_tags($category['description']) : 'Exporter of ' . $category['name'] . ' from India') ?>,
  "numberOfItems": <?= count($products) ?>,
  "itemListElement": <?= json_encode($itemList, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
}
</script>
<!-- Feature Start -->
<div class="container-fluid overflow-hidden py-5 px-lg-0">
    <div class="container feature py-5 px-lg-0">
        <div class="row g-5 mx-lg-0">

            <div class="col-lg-6 pe-lg-0 wow fadeInRight" data-wow-delay="0.1s" style="min-height: 400px;">
                <div class="position-relative h-100">
                    <?php if (!empty($category['image'])): ?>
                    <img class="position-absolute img-fluid w-100 h-100" src="<?= base_url(ltrim($category['image'], '/')) ?>" style="object-fit: cover;" alt="<?= e($category['name']) ?>" loading="lazy" decoding="async">
                    <?php else: ?>
                    <img class="position-absolute img-fluid w-100 h-100" src="<?= base_url('img/placeholder.png') ?>" style="object-fit: cover;" alt="<?= e($category['name']) ?>" loading="lazy" decoding="async">
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-6 feature-text wow fadeInUp" data-wow-delay="0.1s">
                <h6 class="text-secondary text-uppercase mb-3"><?= e($category['name']) ?></h6>
                <h1 class="mb-5">We Are a Trusted Export Company for Premium <?= e($category['name']) ?> Worldwide.</h1>
                <div class="d-flex mb-5 wow fadeInUp" data-wow-delay="0.3s">
                    <div class="ms-4">
                        <p class="mb-0"><?= nl2br(e($category['description'])) ?></p>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
<!-- Feature End -->

<!-- Products Start -->
<div class="container-xxl py-5">
    <div class="container py-5">
        <div class="text-center wow fadeInUp" data-wow-delay="0.1s">
             <h1 class="mb-2"><?= e($category['name']) ?> Products</h1>
             <p class="text-muted mb-5">Showing <span id="showingCount"><?= count($products) ?></span> of <?= $total ?> products</p>
        </div>

        <div class="row g-4" id="productsGrid" data-category="<?= e($category['slug']) ?>" data-wa="<?= e($whatsappNumber) ?>">
            <?php if (empty($products)): ?>
                <div class="col-12 text-center wow fadeInUp" data-wow-delay="0.3s">
                    <p>More products are being added to this category. Please contact us directly for inquiries.</p>
                    <a href="<?= base_url('contact') ?>" class="btn btn-primary">Contact Us</a>
                </div>
            <?php else: ?>
                <?php $delay = 0.1; ?>
                <?php foreach ($products as $product): ?>
                <div class="col-lg-3 col-md-6 product-item-col wow fadeInUp" data-wow-delay="<?= $delay ?>s">
                    <div class="team-item p-4 h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="overflow-hidden mb-4 position-relative" style="height:240px; background:#f8f9fa; border-radius: 4px;">
                                <?php 
                                $pImg = !empty($product['image']) ? ltrim($product['image'], '/') : 'img/placeholder.png';
                                if (!str_starts_with($pImg, 'img/') && !str_starts_with($pImg, 'assets/')) {
                                    $pImg = 'img/' . $pImg;
                                }
                                ?>
                                <img class="img-fluid w-100 h-100" style="object-fit:cover" src="<?= base_url($pImg) ?>" alt="<?= e($product['name']) ?>" loading="lazy" decoding="async">
                            </div>
                            <h5 class="mb-1 text-truncate" title="<?= e($product['name']) ?>"><?= e($product['name']) ?></h5>
                            <p class="text-muted small mb-3"><?= e($category['name']) ?></p>
                        </div>
                        <div class="btn-slide mt-2">
                            <i class="fa fa-share"></i>
                            <span>
                                <?php $wa_text = urlencode('Hi, I am interested in ' . $product['name'] . ' (' . $category['name'] . ')'); ?>
                                <a href="https://wa.me/<?= $whatsappNumber ?>?text=<?= $wa_text ?>" target="_blank" rel="noopener"><i class="fab fa-whatsapp"></i></a>
                                <a href="https://www.facebook.com/Bceexport" target="_blank" rel="noopener"><i class="fab fa-facebook-f"></i></a>
                                <a href="https://www.instagram.com/bceexport/" target="_blank" rel="noopener"><i class="fab fa-instagram"></i></a>
                            </span>
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

        <?php if ($totalPages > 1): ?>
        <div class="row mt-5">
            <div class="col-12 text-center">
                <button type="button" id="loadMoreBtn" class="btn btn-primary py-3 px-5" 
                        data-category="<?= e($category['slug']) ?>" 
                        data-page="<?= $currentPage ?>" 
                        data-total-pages="<?= $totalPages ?>">
                    <span class="btn-text">Load More Products</span>
                    <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<!-- Products End -->
