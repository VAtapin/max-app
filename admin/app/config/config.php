<?php

$config = [
    'app' => [
        'name' => 'SWPro',
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
        'upload_max_bytes' => 0,
        'allowed_image_types' => ['image/jpeg', 'image/png', 'image/webp'],
        'allowed_attachment_types' => [
            'image/jpeg',
            'image/png',
            'image/webp',
            'application/pdf',
            'video/mp4',
        ],
    ],
    'integrations' => [
        'telegram_bot_token' => getenv('TELEGRAM_BOT_TOKEN') ?: '',
        'mini_app_url' => getenv('SWPRO_MINI_APP_URL') ?: '',
        'vk_app_id' => getenv('VK_APP_ID') ?: '',
        'vk_secure_key' => getenv('VK_SECURE_KEY') ?: '',
        'vk_service_token' => getenv('VK_SERVICE_TOKEN') ?: '',
        'vk_group_token' => getenv('VK_GROUP_TOKEN') ?: '',
    ],
];

$localConfig = __DIR__ . '/local.php';
if (is_file($localConfig)) {
    $config = array_replace_recursive($config, require $localConfig);
}

return $config;
