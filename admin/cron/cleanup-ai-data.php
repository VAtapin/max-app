<?php

require_once __DIR__ . '/../app/core/ai_content_governance.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$dryRun = in_array('--dry-run', $argv ?? [], true);
echo json_encode(ai_cleanup_data($dryRun), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
