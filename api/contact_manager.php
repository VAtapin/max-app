<?php

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/lead_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'method not allowed'], 405);
}

$data = input_json() ?: $_POST;
$user = require_platform_user($data);
if (!client_onboarding_status($user)['complete']) {
    json_response(['error' => 'onboarding_required'], 403);
}
$leadId = create_lead_for_user($user, $data);

json_response(['lead_id' => $leadId, 'status' => 'new']);
