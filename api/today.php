<?php

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../admin/app/core/client_journey.php';
require_once __DIR__ . '/../admin/app/core/ai_workflows.php';

$data = $_SERVER['REQUEST_METHOD'] === 'POST' ? (input_json() ?: $_POST) : null;
$user = require_platform_user($data);
$onboarding = client_onboarding_status($user);
if (empty($onboarding['complete']) || !empty($onboarding['web_merge_required'])) {
    json_response(['error' => 'onboarding_required'], 403);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemId = max(0, (int)($data['item_id'] ?? 0));
    $completed = !empty($data['completed']) ? 1 : 0;
    $stmt = db()->prepare(
        'UPDATE client_action_plan_items cpi
         INNER JOIN client_action_plans cap ON cap.id = cpi.plan_id
         SET cpi.is_completed = :completed,
             cpi.completed_at = IF(:completed_at = 1, NOW(), NULL)
         WHERE cpi.id = :item_id AND cap.end_user_id = :user_id'
    );
    $stmt->execute([
        'completed' => $completed,
        'completed_at' => $completed,
        'item_id' => $itemId,
        'user_id' => (int)$user['id'],
    ]);
    db()->prepare(
        'UPDATE client_action_plans cap
         SET cap.status = IF(
             EXISTS (SELECT 1 FROM client_action_plan_items pending WHERE pending.plan_id = cap.id AND pending.is_completed = 0),
             "active", "completed"
         )
         WHERE cap.end_user_id = :user_id AND cap.status = "active"'
    )->execute(['user_id' => (int)$user['id']]);
    if ($stmt->rowCount() === 0 && $itemId <= 0) {
        json_response(['error' => 'plan item not found'], 404);
    }
    json_response(['ok' => true] + ai_workflow_client_today($user));
}

json_response(['ok' => true] + ai_workflow_client_today($user));
