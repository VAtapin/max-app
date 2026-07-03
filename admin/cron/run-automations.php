<?php

require_once __DIR__ . '/../app/core/automation_runner.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$config = app_config();
date_default_timezone_set((string)($config['app']['automation_timezone'] ?? 'Europe/Moscow'));
$result = run_client_automations();
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
