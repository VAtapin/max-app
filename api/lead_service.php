<?php

function create_lead_for_user(array $user, array $data): int
{
    $sourcePlatform = $data['platform'] ?? $user['platform'];
    if (!in_array($sourcePlatform, ['telegram', 'vk', 'max', 'web'], true)) {
        $sourcePlatform = $user['platform'];
    }

    $stmt = db()->prepare(
        'INSERT INTO leads (end_user_id, manager_id, reseller_id, product_id, source_platform, message)
         VALUES (:end_user_id, :manager_id, :reseller_id, :product_id, :source_platform, :message)'
    );
    $stmt->execute([
        'end_user_id' => $user['id'],
        'manager_id' => $user['manager_id'],
        'reseller_id' => $user['reseller_id'],
        'product_id' => isset($data['product_id']) && $data['product_id'] !== '' ? (int)$data['product_id'] : null,
        'source_platform' => $sourcePlatform,
        'message' => $data['message'] ?? app_text('auto.k_d169a041af9d'),
    ]);
    $leadId = (int)db()->lastInsertId();

    $log = db()->prepare(
        'INSERT INTO activity_logs (actor_type, actor_id, action, entity_type, entity_id, details)
         VALUES ("end_user", :actor_id, "create_lead", "leads", :entity_id, :details)'
    );
    $log->execute([
        'actor_id' => $user['id'],
        'entity_id' => $leadId,
        'details' => json_encode(['platform' => $sourcePlatform], JSON_UNESCAPED_UNICODE),
    ]);

    return $leadId;
}
