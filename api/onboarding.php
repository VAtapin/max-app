<?php

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../admin/app/core/client_journey.php';

$data = input_json() ?: $_POST;
$user = require_platform_user($data);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    json_response([
        'user' => $user,
        'onboarding' => client_onboarding_status($user),
        'gender_options' => client_gender_labels(),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'method not allowed'], 405);
}

$action = (string)($data['action'] ?? '');
$platform = (string)($user['platform'] ?? ($data['platform'] ?? 'web'));

if ($action === 'consent') {
    $types = $data['document_types'] ?? [];
    if (!is_array($types) || !$types) {
        json_response(['error' => 'document_types are required'], 422);
    }

    $metadata = [
        'user_agent' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
        'ip_hash' => hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? '')),
    ];
    foreach (array_values(array_unique(array_map('strval', $types))) as $type) {
        grant_user_consent((int)$user['id'], $type, $platform, $metadata);
    }

    $userStmt = db()->prepare('SELECT * FROM end_users WHERE id = :id LIMIT 1');
    $userStmt->execute(['id' => $user['id']]);
    $updated = $userStmt->fetch() ?: $user;
    json_response([
        'user' => $updated,
        'onboarding' => client_onboarding_status($updated),
    ]);
}

if ($action === 'profile') {
    $status = client_onboarding_status($user);
    if ($status['missing_consents']) {
        json_response(['error' => 'required consents are missing'], 422);
    }

    try {
        $updated = complete_client_onboarding((int)$user['id'], $data);
    } catch (InvalidArgumentException $e) {
        json_response(['error' => $e->getMessage()], 422);
    }

    json_response([
        'user' => $updated,
        'onboarding' => client_onboarding_status($updated),
    ]);
}

if ($action === 'revoke_marketing') {
    revoke_user_consents((int)$user['id'], 'marketing_consent');
    json_response(['ok' => true]);
}

if ($action === 'revoke_all') {
    revoke_user_consents((int)$user['id']);
    json_response(['ok' => true]);
}

json_response(['error' => 'unknown action'], 422);
