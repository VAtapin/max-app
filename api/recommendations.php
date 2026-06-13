<?php

require __DIR__ . '/bootstrap.php';

$user = require_platform_user();

$stmt = db()->prepare(
    'SELECT r.*,
            p.title AS product_title,
            p.short_description,
            p.full_description,
            p.image_path,
            p.document_path,
            p.video_url,
            p.purchase_url,
            pc.title AS category_title
     FROM recommendations r
     LEFT JOIN products p ON p.id = r.product_id
     LEFT JOIN product_categories pc ON pc.id = COALESCE(r.category_id, p.category_id)
     WHERE r.end_user_id = :end_user_id
     ORDER BY r.score DESC, r.id DESC'
);
$stmt->execute(['end_user_id' => $user['id']]);
$recommendations = $stmt->fetchAll();

if (!$recommendations) {
    [$ownerWhere, $ownerParams] = client_owner_scope($user, 'p');
    $fallbackStmt = db()->prepare(
        "SELECT p.id AS product_id,
                p.title AS product_title,
                p.short_description,
                p.full_description,
                p.image_path,
                p.document_path,
                p.video_url,
                p.purchase_url,
                pc.title AS category_title
         FROM products p
         LEFT JOIN product_categories pc ON pc.id = p.category_id
         WHERE p.is_active = 1 AND $ownerWhere
         ORDER BY p.sort_order, p.id
         LIMIT 5"
    );
    $fallbackStmt->execute($ownerParams);
    $fallback = $fallbackStmt->fetchAll();
    json_response(['recommendations' => $fallback, 'disclaimer' => medical_disclaimer()]);
}

json_response(['recommendations' => $recommendations, 'disclaimer' => medical_disclaimer()]);
