<?php

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/lead_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'method not allowed'], 405);
}

$data = input_json() ?: $_POST;
$user = require_platform_user();
$leadId = create_lead_for_user($user, $data);

json_response(['lead_id' => $leadId, 'status' => 'new']);
