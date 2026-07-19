<?php

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../admin/app/core/material_cloner.php';

$ownerWhere = '1 = 0';
$ownerParams = [];
if (isset($_GET['platform'], $_GET['platform_user_id'])) {
    $user = require_platform_user();
    if (!empty($user['manager_id'])) {
        clone_reseller_materials_for_manager((int)$user['manager_id'], isset($user['reseller_id']) ? (int)$user['reseller_id'] : null);
    }
    [$ownerWhere, $ownerParams] = client_material_owner_scope($user, 'c');
}

if (isset($_GET['id'])) {
    $stmt = db()->prepare(
        "SELECT c.*, pc.title AS category_title
         FROM content_posts c
         LEFT JOIN product_categories pc ON pc.id = c.category_id
         WHERE c.id = :id
           AND c.status = 'published'
           AND $ownerWhere
         LIMIT 1"
    );
    $stmt->execute(['id' => (int)$_GET['id']] + $ownerParams);
    $content = $stmt->fetch();
    $content ? json_response(['content' => $content]) : json_response(['error' => 'not found'], 404);
}

$stmt = db()->prepare(
    "SELECT c.id, c.content_type, c.title, c.short_text, c.full_text, c.image_path,
            c.attachment_path, c.video_url, c.button_text, c.button_url, c.publish_at,
            pc.title AS category_title
     FROM content_posts c
     LEFT JOIN product_categories pc ON pc.id = c.category_id
     WHERE c.status = 'published'
       AND $ownerWhere
     ORDER BY COALESCE(c.publish_at, c.created_at) DESC, c.id DESC"
);
$stmt->execute($ownerParams);

json_response(['materials' => $stmt->fetchAll()]);
