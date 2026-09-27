<?php

/**
 * Import/upsert all products from tools/products-master.json into the SQLite database.
 * Usage: php tools/import_products.php
 */

require_once __DIR__ . '/../config/bootstrap.php';

echo "=== BCE Export Product Importer ===\n";

$masterFile = __DIR__ . '/products-master.json';
if (!file_exists($masterFile)) {
    echo "ERROR: Master catalog file tools/products-master.json not found!\n";
    exit(1);
}

$products = json_decode(file_get_contents($masterFile), true);
if (!$products) {
    echo "ERROR: Invalid JSON in tools/products-master.json!\n";
    exit(1);
}

global $pdo;
$now = date('Y-m-d H:i:s');

$checkStmt = $pdo->prepare("SELECT id FROM products WHERE slug = ?");
$insertStmt = $pdo->prepare("INSERT INTO products (slug, name, category_id, short_description, description, image, featured, status, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$updateStmt = $pdo->prepare("UPDATE products SET name = ?, category_id = ?, short_description = ?, description = ?, image = ?, featured = ?, status = ?, sort_order = ?, updated_at = ? WHERE slug = ?");

$inserted = 0;
$updated = 0;

foreach ($products as $p) {
    $slug = $p['slug'];
    $checkStmt->execute([$slug]);
    if ($checkStmt->fetch()) {
        $updateStmt->execute([
            $p['name'],
            $p['category_id'],
            $p['short_description'] ?? '',
            $p['description'] ?? '',
            $p['image'] ?? '',
            !empty($p['featured']) ? 1 : 0,
            $p['status'] ?? 'published',
            $p['sort_order'] ?? 1,
            $now,
            $slug
        ]);
        $updated++;
    } else {
        $insertStmt->execute([
            $slug,
            $p['name'],
            $p['category_id'],
            $p['short_description'] ?? '',
            $p['description'] ?? '',
            $p['image'] ?? '',
            !empty($p['featured']) ? 1 : 0,
            $p['status'] ?? 'published',
            $p['sort_order'] ?? 1,
            $now,
            $now
        ]);
        $inserted++;
    }
}

echo "SUCCESS: Processed " . count($products) . " products ($inserted inserted, $updated updated).\n";
