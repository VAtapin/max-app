<?php

require_once __DIR__ . '/../app/core/ai_content_governance.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

try {
    echo json_encode(ai_docs_sync(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $error) {
    if (str_contains($error->getMessage(), 'выключена')) {
        echo json_encode(['skipped' => true, 'reason' => $error->getMessage()], JSON_UNESCAPED_UNICODE) . PHP_EOL;
        exit(0);
    }
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
