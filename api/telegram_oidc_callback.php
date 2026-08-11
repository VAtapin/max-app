<?php

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/telegram_oidc_common.php';

telegram_oidc_session_start();
$flow = $_SESSION['telegram_oidc'] ?? null;
unset($_SESSION['telegram_oidc']);

try {
    if (!is_array($flow)
        || time() - (int)($flow['created_at'] ?? 0) > 900
        || !hash_equals((string)($flow['state'] ?? ''), (string)($_GET['state'] ?? ''))
        || empty($_GET['code'])) {
        throw new RuntimeException('Telegram login session is invalid or expired');
    }

    $config = app_config();
    $clientId = trim((string)($config['integrations']['telegram_oidc_client_id'] ?? ''));
    $clientSecret = trim((string)($config['integrations']['telegram_oidc_client_secret'] ?? ''));
    $body = http_build_query([
        'grant_type' => 'authorization_code',
        'code' => (string)$_GET['code'],
        'redirect_uri' => telegram_oidc_redirect_uri($config),
        'code_verifier' => (string)$flow['verifier'],
    ]);
    $tokens = telegram_oidc_http_json('https://oauth.telegram.org/token', [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n"
            . 'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret) . "\r\n",
        'content' => $body,
    ]);
    $claims = telegram_oidc_verify_id_token(
        (string)($tokens['id_token'] ?? ''),
        $clientId,
        (string)$flow['nonce']
    );

    $name = trim((string)($claims['name'] ?? ''));
    $nameParts = preg_split('/\s+/u', $name, 2) ?: [];
    $firstName = trim((string)($claims['given_name'] ?? ($nameParts[0] ?? '')));
    $lastName = trim((string)($claims['family_name'] ?? ($nameParts[1] ?? '')));
    $telegramId = (string)($claims['id'] ?? $claims['sub'] ?? '');
    if ($telegramId === '') {
        throw new RuntimeException('Telegram user identifier is missing');
    }
    $user = create_or_get_user([
        'platform' => 'telegram',
        'platform_user_id' => $telegramId,
        'username' => $claims['preferred_username'] ?? null,
        'first_name' => $firstName !== '' ? $firstName : null,
        'last_name' => $lastName !== '' ? $lastName : null,
        'referral_code' => $flow['referral_code'] ?: null,
        'link_token' => $flow['link_token'] ?: null,
        'platform_verified' => true,
    ]);
    session_regenerate_id(true);
    $_SESSION['telegram_oidc_result'] = [
        'created_at' => time(),
        'user' => $user,
        'auth' => [
            'platform' => 'telegram',
            'platform_user_id' => $telegramId,
            'auth_token' => telegram_auth_token($telegramId),
        ],
    ];

    $redirectParams = ['oidc' => '1'];
    if (!empty($flow['return_page'])) {
        $redirectParams['page'] = (string)$flow['return_page'];
    }
    if (!empty($flow['test_id'])) {
        $redirectParams['test_id'] = (int)$flow['test_id'];
    }
    if (!empty($flow['material_id'])) {
        $redirectParams['material_id'] = (int)$flow['material_id'];
    }
    header(
        'Location: ' . rtrim((string)$config['app']['public_url'], '/')
        . '/vk-mini-app/?' . http_build_query($redirectParams)
    );
    exit;
} catch (Throwable $e) {
    http_response_code(400);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>Ошибка входа</title>'
        . '<p>Не удалось войти через Telegram: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><a href="/vk-mini-app/">Вернуться в SWPro</a></p>';
    exit;
}
