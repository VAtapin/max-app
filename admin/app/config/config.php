<?php

$config = [
    'app' => [
        'name' => 'Health Sales Support',
        'base_url' => '/admin/public',
        'session_name' => 'health_admin_session',
    ],
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'database' => getenv('DB_DATABASE') ?: 'health_sales_system',
        'username' => getenv('DB_USERNAME') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
        'charset' => 'utf8mb4',
    ],
    'security' => [
        'upload_max_bytes' => 5 * 1024 * 1024,
        'allowed_image_types' => ['image/jpeg', 'image/png', 'image/webp'],
    ],
];

$localConfig = __DIR__ . '/local.php';
if (is_file($localConfig)) {
    $config = array_replace_recursive($config, require $localConfig);
}

return $config;
