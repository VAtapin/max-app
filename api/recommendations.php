<?php

require __DIR__ . '/bootstrap.php';

$user = require_platform_user();

$stmt = db()->prepare(
    'SELECT r.*,
            p.title AS product_title,
            p.short_description,
            p.full_description,
            CASE WHEN p.image_review_status = "rejected" THEN NULL ELSE p.image_path END image_path,
            p.document_path,
            p.video_url,
            p.purchase_url,
            p.catalog_sku,
            p.product_kind,
            p.recommendation_notice,
            (SELECT pv.id FROM product_variants pv WHERE pv.product_id = p.id AND pv.is_active = 1 ORDER BY pv.is_sample, pv.sort_order, pv.id LIMIT 1) AS primary_variant_id,
            (SELECT pv.sku FROM product_variants pv WHERE pv.product_id = p.id AND pv.is_active = 1 ORDER BY pv.is_sample, pv.sort_order, pv.id LIMIT 1) AS primary_sku,
            pc.title AS category_title
     FROM recommendations r
     JOIN products p ON p.id = r.product_id
       AND p.is_active = 1 AND p.is_deleted = 0 AND p.ai_enabled = 1 AND p.content_status = "approved"
       AND (p.product_kind NOT IN ("supplement","food") OR (p.safety_review_status = "verified" AND NULLIF(p.composition, "") IS NOT NULL AND NULLIF(p.usage_text, "") IS NOT NULL AND NULLIF(p.warning_text, "") IS NOT NULL AND NULLIF(p.contraindications, "") IS NOT NULL AND NULLIF(p.allowed_claims, "") IS NOT NULL AND NULLIF(p.source_urls, "") IS NOT NULL))
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
                CASE WHEN p.image_review_status = 'rejected' THEN NULL ELSE p.image_path END image_path,
                p.document_path,
                p.video_url,
                p.purchase_url,
                p.catalog_sku,
                p.product_kind,
                p.recommendation_notice,
                (SELECT pv.id FROM product_variants pv WHERE pv.product_id = p.id AND pv.is_active = 1 ORDER BY pv.is_sample, pv.sort_order, pv.id LIMIT 1) AS primary_variant_id,
                (SELECT pv.sku FROM product_variants pv WHERE pv.product_id = p.id AND pv.is_active = 1 ORDER BY pv.is_sample, pv.sort_order, pv.id LIMIT 1) AS primary_sku,
                pc.title AS category_title
         FROM products p
         LEFT JOIN product_categories pc ON pc.id = p.category_id
         WHERE p.is_active = 1 AND p.is_deleted = 0 AND p.ai_enabled = 1 AND p.content_status = 'approved'
           AND (p.product_kind NOT IN ('supplement','food') OR (p.safety_review_status = 'verified' AND NULLIF(p.composition, '') IS NOT NULL AND NULLIF(p.usage_text, '') IS NOT NULL AND NULLIF(p.warning_text, '') IS NOT NULL AND NULLIF(p.contraindications, '') IS NOT NULL AND NULLIF(p.allowed_claims, '') IS NOT NULL AND NULLIF(p.source_urls, '') IS NOT NULL))
           AND $ownerWhere
         ORDER BY p.sort_order, p.id
         LIMIT 5"
    );
    $fallbackStmt->execute($ownerParams);
    $fallback = $fallbackStmt->fetchAll();
    json_response(['recommendations' => $fallback]);
}

json_response(['recommendations' => $recommendations]);
