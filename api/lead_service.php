<?php

require_once __DIR__ . '/../admin/app/core/client_journey.php';

function normalize_lead_request_type(?string $value, ?int $productId = null): string
{
    if ($productId) {
        return 'product';
    }

    $value = trim((string)$value);
    return in_array($value, ['consultation', 'test_result', 'cashback', 'cooperation', 'other'], true)
        ? $value
        : 'consultation';
}

function create_lead_for_user(array $user, array $data): int
{
    $sourcePlatform = $data['platform'] ?? $user['current_platform'] ?? $user['platform'];
    $sourcePlatform = normalize_platform((string)$sourcePlatform);
    if (!in_array($sourcePlatform, ['telegram', 'VK', 'OK', 'MAX', 'web'], true)) {
        $sourcePlatform = $user['platform'];
    }
    $productId = isset($data['product_id']) && $data['product_id'] !== '' ? (int)$data['product_id'] : null;
    $requestType = normalize_lead_request_type($data['request_type'] ?? null, $productId);

    $stmt = db()->prepare(
        'INSERT INTO leads (
            end_user_id, manager_id, reseller_id, product_id, request_type, source_platform, message
         ) VALUES (
            :end_user_id, :manager_id, :reseller_id, :product_id, :request_type, :source_platform, :message
         )'
    );
    $stmt->execute([
        'end_user_id' => $user['id'],
        'manager_id' => $user['manager_id'],
        'reseller_id' => $user['reseller_id'],
        'product_id' => $productId,
        'request_type' => $requestType,
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

    update_client_stage((int)$user['id'], 'consultation_requested');
    notify_consultant_about_contact($user, $leadId);

    return $leadId;
}
