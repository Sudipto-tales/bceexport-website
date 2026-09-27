<?php

function make_slug(string $name): string {
    $s = strtolower(trim($name));
    $s = str_replace(['&', "'"], ['and', ''], $s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim($s, '-');
    return $s ?: 'item';
}

$categories_map = [
    'leather' => 'leather.html',
    'wooden-handicraft' => 'wooden_handicraft.html',
    'furniture' => 'Furniture.html',
    'jute' => 'jute.html',
    'dhokra' => 'Dhokra.html',
    'terracotta' => 'Terracotta.html',
    'fruit-vegetable' => 'fruit-vegetable.html',
];

$extracted = [];

foreach ($categories_map as $cat_slug => $file) {
    $path = __DIR__ . '/../' . $file;
    if (!file_exists($path)) {
        echo "File not found: $file\n";
        continue;
    }

    $content = file_get_contents($path);
    $doc = new DOMDocument();
    @$doc->loadHTML($content);
    $xpath = new DOMXPath($doc);

    // Let's find product cards. Usually h5 with mb-0 or inside card-body or similar
    // Let's query all h5 elements
    $h5s = $xpath->query('//h5');
    $images = $xpath->query('//img');

    echo "=== $cat_slug ($file) ===\n";
    $titles = [];
    foreach ($h5s as $h5) {
        $t = trim($h5->textContent);
        if ($t !== '' && strlen($t) < 80 && !in_array($t, ['Quick Links', 'Contact Us', 'Popular Links', 'Newsletter', 'Follow Us', 'BCE EXPORT', 'Categories', 'Our Services', 'Get In Touch'], true)) {
            $titles[] = $t;
        }
    }

    $img_sources = [];
    foreach ($images as $img) {
        $src = $img->getAttribute('src');
        if (str_contains($src, 'img/')) {
            // exclude logos, headers if needed
            if (!str_contains($src, 'logo') && !str_contains($src, 'certification') && !str_contains($src, 'testimonial') && !str_contains($src, 'team')) {
                $img_sources[] = '/' . ltrim($src, '/');
            }
        }
    }

    echo "Found " . count($titles) . " titles:\n";
    foreach ($titles as $idx => $t) {
        $slug = make_slug($t);
        echo "  [" . ($idx + 1) . "] $t -> $slug\n";
    }
    echo "Found " . count($img_sources) . " images in img/:\n";
    foreach ($img_sources as $idx => $src) {
        echo "  [" . ($idx + 1) . "] $src\n";
    }
    echo "\n";
}
