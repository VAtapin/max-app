<?php

require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'method not allowed'], 405);
}

$user = require_platform_user();
$token = create_account_link_token((int)$user['id']);
$config = app_config();
$miniAppUrl = (string)($config['integrations']['mini_app_url'] ?? getenv('SWPRO_MINI_APP_URL') ?: '');
$telegramBot = (string)(getenv('TELEGRAM_BOT_USERNAME') ?: 'SWProAssistant_bot');

json_response([
    'token' => $token,
    'expires_in' => 900,
    'links' => [
        'mini_app' => $miniAppUrl ? $miniAppUrl . (str_contains($miniAppUrl, '?') ? '&' : '?') . 'link_token=' . rawurlencode($token) : '',
        'telegram' => 'https://t.me/' . rawurlencode($telegramBot) . '?start=' . rawurlencode('link_' . $token),
    ],
]);
