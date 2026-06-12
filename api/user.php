<?php

require __DIR__ . '/bootstrap.php';

$user = require_platform_user();
$stmt = db()->prepare(
    'SELECT platform, platform_user_id, username, first_name, last_name, display_name, created_at
     FROM platform_accounts
     WHERE end_user_id = :end_user_id
     ORDER BY FIELD(platform, "telegram", "VK", "OK", "MAX", "web"), id'
);
$stmt->execute(['end_user_id' => $user['id']]);

json_response([
    'user' => $user,
    'platform_accounts' => $stmt->fetchAll(),
    'disclaimer' => medical_disclaimer(),
]);
