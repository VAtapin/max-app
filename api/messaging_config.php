<?php

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../admin/app/core/social_messaging.php';

$user = require_platform_user();
$managerId = !empty($user['manager_id']) ? (int)$user['manager_id'] : null;
$resellerId = !empty($user['reseller_id']) ? (int)$user['reseller_id'] : null;
$okAppId = preg_replace('/\D+/', '', (string)(app_config()['integrations']['ok_app_id'] ?? '')) ?: '';

$items = [];
foreach (['VK', 'OK'] as $platform) {
    $integration = messaging_integration_for_owner($platform, $managerId, $resellerId);
    if (!$integration) {
        continue;
    }

    $externalId = trim((string)($integration['external_id'] ?? ''));
    if ($externalId === '') {
        continue;
    }

    $items[$platform] = [
        'platform' => $platform,
        'title' => (string)($integration['title'] ?? platform_label($platform)),
        'external_id' => $externalId,
    ];
    if ($platform === 'VK') {
        $permissionStmt = db()->prepare(
            'SELECT status, delivery_enabled
             FROM vk_message_permissions
             WHERE end_user_id = :end_user_id
               AND group_id = :group_id
             LIMIT 1'
        );
        $permissionStmt->execute([
            'end_user_id' => (int)$user['id'],
            'group_id' => $externalId,
        ]);
        $permission = $permissionStmt->fetch() ?: null;
        $items[$platform]['permission_status'] = $permission['status'] ?? null;
        $items[$platform]['delivery_enabled'] = $permission ? (bool)$permission['delivery_enabled'] : false;
    } elseif ($platform === 'OK') {
        $permissionStmt = db()->prepare(
            'SELECT status
             FROM ok_message_permissions
             WHERE end_user_id = :end_user_id
               AND group_id = :group_id
             LIMIT 1'
        );
        $permissionStmt->execute([
            'end_user_id' => (int)$user['id'],
            'group_id' => $externalId,
        ]);
        $items[$platform]['permission_status'] = $permissionStmt->fetchColumn() ?: null;
    }
}

json_response([
    'integrations' => $items,
    // The regular web site cannot invoke the native OK confirmation. It can
    // offer a safe return to the real OK Mini App instead of a fabricated
    // group-chat URL that asks an owner to join their own group.
    'ok_mini_app_url' => $okAppId !== '' ? 'https://ok.ru/app/' . $okAppId : null,
]);
