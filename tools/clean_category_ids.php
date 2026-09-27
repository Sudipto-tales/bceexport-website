<?php
require_once __DIR__ . '/../config/bootstrap.php';
global $pdo;

$map = [
    '1' => 'leather',
    '2' => 'wooden-handicraft',
    '3' => 'furniture',
    '4' => 'jute',
    '5' => 'dhokra',
    '6' => 'terracotta',
    '7' => 'fruit-vegetable',
];

$stmt = $pdo->prepare("UPDATE products SET category_id = ? WHERE category_id = ?");

foreach ($map as $id => $slug) {
    $stmt->execute([$slug, $id]);
}

echo "Cleaned up integer category IDs in products table.\n";
