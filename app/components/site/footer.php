<?php
/**
 * Site footer component.
 *
 * Variables: $phone (string), $email (string), $address (string)
 */
$phone = $phone ?? '+91 8900379037';
$email = $email ?? 'admin@bceexport.com';
$address = $address ?? 'Arabindanagar (N) Bankura 722101 West Bengal, INDIA';
?>
    <!-- Footer Start -->
    <div class="container-fluid bg-dark text-light footer pt-5 mt-5 wow fadeInUp" data-wow-delay="0.1s">
        <div class="container py-5">
            <div class="row g-5">
                <div class="col-lg-3 col-md-6">
                    <h4 class="text-light mb-4">Address</h4>
                    <p class="mb-2"><i class="fa fa-map-marker-alt me-3"></i><?= e($address) ?></p>
                    <p class="mb-2"><i class="fa fa-phone-alt me-3"></i><span data-bce-phone><?= e($phone) ?></span></p>
                    <p class="mb-2"><i class="fa fa-envelope me-3"></i><span data-bce-email><?= e($email) ?></span></p>
                    <div class="d-flex pt-2">
                        <a class="btn btn-outline-light btn-social" href="https://www.facebook.com/share/19tPMJFtDS/"><i class="fab fa-facebook-f"></i></a>
                        <a class="btn btn-outline-light btn-social" href="https://www.instagram.com/bce_export/"><i class="fab fa-instagram"></i></a>
                        <a class="btn btn-outline-light btn-social" href="https://t.me/Bce_Export"><i class="fab fa-telegram"></i></a>
                        <a class="btn btn-outline-light btn-social" href="https://wa.me/+918900379037"><i class="fab fa-whatsapp"></i></a>
                        <a class="btn btn-outline-light btn-social" href="https://youtube.com/@bcasudipta"><i class="fab fa-youtube"></i></a>
                        <a class="btn btn-outline-light btn-social" href="https://www.linkedin.com/in/sudipta-ghosh-9a3a502b5"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <h4 class="text-light mb-4">Services</h4>
                    <a class="btn btn-link" href="<?= base_url('services') ?>">Air Freight</a>
                    <a class="btn btn-link" href="<?= base_url('services') ?>">Sea Freight</a>
                    <a class="btn btn-link" href="<?= base_url('services') ?>">Road Freight</a>
                    <a class="btn btn-link" href="<?= base_url('services') ?>">Train Freight</a>
                    <a class="btn btn-link" href="<?= base_url('services') ?>">Custom Clearance</a>
                    <a class="btn btn-link" href="<?= base_url('services') ?>">Warehousing</a>
                </div>
                <div class="col-lg-3 col-md-6">
                    <h4 class="text-light mb-4">Quick Links</h4>
                    <a class="btn btn-link" href="<?= base_url('about') ?>">About Us</a>
                    <a class="btn btn-link" href="<?= base_url('contact') ?>">Contact Us</a>
                    <a class="btn btn-link" href="<?= base_url('products/leather') ?>">Leather</a>
                    <a class="btn btn-link" href="<?= base_url('products/wooden-handicraft') ?>">Wooden Handicraft</a>
                    <a class="btn btn-link" href="<?= base_url('products/dhokra') ?>">Dhokra</a>
                    <a class="btn btn-link" href="<?= base_url('products/terracotta') ?>">Terracotta</a>
                </div>
                <div class="col-lg-3 col-md-6">
                    <h4 class="text-light mb-4">BCE Export</h4>
                    <p>BCE Export is a trusted Indian export company supplying authentic handicrafts worldwide. We specialize in wooden, bamboo, glass, and handmade décor products, ensuring quality craftsmanship, reliable sourcing, and timely delivery.</p>
                </div>
            </div>
        </div>
        <div class="container">
            <div class="copyright">
                <div class="row">
                    <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                        &copy; <a class="border-bottom" href="<?= base_url('/') ?>">BCE Export</a>, All Rights Reserved.
                    </div>
                    <div class="col-md-6 text-center text-md-end">
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Footer End -->
