<?php

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/telegram_oidc_common.php';

header('Cache-Control: no-store');
telegram_oidc_session_start();
$result = $_SESSION['telegram_oidc_result'] ?? null;
unset($_SESSION['telegram_oidc_result']);
if (!is_array($result) || time() - (int)($result['created_at'] ?? 0) > 300) {
    json_response(['error' => 'telegram oidc session not found'], 404);
}
unset($result['created_at']);
json_response($result);
