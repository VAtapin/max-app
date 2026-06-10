<?php

require __DIR__ . '/bootstrap.php';

if (isset($_GET['id'])) {
    $stmt = db()->prepare(
        'SELECT p.*, c.title AS category_title
         FROM products p
         LEFT JOIN product_categories c ON c.id = p.category_id
         WHERE p.id = :id AND p.is_active = 1'
    );
    $stmt->execute(['id' => (int)$_GET['id']]);
    $product = $stmt->fetch();
    $product ? json_response(['product' => $product, 'disclaimer' => medical_disclaimer()]) : json_response(['error' => 'not found'], 404);
}

$categoryId = $_GET['category_id'] ?? null;
$sql = 'SELECT id, category_id, title, slug, short_description, image_path, price FROM products WHERE is_active = 1';
$params = [];
if ($categoryId) {
    $sql .= ' AND category_id = :category_id';
    $params['category_id'] = (int)$categoryId;
}
$sql .= ' ORDER BY sort_order, title';

$stmt = db()->prepare($sql);
$stmt->execute($params);
json_response(['products' => $stmt->fetchAll()]);
