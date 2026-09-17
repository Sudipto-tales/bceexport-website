<?php
// Category page template
?>
<!-- Feature Start -->
<div class="container-fluid overflow-hidden py-5 px-lg-0">
    <div class="container feature py-5 px-lg-0">
        <div class="row g-5 mx-lg-0">

            <div class="col-lg-6 pe-lg-0 wow fadeInRight" data-wow-delay="0.1s" style="min-height: 400px;">
                <div class="position-relative h-100">
                    <?php if (!empty($category['image'])): ?>
                    <img class="position-absolute img-fluid w-100 h-100" src="<?= base_url(ltrim($category['image'], '/')) ?>" style="object-fit: cover;" alt="<?= e($category['name']) ?>">
                    <?php else: ?>
                    <img class="position-absolute img-fluid w-100 h-100" src="<?= base_url('img/placeholder.png') ?>" style="object-fit: cover;" alt="<?= e($category['name']) ?>">
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

<!-- Team Start -->
<div class="container-xxl py-5">
    <div class="container py-5">
        <div class="text-center wow fadeInUp" data-wow-delay="0.1s">
             <h1 class="mb-5"><?= e($category['name']) ?> Products</h1>
        </div>
        <div class="row g-4">
            <?php if (empty($products)): ?>
                <div class="col-12 text-center wow fadeInUp" data-wow-delay="0.3s">
                    <p>More products are being added to this category. Please contact us directly for inquiries.</p>
                    <a href="<?= base_url('contact') ?>" class="btn btn-primary">Contact Us</a>
                </div>
            <?php else: ?>
                <?php $delay = 0.3; ?>
                <?php foreach ($products as $product): ?>
                <div class="col-lg-3 col-md-6 wow fadeInUp" data-wow-delay="<?= $delay ?>s">
                    <div class="team-item p-4">
                        <div class="overflow-hidden mb-4" style="height:240px">
                            <?php if (!empty($product['image'])): ?>
                                <?php 
                                $pImg = ltrim($product['image'], '/');
                                if (!str_starts_with($pImg, 'img/') && !str_starts_with($pImg, 'assets/')) {
                                    $pImg = 'img/' . $pImg;
                                }
                                ?>
                                <img class="img-fluid w-100 h-100" style="object-fit:cover" src="<?= base_url($pImg) ?>" alt="<?= e($product['name']) ?>">
                            <?php else: ?>
                                <img class="img-fluid w-100 h-100" style="object-fit:cover" src="<?= base_url('img/placeholder.png') ?>" alt="<?= e($product['name']) ?>">
                            <?php endif; ?>
                        </div>
                        <h5 class="mb-0"><?= e($product['name']) ?></h5>
                        <p><?= e($category['name']) ?> Product</p>
                        <div class="btn-slide mt-1">
                            <i class="fa fa-share"></i>
                            <span>
                                <?php $wa_text = urlencode('Hi, I am interested in ' . $product['name']); ?>
                                <a href="https://wa.me/<?= isset($settings['whatsapp']) ? e($settings['whatsapp']) : '+918900379037' ?>?text=<?= $wa_text ?>"><i class="fab fa-whatsapp"></i></a>
                                <a href="<?= isset($settings['facebook']) ? e($settings['facebook']) : 'https://www.facebook.com/Bceexport' ?>"><i class="fab fa-facebook-f"></i></a>
                                <a href="<?= isset($settings['instagram']) ? e($settings['instagram']) : 'https://www.instagram.com/bceexport/' ?>"><i class="fab fa-instagram"></i></a>
                            </span>
                        </div>
                    </div>
                </div>
                <?php 
                $delay += 0.2; 
                if ($delay > 0.9) $delay = 0.3;
                ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<!-- Team End -->
