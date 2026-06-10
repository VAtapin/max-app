<?php

require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'method not allowed'], 405);
}

$data = input_json() ?: $_POST;
$user = require_platform_user();

$stmt = db()->prepare(
    'INSERT INTO leads (end_user_id, manager_id, reseller_id, product_id, source_platform, message)
     VALUES (:end_user_id, :manager_id, :reseller_id, :product_id, :source_platform, :message)'
);
$stmt->execute([
    'end_user_id' => $user['id'],
    'manager_id' => $user['manager_id'],
    'reseller_id' => $user['reseller_id'],
    'product_id' => isset($data['product_id']) ? (int)$data['product_id'] : null,
    'source_platform' => $user['platform'],
    'message' => $data['message'] ?? 'Пользователь запросил связь с менеджером.',
]);
$leadId = (int)db()->lastInsertId();

$log = db()->prepare(
    'INSERT INTO activity_logs (actor_type, actor_id, action, entity_type, entity_id, details)
     VALUES ("end_user", :actor_id, "create_lead", "leads", :entity_id, :details)'
);
$log->execute([
    'actor_id' => $user['id'],
    'entity_id' => $leadId,
    'details' => json_encode(['platform' => $user['platform']], JSON_UNESCAPED_UNICODE),
]);

json_response(['lead_id' => $leadId, 'status' => 'new']);
