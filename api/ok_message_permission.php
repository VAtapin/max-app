<?php

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../admin/app/core/client_journey.php';
require_once __DIR__ . '/../admin/app/core/social_messaging.php';

$data = input_json() ?: $_REQUEST;
$user = require_platform_user($data);
$platform = normalize_platform((string)($data['platform'] ?? $user['current_platform'] ?? $user['platform'] ?? ''));
$action = strtolower(trim((string)($data['action'] ?? 'prepare')));

if ($platform !== 'OK') {
    json_response(['error' => 'Подключение сообщений OK доступно только в приложении Одноклассников.'], 422);
}

$documents = active_legal_documents();
if (empty($documents['marketing_consent'])) {
    json_response(['active' => false, 'error' => 'Согласие на рассылку сейчас отключено.'], 409);
}

$platformUserId = trim((string)($data['platform_user_id'] ?? ''));
$account = messaging_ok_platform_account((int)$user['id'], $platformUserId);
if (!$account || $platformUserId === '') {
    json_response(['error' => 'Аккаунт Одноклассников не подтверждён.'], 422);
}

$integration = messaging_integration_for_owner(
    'OK',
    !empty($user['manager_id']) ? (int)$user['manager_id'] : null,
    !empty($user['reseller_id']) ? (int)$user['reseller_id'] : null
);
if (!$integration) {
    json_response(['error' => 'У консультанта пока нет готовой группы OK для сообщений.'], 409);
}

$result = [
    'active' => true,
    'group_id' => (string)$integration['external_id'],
    'title' => (string)$integration['title'],
];

if ($action === 'revoke' || $action === 'deny') {
    messaging_upsert_ok_permission((int)$user['id'], (int)$account['id'], $integration, 'denied');
    messaging_sync_ok_account_allowed((int)$account['id']);
    json_response($result + ['status' => 'denied']);
}

$existing = messaging_ok_permission_for_user((int)$user['id'], (string)$integration['external_id']);
if ($action === 'allow') {
    messaging_upsert_ok_permission((int)$user['id'], (int)$account['id'], $integration, 'allowed');
    messaging_sync_ok_account_allowed((int)$account['id']);
    json_response($result + ['status' => 'allowed']);
}

if (($existing['status'] ?? '') === 'allowed') {
    json_response($result + ['status' => 'allowed']);
}

messaging_upsert_ok_permission((int)$user['id'], (int)$account['id'], $integration, 'pending');
messaging_sync_ok_account_allowed((int)$account['id']);
json_response($result + ['status' => 'pending']);
