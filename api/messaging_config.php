<?php

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../admin/app/core/social_messaging.php';

$user = require_platform_user();
$managerId = !empty($user['manager_id']) ? (int)$user['manager_id'] : null;
$resellerId = !empty($user['reseller_id']) ? (int)$user['reseller_id'] : null;

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
}

json_response(['integrations' => $items]);
