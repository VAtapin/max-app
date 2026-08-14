<?php

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../admin/app/core/client_journey.php';
require_once __DIR__ . '/../admin/app/core/social_messaging.php';

$data = input_json() ?: $_REQUEST;
$user = require_platform_user($data);
$platform = normalize_platform((string)($data['platform'] ?? $user['current_platform'] ?? $user['platform'] ?? ''));

if ($platform !== 'VK') {
    json_response(['error' => 'Разрешение сообщений VK доступно только внутри приложения VK.'], 422);
}

$documents = active_legal_documents();
if (empty($documents['marketing_consent'])) {
    json_response(['active' => false, 'error' => 'Согласие на рассылку сейчас отключено.'], 409);
}

$platformUserId = preg_replace('/\D+/', '', (string)($data['platform_user_id'] ?? '')) ?? '';
$account = messaging_vk_platform_account((int)$user['id'], $platformUserId);
if (!$account || $platformUserId === '') {
    json_response(['error' => 'Аккаунт VK не подтверждён.'], 422);
}

$integration = messaging_integration_for_owner(
    'VK',
    !empty($user['manager_id']) ? (int)$user['manager_id'] : null,
    !empty($user['reseller_id']) ? (int)$user['reseller_id'] : null
);
if (!$integration) {
    json_response(['error' => 'Нет готового сообщества VK для подключения. Укажите email для получения уведомлений.'], 409);
}

$existing = messaging_vk_permission_for_user((int)$user['id'], $platformUserId, (int)$integration['id']);
if ($existing) {
    json_response([
        'active' => true,
        'status' => 'allowed',
        'group_id' => (string)$integration['external_id'],
        'title' => (string)$integration['title'],
    ]);
}

$requestKey = bin2hex(random_bytes(24));
messaging_upsert_vk_permission(
    (int)$user['id'],
    (int)$account['id'],
    $integration,
    'pending',
    hash('sha256', $requestKey),
    date('Y-m-d H:i:s', time() + 15 * 60)
);

json_response([
    'active' => true,
    'status' => 'pending',
    'group_id' => (string)$integration['external_id'],
    'title' => (string)$integration['title'],
    'key' => $requestKey,
]);
