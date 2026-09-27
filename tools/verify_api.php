<?php
require_once __DIR__ . '/../config/bootstrap.php';

global $pdo;

echo "=== API Verification Checks ===\n";

// 1. Categories check
$cats = db_fetch_all("SELECT id, slug, name FROM categories ORDER BY sort_order");
echo "1. Categories count: " . count($cats) . " (expected 7)\n";
foreach ($cats as $c) {
    echo "   - {$c['slug']}: {$c['name']}\n";
}

// 2. Products count
$total_prods = db_scalar("SELECT COUNT(*) FROM products WHERE deleted_at IS NULL");
echo "\n2. Total published products: $total_prods (expected 192)\n";

// 3. Category ID breakdown
$breakdown = db_fetch_all("SELECT category_id, COUNT(*) as count FROM products WHERE deleted_at IS NULL GROUP BY category_id");
echo "\n3. Category breakdown:\n";
foreach ($breakdown as $b) {
    echo "   - {$b['category_id']}: {$b['count']} products\n";
}

// 4. Test sample product lookups by slug
$slugs_to_test = ['jacket', 'wooden-incense-stick-holder', 'file-folder-b-and-w-jute', 'dhokra-tribal-musician-couple', 'terracotta-flower-vase', 'ginger'];
echo "\n4. Lookup by slug:\n";
foreach ($slugs_to_test as $s) {
    $row = db_fetch_one("SELECT slug, name, category_id, image FROM products WHERE slug = ?", [$s]);
    if ($row) {
        echo "   ✓ FOUND [$s]: {$row['name']} (Cat: {$row['category_id']}, Img: {$row['image']})\n";
    } else {
        echo "   ✗ NOT FOUND [$s]\n";
    }
}
