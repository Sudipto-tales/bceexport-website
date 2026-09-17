<?php
// Testimonials page template
?>
<!-- Testimonial Start -->
<div class="container-xxl py-5 wow fadeInUp" data-wow-delay="0.1s">
    <div class="container py-5">
        <div class="text-center">
            <h6 class="text-secondary text-uppercase">Testimonial</h6>
            <h1 class="mb-0">Our Clients Say!</h1>
        </div>
        <?php if (empty($testimonials)): ?>
            <div class="text-center mt-5">
                <p>Check back later to see what our clients say about us.</p>
            </div>
        <?php else: ?>
        <div class="owl-carousel testimonial-carousel wow fadeInUp" data-wow-delay="0.1s">
            <?php foreach ($testimonials as $testimonial): ?>
            <div class="testimonial-item p-4 my-5">
                <i class="fa fa-quote-right fa-3x text-light position-absolute top-0 end-0 mt-n3 me-4"></i>
                <div class="d-flex align-items-end mb-4">
                    <?php if (!empty($testimonial['photo'])): ?>
                        <?php 
                        $tPhoto = ltrim($testimonial['photo'], '/');
                        if (!str_starts_with($tPhoto, 'img/') && !str_starts_with($tPhoto, 'assets/')) {
                            $tPhoto = 'img/' . $tPhoto;
                        }
                        ?>
                        <img class="img-fluid flex-shrink-0" src="<?= base_url($tPhoto) ?>" style="width: 80px; height: 80px; object-fit: cover; border-radius: 50%;" alt="<?= e($testimonial['name']) ?>">
                    <?php else: ?>
                        <img class="img-fluid flex-shrink-0" src="<?= base_url('img/placeholder.png') ?>" style="width: 80px; height: 80px; object-fit: cover; border-radius: 50%;" alt="<?= e($testimonial['name']) ?>">
                    <?php endif; ?>
                    <div class="ms-4">
                        <h5 class="mb-1"><?= e($testimonial['name']) ?></h5>
                        <p class="m-0"><?= e($testimonial['role']) ?><?= !empty($testimonial['company']) ? ', ' . e($testimonial['company']) : '' ?></p>
                    </div>
                </div>
                <p class="mb-0"><?= nl2br(e($testimonial['text'])) ?></p>
                <?php if (!empty($testimonial['rating'])): ?>
                <div class="mt-3 text-warning">
                    <?php for ($i = 0; $i < $testimonial['rating']; $i++): ?>
                    <i class="fa fa-star"></i>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<!-- Testimonial End -->
