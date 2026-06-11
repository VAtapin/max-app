<?php

require __DIR__ . '/bootstrap.php';

$data = input_json() ?: $_POST;

$logDir = __DIR__ . '/../storage/logs';

if (!is_dir($logDir)) {
    mkdir($logDir, 0775, true);
}

file_put_contents(
    $logDir . '/auth_payload.log',
    date('Y-m-d H:i:s') . "\n" .
    json_encode([
        'get' => $_GET,
        'post' => $_POST,
        'input' => $data,
        'server' => [
            'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'HTTP_REFERER' => $_SERVER['HTTP_REFERER'] ?? null,
            'HTTP_ORIGIN' => $_SERVER['HTTP_ORIGIN'] ?? null,
            'QUERY_STRING' => $_SERVER['QUERY_STRING'] ?? null,
            'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? null,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) .
    "\n\n------------------------\n\n",
    FILE_APPEND
);


$user = create_or_get_user($data);

json_response(['user' => $user]);
