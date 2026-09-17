<?php
// 404 page template
?>
<div class="container-xxl py-5 wow fadeInUp" data-wow-delay="0.1s">
    <div class="container text-center py-5">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <i class="bi bi-exclamation-triangle display-1 text-primary"></i>
                <h1 class="display-1">404</h1>
                <h1 class="mb-4">Page Not Found</h1>
                <p class="mb-4">Sorry, the page you're looking for doesn't exist.</p>
                <a class="btn btn-primary rounded-pill py-3 px-5 me-2" href="<?= base_url('/') ?>">Go Back Home</a>
                <a class="btn btn-secondary rounded-pill py-3 px-5" href="<?= base_url('contact') ?>">Contact Us</a>
            </div>
        </div>
    </div>
</div>
