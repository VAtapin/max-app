<?php

require __DIR__ . '/bootstrap.php';

if (isset($_GET['id'])) {
    $ownerWhere = 'p.owner_type IS NULL AND p.is_deleted = 0';
    $ownerParams = [];
    if (isset($_GET['platform'], $_GET['platform_user_id'])) {
        $user = require_platform_user();
        [$ownerWhere, $ownerParams] = client_owner_scope($user, 'p');
    }
    $stmt = db()->prepare(
        "SELECT p.*, c.title AS category_title
         FROM products p
         LEFT JOIN product_categories c ON c.id = p.category_id
         WHERE p.id = :id AND p.is_active = 1 AND $ownerWhere"
    );
    $stmt->execute(['id' => (int)$_GET['id']] + $ownerParams);
    $product = $stmt->fetch();
    if (!$product) {
        json_response(['error' => 'not found'], 404);
    }
    if (($product['image_review_status'] ?? '') === 'rejected') {
        $product['image_path'] = null;
    }
    $variantStmt = db()->prepare('SELECT id, sku, title, volume_text, price, currency, image_path, is_sample FROM product_variants WHERE product_id = :product_id AND is_active = 1 ORDER BY is_sample, sort_order, id');
    $variantStmt->execute(['product_id' => (int)$product['id']]);
    $product['variants'] = $variantStmt->fetchAll();
    if (($product['image_review_status'] ?? '') === 'rejected') {
        foreach ($product['variants'] as &$variant) {
            $variant['image_path'] = null;
        }
        unset($variant);
    }
    json_response(['product' => $product]);
}

$categoryId = $_GET['category_id'] ?? null;
$ownerWhere = 'p.owner_type IS NULL AND p.is_deleted = 0';
$params = [];
if (isset($_GET['platform'], $_GET['platform_user_id'])) {
    $user = require_platform_user();
    [$ownerWhere, $params] = client_owner_scope($user, 'p');
}
$sql = "SELECT p.id, p.category_id, p.title, p.slug, p.short_description, p.full_description,
               CASE WHEN p.image_review_status = 'rejected' THEN NULL ELSE p.image_path END image_path, p.document_path, p.video_url, p.purchase_url, p.price,
               p.catalog_sku, p.product_kind, p.recommendation_notice,
               (SELECT pv.id FROM product_variants pv WHERE pv.product_id = p.id AND pv.is_active = 1 ORDER BY pv.is_sample, pv.sort_order, pv.id LIMIT 1) AS primary_variant_id,
               (SELECT pv.sku FROM product_variants pv WHERE pv.product_id = p.id AND pv.is_active = 1 ORDER BY pv.is_sample, pv.sort_order, pv.id LIMIT 1) AS primary_sku,
               c.title AS category_title
        FROM products p
        LEFT JOIN product_categories c ON c.id = p.category_id
        WHERE p.is_active = 1 AND $ownerWhere";
if ($categoryId) {
    $categoryStmt = db()->prepare('SELECT source_category_id FROM product_categories WHERE id = :id LIMIT 1');
    $categoryStmt->execute(['id' => (int)$categoryId]);
    $sourceCategoryId = $categoryStmt->fetchColumn();
    $sql .= ' AND (p.category_id = :category_id OR c.source_category_id = :category_id_clone'
        . ($sourceCategoryId ? ' OR p.category_id = :source_category_id' : '')
        . ')';
    $params['category_id'] = (int)$categoryId;
    $params['category_id_clone'] = (int)$categoryId;
    if ($sourceCategoryId) {
        $params['source_category_id'] = (int)$sourceCategoryId;
    }
}
$sql .= ' ORDER BY p.sort_order, p.title';

$stmt = db()->prepare($sql);
$stmt->execute($params);
json_response(['products' => $stmt->fetchAll()]);
