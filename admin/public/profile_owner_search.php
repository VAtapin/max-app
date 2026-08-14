<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/permissions.php';
require_once __DIR__ . '/../app/core/consultant_profiles.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$admin = require_auth();
if (!can_manage('my_page', $admin) || ($admin['role'] ?? '') === 'manager') {
    http_response_code(403);
    echo json_encode(['error' => 'Поиск профилей недоступен.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$query = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100, 'UTF-8');
if (mb_strlen($query, 'UTF-8') < 2 && !preg_match('/^#?\d+$/', $query)) {
    echo json_encode(['options' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    echo json_encode([
        'options' => consultant_search_options_for_admin($admin, $query, 30),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    error_log('Profile owner search failed: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Поиск временно недоступен.'], JSON_UNESCAPED_UNICODE);
}
