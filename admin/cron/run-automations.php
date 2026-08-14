<?php

require_once __DIR__ . '/../app/core/automation_runner.php';
require_once __DIR__ . '/../app/core/web_user_cleanup.php';
require_once __DIR__ . '/../app/core/ai_workflows.php';
require_once __DIR__ . '/../app/core/ai_jobs.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$config = app_config();
date_default_timezone_set((string)($config['app']['automation_timezone'] ?? 'Europe/Moscow'));
$result = run_client_automations();
$result['web_user_cleanup'] = cleanup_expired_web_users();
$result['ai_actions'] = ai_workflow_refresh_all_actions();
$result['ai_video_jobs'] = ai_poll_video_jobs();
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
