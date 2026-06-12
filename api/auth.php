<?php

require __DIR__ . '/bootstrap.php';

$data = input_json() ?: $_POST;
$user = create_or_get_user($data);

json_response([
    'user' => $user,
    'default_manager' => default_manager_card($user['platform'] ?? ($data['platform'] ?? null)),
]);
