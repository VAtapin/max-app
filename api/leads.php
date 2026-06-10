<?php

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/lead_service.php';

$user = require_platform_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = input_json() ?: $_POST;
    $leadId = create_lead_for_user($user, $data);
    json_response(['lead_id' => $leadId, 'status' => 'new'], 201);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['error' => 'method not allowed'], 405);
}

$stmt = db()->prepare(
    'SELECT l.*, p.title AS product_title
     FROM leads l
     LEFT JOIN products p ON p.id = l.product_id
     WHERE l.end_user_id = :end_user_id
     ORDER BY l.id DESC
     LIMIT 50'
);
$stmt->execute(['end_user_id' => $user['id']]);

json_response(['leads' => $stmt->fetchAll()]);
