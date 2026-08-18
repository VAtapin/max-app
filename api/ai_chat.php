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
    $question = trim((string)($data['message'] ?? ''));
    $owner = ai_owner_for_client($user);
    $profile = ai_profile_for_owner($owner);
    $normalizedQuestion = mb_strtolower($question, 'UTF-8');

    // Consultant identity is account context, not a knowledge-base article.
    // Answer identity questions directly from the consultant profile so retrieval
    // cannot accidentally omit the consultant's name.
    if ($profile && preg_match('/(?:как\s+(?:зовут|звать|имя)|кто\s+(?:мой|моя)\s+консультант|имя\s+(?:моего|моей)\s+консультант)/u', $normalizedQuestion)) {
        $name = trim((string)($profile['display_name'] ?? ''));
        $subtitle = trim((string)($profile['short_description'] ?? $profile['specialization'] ?? ''));
        if ($name !== '') {
            $answer = 'Ваш консультант — ' . $name . ($subtitle !== '' ? '. ' . $subtitle : '.');
            json_response([
                'ok' => true,
                'answer' => $answer,
                'citations' => [],
                'safety_status' => 'ok',
                'provider' => 'swpro-profile',
            ]);
        }
    }

    $result = ai_answer(
        $question,
        'client',
        $user,
        normalize_platform((string)($user['current_platform'] ?? $user['platform'] ?? 'web')),
        'client-mini-app'
    );
    json_response($result, $result['ok'] ? 200 : 422);
} catch (Throwable) {
    json_response(['ok' => false, 'error' => 'Помощник временно недоступен.'], 500);
}
