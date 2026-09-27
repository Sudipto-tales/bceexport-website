<?php

function make_slug(string $name): string {
    $s = strtolower(trim($name));
    $s = str_replace('&', ' and ', $s);
    $s = str_replace("'", '', $s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim($s, '-');
    return $s ?: 'item';
}

$categories_map = [
    'leather' => [
        'file' => 'leather.html',
        'default_desc' => 'Premium handcrafted genuine leather products & bags.',
        'hero_image' => '/img/Leather01.webp'
    ],
    'wooden-handicraft' => [
        'file' => 'wooden_handicraft.html',
        'default_desc' => 'Exquisite hand-carved wooden decorative items and sculptures.',
        'hero_image' => '/img/Wooden Crafts.webp'
    ],
    'furniture' => [
        'file' => 'Furniture.html',
        'default_desc' => 'Royal handcrafted wooden, cane and designer furniture.',
        'hero_image' => '/img/Furniture_Main.webp'
    ],
    'jute' => [
        'file' => 'jute.html',
        'default_desc' => 'Eco-friendly sustainable jute bags, rugs & home decor.',
        'hero_image' => '/img/Handcrafted_jute.webp'
    ],
    'dhokra' => [
        'file' => 'Dhokra.html',
        'default_desc' => 'Ancient non-ferrous lost-wax metal casting art form of Bengal.',
        'hero_image' => '/img/Dhokra Handcrafted.webp'
    ],
    'terracotta' => [
        'file' => 'Terracotta.html',
        'default_desc' => 'Traditional clay pottery, statues, diyas & wall decor.',
        'hero_image' => '/img/Terracotta01.webp'
    ],
    'fruit-vegetable' => [
        'file' => 'fruit-vegetable.html',
        'default_desc' => 'Fresh, organic export-grade agricultural produce from Indian farms.',
        'hero_image' => '/img/Fruit.webp'
    ]
];

$all_products = [];
$used_slugs = [];

foreach ($categories_map as $cat_slug => $cat_info) {
    $path = __DIR__ . '/../' . $cat_info['file'];
    if (!file_exists($path)) {
        echo "Warning: File not found {$cat_info['file']}\n";
        continue;
    }

    $content = file_get_contents($path);
    $doc = new DOMDocument();
    @$doc->loadHTML($content);
    $xpath = new DOMXPath($doc);

    // Find product items (team-item divs)
    $items = $xpath->query('//div[contains(@class, "team-item")]');

    $sort_order = 1;

    foreach ($items as $item) {
        $h5s = $xpath->query('.//h5', $item);
        if ($h5s->length === 0) continue;

        $name = trim($h5s->item(0)->textContent);
        if ($name === '' || in_array($name, ['Full Name', 'Quick Links', 'Contact Us'], true)) continue;

        // Image source
        $imgs = $xpath->query('.//img', $item);
        $image_path = $cat_info['hero_image'];
        if ($imgs->length > 0) {
            $src = $imgs->item(0)->getAttribute('src');
            if ($src) {
                $image_path = '/' . ltrim($src, '/');
            }
        }

        // Generate unique slug
        $base_slug = make_slug($name);
        $slug = $base_slug;
        $counter = 2;
        while (isset($used_slugs[$slug])) {
            $slug = $base_slug . '-' . $counter;
            $counter++;
        }
        $used_slugs[$slug] = true;

        $all_products[] = [
            'name' => $name,
            'slug' => $slug,
            'category_id' => $cat_slug,
            'short_description' => "Export-grade {$name} from BCE Export catalog.",
            'description' => "Premium quality {$name}, handcrafted and processed for global export markets.",
            'image' => $image_path,
            'featured' => ($sort_order <= 3) ? 1 : 0,
            'status' => 'published',
            'sort_order' => $sort_order++
        ];
    }
}

$output_file = __DIR__ . '/products-master.json';
file_put_contents($output_file, json_encode($all_products, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "Generated " . count($all_products) . " master products in $output_file\n";
