<?php

require_once __DIR__ . '/../app/core/web_user_cleanup.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$dryRun = in_array('--dry-run', $argv ?? [], true);
echo json_encode(
    cleanup_expired_web_users(500, $dryRun),
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
) . PHP_EOL;
