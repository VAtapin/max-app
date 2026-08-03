<?php

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/telegram_oidc_common.php';

$config = app_config();
$clientId = trim((string)($config['integrations']['telegram_oidc_client_id'] ?? ''));
$clientSecret = trim((string)($config['integrations']['telegram_oidc_client_secret'] ?? ''));
if ($clientId === '' || $clientSecret === '') {
    json_response(['error' => 'telegram oidc is not configured'], 503);
}

telegram_oidc_session_start();
$state = oidc_base64url_encode(random_bytes(32));
$nonce = oidc_base64url_encode(random_bytes(32));
$verifier = oidc_base64url_encode(random_bytes(64));
$returnPage = (string)($_GET['return_page'] ?? '');
if (!in_array($returnPage, ['tests', 'cashback', 'contact', 'cooperation'], true)) {
    $returnPage = '';
}
$_SESSION['telegram_oidc'] = [
    'state' => $state,
    'nonce' => $nonce,
    'verifier' => $verifier,
    'referral_code' => trim((string)($_GET['ref'] ?? '')),
    'link_token' => trim((string)($_GET['link_token'] ?? '')),
    'return_page' => $returnPage,
    'test_id' => max(0, (int)($_GET['test_id'] ?? 0)),
    'material_id' => max(0, (int)($_GET['material_id'] ?? 0)),
    'created_at' => time(),
];

$params = [
    'response_type' => 'code',
    'client_id' => $clientId,
    'redirect_uri' => telegram_oidc_redirect_uri($config),
    'scope' => 'openid profile',
    'state' => $state,
    'nonce' => $nonce,
    'code_challenge' => oidc_base64url_encode(hash('sha256', $verifier, true)),
    'code_challenge_method' => 'S256',
];
header('Location: https://oauth.telegram.org/auth?' . http_build_query($params));
exit;
