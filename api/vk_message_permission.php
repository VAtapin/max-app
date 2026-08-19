<?php

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../admin/app/core/client_journey.php';
require_once __DIR__ . '/../admin/app/core/social_messaging.php';

$data = input_json() ?: $_REQUEST;
$user = require_platform_user($data);
$platform = normalize_platform((string)($data['platform'] ?? $user['current_platform'] ?? $user['platform'] ?? ''));
$action = strtolower(trim((string)($data['action'] ?? 'prepare')));

if (!in_array($platform, ['VK', 'web'], true)) {
    json_response(['error' => 'Подключение сообщений VK недоступно на этой платформе.'], 422);
}

$documents = active_legal_documents();
if (empty($documents['marketing_consent'])) {
    json_response(['active' => false, 'error' => 'Согласие на рассылку сейчас отключено.'], 409);
}

$platformUserId = preg_replace('/\D+/', '', (string)($data['platform_user_id'] ?? '')) ?? '';
if ($platform === 'VK') {
    $account = messaging_vk_platform_account((int)$user['id'], $platformUserId);
} else {
    $accountStmt = db()->prepare(
        'SELECT * FROM platform_accounts
         WHERE end_user_id = :end_user_id AND platform = "web"
         ORDER BY id DESC LIMIT 1'
    );
    $accountStmt->execute(['end_user_id' => (int)$user['id']]);
    $account = $accountStmt->fetch() ?: null;
}
if (!$account || ($platform === 'VK' && $platformUserId === '')) {
    json_response(['error' => 'Аккаунт пользователя не подтверждён.'], 422);
}

$integration = messaging_integration_for_owner(
    'VK',
    !empty($user['manager_id']) ? (int)$user['manager_id'] : null,
    !empty($user['reseller_id']) ? (int)$user['reseller_id'] : null
);
if (!$integration) {
    json_response(['error' => 'Нет готового сообщества VK для подключения. Укажите email для получения уведомлений.'], 409);
}

if ($action === 'revoke') {
    $permissionStmt = db()->prepare(
        'SELECT id, platform_account_id
         FROM vk_message_permissions
         WHERE end_user_id = :end_user_id AND group_id = :group_id
         LIMIT 1'
    );
    $permissionStmt->execute([
        'end_user_id' => (int)$user['id'],
        'group_id' => (string)$integration['external_id'],
    ]);
    $permission = $permissionStmt->fetch() ?: null;
    if ($permission) {
        $revoke = db()->prepare(
            'UPDATE vk_message_permissions
             SET status = "denied", request_key_hash = NULL, request_expires_at = NULL,
                 denied_at = NOW()
             WHERE id = :id'
        );
        $revoke->execute(['id' => (int)$permission['id']]);
        messaging_sync_vk_account_allowed((int)$permission['platform_account_id']);
    }
    json_response([
        'active' => true,
        'status' => 'denied',
        'group_id' => (string)$integration['external_id'],
        'title' => (string)$integration['title'],
    ]);
}

$existing = null;
if ($platform === 'VK') {
    $existing = messaging_vk_permission_for_user((int)$user['id'], $platformUserId, (string)$integration['external_id']);
} else {
    $existingStmt = db()->prepare(
        'SELECT p.*, i.*, i.id AS integration_id
         FROM vk_message_permissions p
         INNER JOIN messaging_integrations i ON i.id = p.integration_id
         WHERE p.end_user_id = :end_user_id
           AND p.group_id = :group_id
           AND p.status = "allowed"
         LIMIT 1'
    );
    $existingStmt->execute([
        'end_user_id' => (int)$user['id'],
        'group_id' => (string)$integration['external_id'],
    ]);
    $existing = $existingStmt->fetch() ?: null;
}
if ($existing) {
    json_response([
        'active' => true,
        'status' => 'allowed',
        'group_id' => (string)$integration['external_id'],
        'title' => (string)$integration['title'],
    ]);
}

$vkAppId = preg_replace('/\D+/', '', (string)(app_config()['integrations']['vk_app_id'] ?? '')) ?: null;
if ($platform === 'web' && !$vkAppId) {
    json_response(['error' => 'VK App ID не настроен. Укажите email для получения уведомлений.'], 409);
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
    'app_id' => $vkAppId,
    'flow' => $platform === 'VK' ? 'mini_app' : 'web_widget',
]);
