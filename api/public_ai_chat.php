<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../admin/app/core/ai_center.php';
require_once __DIR__ . '/../admin/app/core/consultant_profiles.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'method not allowed'], 405);
}

$data = input_json();
$referralCode = normalize_referral_code((string)($data['referral_code'] ?? ''));
$platformUserId = trim((string)($data['platform_user_id'] ?? ''));
$message = trim((string)($data['message'] ?? ''));

if (!$referralCode || !$platformUserId || !preg_match('/^web-[a-zA-Z0-9-]{8,80}$/', $platformUserId)) {
    json_response(['ok' => false, 'error' => 'Не удалось определить публичную страницу.'], 422);
}
if ($message === '' || mb_strlen($message, 'UTF-8') > 1000) {
    json_response(['ok' => false, 'error' => $message === '' ? 'Напишите вопрос.' : 'Сократите вопрос до 1000 символов.'], 422);
}

$binding = referral_binding($referralCode);
$profile = consultant_profile_by_referral_code($referralCode);
if (!$binding || !$profile || (int)($profile['is_public'] ?? 0) !== 1 || billing_profile_is_blocked($profile)) {
    json_response(['ok' => false, 'error' => 'Страница консультанта сейчас недоступна.'], 404);
}

$plan = billing_plan_for_reseller_branch((int)$binding['reseller_id']);
if (!ai_enabled() || !$plan || (int)($plan['is_active'] ?? 0) !== 1 || (int)($plan['ai_text_enabled'] ?? 0) !== 1) {
    json_response(['ok' => false, 'error' => 'ИИ-помощник не входит в текущий тариф консультанта.'], 403);
}

try {
    $user = create_or_get_user([
        'platform' => 'web',
        'platform_user_id' => $platformUserId,
        'referral_code' => $referralCode,
    ]);
    if (!empty($user['staff_preview'])) {
        json_response(['ok' => false, 'error' => 'Для проверки клиентского помощника откройте страницу в обычном браузере.'], 403);
    }

    $sameOwner = (int)($user['reseller_id'] ?? 0) === (int)$binding['reseller_id']
        && (int)($user['manager_id'] ?? 0) === (int)($binding['manager_id'] ?? 0);
    if (!$sameOwner) {
        json_response(['ok' => false, 'error' => 'Этот браузер уже закреплён за другим консультантом. Откройте свой кабинет или свяжитесь с консультантом напрямую.'], 409);
    }
    enforce_workspace_access($user);

    $rate = db()->prepare(
        'SELECT COUNT(*) FROM ai_messages am
         JOIN ai_conversations ac ON ac.id = am.conversation_id
         WHERE ac.actor_type = "client" AND ac.end_user_id = :user_id
           AND ac.channel = "public-site" AND am.role = "user"
           AND am.created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)'
    );
    $rate->execute(['user_id' => (int)$user['id']]);
    if ((int)$rate->fetchColumn() >= 8) {
        json_response(['ok' => false, 'error' => 'Слишком много вопросов подряд. Подождите минуту и продолжите.'], 429);
    }

    $result = ai_answer($message, 'client', $user, 'public-site', 'public-consultant-page');
    json_response($result, $result['ok'] ? 200 : 422);
} catch (Throwable $error) {
    error_log('SWPro public AI assistant: ' . $error->getMessage());
    json_response(['ok' => false, 'error' => 'Помощник временно недоступен. Попробуйте немного позже.'], 500);
}
