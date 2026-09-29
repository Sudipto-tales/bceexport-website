<?php
/**
 * Site navbar component — sticky top navigation.
 *
 * Variables: $active (string) — which nav item is highlighted
 *            $phone (string, optional) — phone number to display
 */
$active = $active ?? '';
$phone = $phone ?? '+91 8900379037';
?>
    <!-- Navbar Start -->
    <nav class="navbar navbar-expand-lg bg-white navbar-light shadow border-top border-5 border-primary sticky-top p-0">
        <a href="<?= base_url('/') ?>" class="navbar-brand bg-primary d-flex align-items-center px-4 px-lg-5">
            <h2 class="m-0 text-white">BCE <b>Export</b></h2>
        </a>
        <button type="button" class="navbar-toggler me-4" data-bs-toggle="collapse" data-bs-target="#navbarCollapse" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarCollapse">
            <div class="navbar-nav ms-auto p-4 p-lg-0">
                <a href="<?= base_url('/') ?>" class="nav-item nav-link<?= $active === 'home' ? ' active' : '' ?>">Home</a>
                <a href="<?= base_url('about') ?>" class="nav-item nav-link<?= $active === 'about' ? ' active' : '' ?>">About</a>
                <a href="<?= base_url('services') ?>" class="nav-item nav-link<?= $active === 'services' ? ' active' : '' ?>">Services</a>
                <div class="nav-item dropdown">
                    <a href="#" class="nav-link dropdown-toggle<?= $active === 'products' ? ' active' : '' ?>" data-bs-toggle="dropdown">Products</a>
                    <div class="dropdown-menu fade-up m-0">
                        <a href="<?= base_url('products/leather') ?>" class="dropdown-item">Leather Products</a>
                        <a href="<?= base_url('products/wooden-handicraft') ?>" class="dropdown-item">Wooden Handicraft Products</a>
                        <a href="<?= base_url('products/furniture') ?>" class="dropdown-item">Furniture Products</a>
                        <a href="<?= base_url('products/jute') ?>" class="dropdown-item">Jute Products</a>
                        <a href="<?= base_url('products/dhokra') ?>" class="dropdown-item">Dhokra Products</a>
                        <a href="<?= base_url('products/terracotta') ?>" class="dropdown-item">Terracotta Products</a>
                        <a href="<?= base_url('products/fruit-vegetable') ?>" class="dropdown-item">Fruit &amp; Vegetable Products</a>
                    </div>
                </div>
                <a href="<?= base_url('blog') ?>" class="nav-item nav-link<?= $active === 'blog' ? ' active' : '' ?>">Blog</a>
                <a href="<?= base_url('contact') ?>" class="nav-item nav-link<?= $active === 'contact' ? ' active' : '' ?>">Contact</a>
            </div>
            <h4 class="m-0 pe-lg-5 d-none d-lg-block"><i class="fa fa-phone-square text-primary me-3"></i><?= e($phone) ?></h4>
        </div>
    </nav>
    <!-- Navbar End -->
