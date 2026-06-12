<?php

require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'method not allowed'], 405);
}

$data = input_json() ?: $_POST;
$initData = (string)($data['init_data'] ?? '');

if ($initData === '') {
    json_response(['error' => 'init_data is required'], 422);
}

$config = app_config();
$botToken = $config['integrations']['telegram_bot_token'] ?? getenv('TELEGRAM_BOT_TOKEN') ?: '';
if ($botToken === '') {
    json_response(['error' => 'telegram bot token is not configured'], 500);
}

parse_str($initData, $parsed);
$receivedHash = $parsed['hash'] ?? '';
if ($receivedHash === '') {
    json_response(['error' => 'telegram hash is missing'], 422);
}

$checkData = $parsed;
unset($checkData['hash']);
ksort($checkData, SORT_STRING);

$pairs = [];
foreach ($checkData as $key => $value) {
    $pairs[] = $key . '=' . $value;
}
$dataCheckString = implode("\n", $pairs);

$secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
$calculatedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

if (!hash_equals($calculatedHash, $receivedHash)) {
    json_response(['error' => 'telegram signature is invalid'], 403);
}

$authDate = isset($parsed['auth_date']) ? (int)$parsed['auth_date'] : 0;
if ($authDate <= 0 || $authDate < time() - 86400) {
    json_response(['error' => 'telegram init data is expired'], 403);
}

$telegramUser = json_decode((string)($parsed['user'] ?? ''), true);
if (!is_array($telegramUser) || empty($telegramUser['id'])) {
    json_response(['error' => 'telegram user is missing'], 422);
}

$startParam = (string)($parsed['start_param'] ?? '');
$linkToken = str_starts_with($startParam, 'link_') ? $startParam : ($data['link_token'] ?? null);
$referralCode = str_starts_with($startParam, 'link_') ? ($data['referral_code'] ?? null) : ($startParam ?: ($data['referral_code'] ?? null));

$user = create_or_get_user([
    'platform' => 'telegram',
    'platform_user_id' => (string)$telegramUser['id'],
    'username' => $telegramUser['username'] ?? null,
    'first_name' => $telegramUser['first_name'] ?? null,
    'last_name' => $telegramUser['last_name'] ?? null,
    'referral_code' => $referralCode,
    'link_token' => $linkToken,
]);

json_response([
    'user' => $user,
    'default_manager' => default_manager_card('telegram'),
    'auth' => [
        'platform' => 'telegram',
        'platform_user_id' => (string)$telegramUser['id'],
        'auth_token' => telegram_auth_token((string)$telegramUser['id']),
    ],
]);
