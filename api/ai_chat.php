<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../admin/app/core/ai_center.php';
require_once __DIR__ . '/../admin/app/core/client_journey.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'method not allowed'], 405);
}

$data = input_json();
$user = require_platform_user($data);
$onboarding = client_onboarding_status($user);
if (empty($onboarding['complete'])) {
    json_response(['ok' => false, 'error' => 'onboarding_required'], 403);
}

try {
    $result = ai_answer(
        (string)($data['message'] ?? ''),
        'client',
        $user,
        normalize_platform((string)($user['current_platform'] ?? $user['platform'] ?? 'web')),
        'client-mini-app'
    );
    json_response($result, $result['ok'] ? 200 : 422);
} catch (Throwable) {
    json_response(['ok' => false, 'error' => 'Помощник временно недоступен.'], 500);
}
