<?php

require_once __DIR__ . '/../app/core/db.php';
require_once __DIR__ . '/../app/core/workspace_billing.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

$sync = billing_sync_all_workspaces();
$result = billing_generate_actual_invoices();
billing_refresh_statuses();

echo json_encode(['synced' => $sync, 'invoices' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
