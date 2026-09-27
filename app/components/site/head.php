<?php
/**
 * Site <head> component — shared across all public pages.
 *
 * Variables: $title (string), $description (string, optional)
 */
$rawTitle = $title ?? 'BCE Export';
if (str_contains($rawTitle, 'BCE Export')) {
    $siteTitle = $rawTitle;
} else {
    $siteTitle = $rawTitle . ' | BCE Export';
}

$pageDescription = $description ?? 'Leading exporter of authentic Indian Handicrafts, Leather, Jute, Dhokra, Terracotta, Furniture, and Produce from West Bengal, India.';
$currentRoute = trim((string) ($_GET['route'] ?? ''), '/');
$canonicalUrl = $canonical ?? base_url($currentRoute ? '/' . ltrim($currentRoute, '/') : '/');
$ogImg = $ogImage ?? base_url('img/about.webp');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title><?= e($siteTitle) ?></title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta content="<?= e($pageDescription) ?>" name="description">
    <meta content="BCE Export, Indian Handicrafts, Export, Leather Goods, Jute Bags, Dhokra Art, Terracotta, Handmade Furniture, Bankura Handicrafts" name="keywords">

    <link rel="canonical" href="<?= e($canonicalUrl) ?>">
    <meta name="robots" content="index, follow, max-image-preview:large">
    <meta name="google-site-verification" content="yhUfttoPHNlNl12fLy1nd-gCTwiZ9Kj6rI3y8Y0fLb4">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="<?= e($ogType ?? 'website') ?>">
    <meta property="og:site_name" content="BCE Export">
    <meta property="og:title" content="<?= e($siteTitle) ?>">
    <meta property="og:description" content="<?= e($pageDescription) ?>">
    <meta property="og:url" content="<?= e($canonicalUrl) ?>">
    <meta property="og:image" content="<?= e($ogImg) ?>">
    <meta property="og:locale" content="en_IN">

    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($siteTitle) ?>">
    <meta name="twitter:description" content="<?= e($pageDescription) ?>">
    <meta name="twitter:image" content="<?= e($ogImg) ?>">

    <meta name="geo.region" content="IN-WB">
    <meta name="geo.placename" content="Bankura, West Bengal">
    <meta name="author" content="BCE Export">

    <!-- Structured Data Schema.org -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@graph": [
        {
          "@type": "Organization",
          "@id": "https://www.bceexport.com/#organization",
          "name": "BCE Export",
          "alternateName": ["Bceexport", "BCE Exports"],
          "url": "https://www.bceexport.com/",
          "logo": "https://www.bceexport.com/img/logo.webp",
          "email": "admin@bceexport.com",
          "telephone": "+91-8900379037",
          "address": {
            "@type": "PostalAddress",
            "streetAddress": "Arabindanagar (N)",
            "addressLocality": "Bankura",
            "addressRegion": "West Bengal",
            "postalCode": "722101",
            "addressCountry": "IN"
          }
        },
        {
          "@type": "WebSite",
          "@id": "https://www.bceexport.com/#website",
          "url": "https://www.bceexport.com/",
          "name": "BCE Export",
          "publisher": {
            "@id": "https://www.bceexport.com/#organization"
          }
        }
      ]
    }
    </script>


    <!-- Google Web Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Roboto:wght@500;700&display=swap" rel="stylesheet">

    <!-- Icon Font Stylesheet -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Libraries Stylesheet -->
    <link href="<?= base_url('lib/animate/animate.min.css') ?>" rel="stylesheet">
    <link href="<?= base_url('lib/owlcarousel/assets/owl.carousel.min.css') ?>" rel="stylesheet">

    <!-- Customized Bootstrap Stylesheet -->
    <link href="<?= base_url('css/bootstrap.min.css') ?>" rel="stylesheet">

    <!-- Template Stylesheet -->
    <link href="<?= base_url('css/style.css') ?>" rel="stylesheet">
</head>

<body>
    <!-- Spinner Start -->
    <div id="spinner" class="show bg-white position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center">
        <div class="spinner-grow text-primary" style="width: 3rem; height: 3rem;" role="status">
            <span class="sr-only">Loading...</span>
        </div>
    </div>
    <!-- Spinner End -->
