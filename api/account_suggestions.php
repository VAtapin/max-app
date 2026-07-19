<?php

require __DIR__ . '/bootstrap.php';

$data = input_json() ?: $_POST;
$user = require_platform_user($data);
$suggestions = find_similar_account_suggestions($user);

$payload = [
    'suggestions' => $suggestions,
];

if ($suggestions) {
    $payload['linking'] = account_link_payload($user);
}

json_response($payload);
