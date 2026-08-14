<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/ai_center.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$admin = require_auth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

verify_csrf();

try {
    $result = ai_answer(
        (string)($_POST['message'] ?? ''),
        'admin',
        $admin,
        'admin',
        trim((string)($_POST['page_context'] ?? '')) ?: null
    );
    http_response_code($result['ok'] ? 200 : 422);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Помощник временно недоступен. Проверьте миграции и настройки.'], JSON_UNESCAPED_UNICODE);
}

