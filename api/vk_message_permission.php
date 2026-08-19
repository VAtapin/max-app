<?php

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../admin/app/core/client_journey.php';
require_once __DIR__ . '/../admin/app/core/social_messaging.php';

function vk_permission_record(int $endUserId, string $groupId): ?array
{
    $stmt = db()->prepare(
        'SELECT p.*, pa.id AS platform_account_id, pa.platform AS account_platform, pa.platform_user_id
         FROM vk_message_permissions p
         INNER JOIN platform_accounts pa ON pa.id = p.platform_account_id
         WHERE p.end_user_id = :end_user_id AND p.group_id = :group_id
         LIMIT 1'
    );
    $stmt->execute([
        'end_user_id' => $endUserId,
        'group_id' => $groupId,
    ]);
    return $stmt->fetch() ?: null;
}

function vk_permission_response(array $integration, ?array $permission, bool $providerChecked = false): never
{
    json_response([
        'active' => true,
        'status' => $permission['status'] ?? 'unknown',
        'delivery_enabled' => !empty($permission['delivery_enabled']),
        'provider_checked' => $providerChecked,
        'group_id' => (string)$integration['external_id'],
        'title' => (string)$integration['title'],
    ]);
}

/**
 * Verifies the record with VK where the actual VK user ID is known. A temporary
 * API failure deliberately leaves the saved state untouched.
 */
function vk_permission_check_provider(
    int $endUserId,
    array $integration,
    ?array $permission,
    string $vkUserId,
    ?bool $knownProviderAllowed = null
): ?array
{
    if ($vkUserId === '') {
        return $permission;
    }

    $providerAllowed = $knownProviderAllowed ?? messaging_vk_provider_permission_status($integration, $vkUserId);
    if ($providerAllowed === null) {
        return $permission;
    }

    $account = $permission;
    if (!$account || (string)$account['account_platform'] !== 'VK' || (string)$account['platform_user_id'] !== $vkUserId) {
        $account = messaging_vk_platform_account($endUserId, $vkUserId);
    }
    if (!$account) {
        return $permission;
    }

    $platformAccountId = (int)($account['platform_account_id'] ?? $account['id']);
    messaging_upsert_vk_permission(
        $endUserId,
        $platformAccountId,
        $integration,
        $providerAllowed ? 'allowed' : 'denied',
        null,
        null,
        $permission ? (bool)$permission['delivery_enabled'] : $providerAllowed
    );
    messaging_sync_vk_account_allowed($platformAccountId);
    return vk_permission_record($endUserId, (string)$integration['external_id']);
}

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

$groupId = (string)$integration['external_id'];
$permission = vk_permission_record((int)$user['id'], $groupId);

if ($action === 'check') {
    $vkUserId = $platform === 'VK'
        ? $platformUserId
        : (($permission && (string)$permission['account_platform'] === 'VK') ? (string)$permission['platform_user_id'] : '');
    $providerAllowed = $vkUserId !== '' ? messaging_vk_provider_permission_status($integration, $vkUserId) : null;
    if ($providerAllowed !== null) {
        $permission = vk_permission_check_provider((int)$user['id'], $integration, $permission, $vkUserId, $providerAllowed);
    }
    vk_permission_response($integration, $permission, $providerAllowed !== null);
}

if ($action === 'revoke') {
    if ($permission) {
        $disable = db()->prepare(
            'UPDATE vk_message_permissions
             SET delivery_enabled = 0
             WHERE id = :id'
        );
        $disable->execute(['id' => (int)$permission['id']]);
        $permission = vk_permission_record((int)$user['id'], $groupId);
    }
    vk_permission_response($integration, $permission);
}

if ($action === 'enable_delivery') {
    $vkUserId = $platform === 'VK'
        ? $platformUserId
        : (($permission && (string)$permission['account_platform'] === 'VK') ? (string)$permission['platform_user_id'] : '');
    $providerAllowed = $vkUserId !== '' ? messaging_vk_provider_permission_status($integration, $vkUserId) : null;
    if ($providerAllowed !== true || !$permission) {
        if ($providerAllowed === false && $permission) {
            $permission = vk_permission_check_provider((int)$user['id'], $integration, $permission, $vkUserId, false);
        }
        vk_permission_response($integration, $permission, $providerAllowed !== null);
    }
    $enable = db()->prepare('UPDATE vk_message_permissions SET delivery_enabled = 1 WHERE id = :id');
    $enable->execute(['id' => (int)$permission['id']]);
    $permission = vk_permission_check_provider((int)$user['id'], $integration, vk_permission_record((int)$user['id'], $groupId), $vkUserId, true);
    vk_permission_response($integration, $permission, true);
}

if ($action === 'allow') {
    if ($platform !== 'VK') {
        json_response(['error' => 'Это действие доступно только внутри VK Mini App.'], 422);
    }
    messaging_mark_vk_permission_allowed((int)$user['id'], $platformUserId, $integration);
    $permission = vk_permission_check_provider((int)$user['id'], $integration, vk_permission_record((int)$user['id'], $groupId), $platformUserId);
    vk_permission_response($integration, $permission, $permission !== null);
}

if ($action === 'confirm') {
    $vkUserId = preg_replace('/\D+/', '', (string)($data['vk_user_id'] ?? '')) ?? '';
    $key = trim((string)($data['key'] ?? ''));
    $allowed = !empty($data['allowed']);
    if ($platform !== 'web' || $vkUserId === '' || $key === '') {
        json_response(['error' => 'Не удалось подтвердить разрешение VK.'], 422);
    }
    if (!messaging_apply_vk_permission_request($key, $groupId, $vkUserId, $allowed)) {
        json_response(['error' => 'Срок подтверждения VK истёк. Нажмите кнопку разрешения ещё раз.'], 409);
    }
    $permission = vk_permission_record((int)$user['id'], $groupId);
    $permission = vk_permission_check_provider((int)$user['id'], $integration, $permission, $vkUserId);
    vk_permission_response($integration, $permission, $permission !== null);
}

if ($permission && (string)$permission['status'] === 'allowed') {
    vk_permission_response($integration, $permission);
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
    date('Y-m-d H:i:s', time() + 15 * 60),
    true
);

json_response([
    'active' => true,
    'status' => 'pending',
    'delivery_enabled' => true,
    'provider_checked' => false,
    'group_id' => $groupId,
    'title' => (string)$integration['title'],
    'key' => $requestKey,
    'app_id' => $vkAppId,
    'flow' => $platform === 'VK' ? 'mini_app' : 'web_widget',
]);
