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
    $productVariantId = isset($data['product_variant_id']) && $data['product_variant_id'] !== '' ? (int)$data['product_variant_id'] : null;
    $recommendationId = isset($data['recommendation_id']) && $data['recommendation_id'] !== '' ? (int)$data['recommendation_id'] : null;
    if ($productVariantId) {
        $variantCheck = db()->prepare('SELECT id FROM product_variants WHERE id = :id AND product_id = :product_id AND is_active = 1 LIMIT 1');
        $variantCheck->execute(['id' => $productVariantId, 'product_id' => $productId]);
        if (!$variantCheck->fetchColumn()) {
            $productVariantId = null;
        }
    }
    if ($recommendationId) {
        $recommendationCheck = db()->prepare('SELECT id FROM recommendations WHERE id = :id AND end_user_id = :end_user_id AND product_id = :product_id LIMIT 1');
        $recommendationCheck->execute(['id' => $recommendationId, 'end_user_id' => (int)$user['id'], 'product_id' => $productId]);
        if (!$recommendationCheck->fetchColumn()) {
            $recommendationId = null;
        }
    }
    $requestType = normalize_lead_request_type($data['request_type'] ?? null, $productId);
    $context = is_array($data['recommendation_context'] ?? null) ? $data['recommendation_context'] : [];

    $stmt = db()->prepare(
        'INSERT INTO leads (
            end_user_id, manager_id, reseller_id, product_id, product_variant_id, recommendation_id, recommendation_context_json, request_type, source_platform, message
         ) VALUES (
            :end_user_id, :manager_id, :reseller_id, :product_id, :product_variant_id, :recommendation_id, :recommendation_context_json, :request_type, :source_platform, :message
         )'
    );
    $stmt->execute([
        'end_user_id' => $user['id'],
        'manager_id' => $user['manager_id'],
        'reseller_id' => $user['reseller_id'],
        'product_id' => $productId,
        'product_variant_id' => $productVariantId,
        'recommendation_id' => $recommendationId,
        'recommendation_context_json' => $context ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
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
        'details' => json_encode(['platform' => $sourcePlatform, 'product_id' => $productId, 'product_variant_id' => $productVariantId, 'recommendation_id' => $recommendationId], JSON_UNESCAPED_UNICODE),
    ]);

    update_client_stage((int)$user['id'], 'consultation_requested');
    notify_consultant_about_contact($user, $leadId);

    return $leadId;
}
