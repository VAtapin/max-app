<?php

require_once __DIR__ . '/../app/core/db.php';
require_once __DIR__ . '/../app/core/helpers.php';
require_once __DIR__ . '/../app/core/broadcast_runner.php';

$results = run_due_broadcasts();

echo json_encode([
    'ok' => true,
    'processed' => count($results),
    'results' => $results,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
