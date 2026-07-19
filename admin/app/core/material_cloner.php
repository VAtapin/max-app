<?php

function manager_reseller_for_materials(int $managerId, ?int $fallbackResellerId = null): ?int
{
    if ($fallbackResellerId !== null && $fallbackResellerId > 0) {
        return $fallbackResellerId;
    }

    $stmt = db()->prepare('SELECT reseller_id FROM managers WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $managerId]);
    $resellerId = $stmt->fetchColumn();

    return $resellerId !== false && $resellerId !== null ? (int)$resellerId : null;
}

function clone_reseller_materials_for_manager(?int $managerId, ?int $resellerId = null): void
{
    $managerId = (int)$managerId;
    if ($managerId <= 0) {
        return;
    }

    $resellerId = manager_reseller_for_materials($managerId, $resellerId);
    if (!$resellerId) {
        return;
    }

    $materials = db()->prepare(
        'SELECT *
         FROM content_posts source
         WHERE source.owner_type = "reseller"
           AND source.owner_id = :reseller_id
           AND source.status <> "hidden"
           AND NOT EXISTS (
                SELECT 1
                FROM content_posts clone
                WHERE clone.owner_type = "manager"
                  AND clone.owner_id = :manager_id
                  AND clone.source_content_post_id = source.id
           )'
    );
    $materials->execute([
        'reseller_id' => $resellerId,
        'manager_id' => $managerId,
    ]);

    $insert = db()->prepare(
        'INSERT IGNORE INTO content_posts (
            content_type, section_type, title, short_text, full_text,
            image_path, attachment_path, video_url, button_text, button_url,
            category_id, owner_type, owner_id, status, publish_at, created_by,
            source_content_post_id
         ) VALUES (
            :content_type, :section_type, :title, :short_text, :full_text,
            :image_path, :attachment_path, :video_url, :button_text, :button_url,
            :category_id, "manager", :manager_id, :status, :publish_at, :created_by,
            :source_content_post_id
         )'
    );

    foreach ($materials->fetchAll() as $material) {
        $insert->execute([
            'content_type' => $material['content_type'],
            'section_type' => $material['section_type'],
            'title' => $material['title'],
            'short_text' => $material['short_text'],
            'full_text' => $material['full_text'],
            'image_path' => $material['image_path'],
            'attachment_path' => $material['attachment_path'],
            'video_url' => $material['video_url'],
            'button_text' => $material['button_text'],
            'button_url' => $material['button_url'],
            'category_id' => $material['category_id'],
            'manager_id' => $managerId,
            'status' => $material['status'],
            'publish_at' => $material['publish_at'],
            'created_by' => $material['created_by'],
            'source_content_post_id' => (int)$material['id'],
        ]);
    }
}
