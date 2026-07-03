<?php

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../admin/app/core/client_journey.php';

$data = input_json() ?: $_POST;
$user = require_platform_user($data);
if (!client_onboarding_status($user)['complete']) {
    json_response(['error' => 'onboarding_required'], 403);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'mark_read') {
    $id = (int)($data['id'] ?? 0);
    $sql = 'UPDATE user_notifications SET is_read = 1, read_at = NOW() WHERE end_user_id = :end_user_id';
    $params = ['end_user_id' => $user['id']];
    if ($id > 0) {
        $sql .= ' AND id = :id';
        $params['id'] = $id;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    json_response(['ok' => true, 'updated' => $stmt->rowCount()]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['error' => 'method not allowed'], 405);
}

$stmt = db()->prepare(
    'SELECT id, notification_type, title, message_text, image_path, video_path,
            action_text, action_url, is_read, created_at
     FROM user_notifications
     WHERE end_user_id = :end_user_id
     ORDER BY is_read, id DESC
     LIMIT 20'
);
$stmt->execute(['end_user_id' => $user['id']]);

json_response([
    'notifications' => $stmt->fetchAll(),
]);
