<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../database/bceexport.sqlite');
echo "CATEGORIES:\n";
print_r($pdo->query('SELECT id, slug, name FROM categories')->fetchAll(PDO::FETCH_ASSOC));
echo "\nPRODUCTS (sample):\n";
print_r($pdo->query('SELECT id, slug, name, category_id FROM products LIMIT 5')->fetchAll(PDO::FETCH_ASSOC));
