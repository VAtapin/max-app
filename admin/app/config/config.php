<?php

$projectRoot = dirname(__DIR__, 3);
$envFile = (string)(getenv('SWPRO_ENV_FILE') ?: $projectRoot . '/deploy/plesk/live.env');
$fileEnv = [];

if (is_file($envFile)) {
    $parsedEnv = parse_ini_file($envFile, false, INI_SCANNER_RAW);
    if ($parsedEnv === false) {
        throw new RuntimeException('Cannot parse the application configuration: ' . $envFile);
    }
    $fileEnv = $parsedEnv;
}

$env = static function (string $key, ?string $default = null) use ($fileEnv): ?string {
    if (array_key_exists($key, $fileEnv)) {
        return (string)$fileEnv[$key];
    }

    $processValue = getenv($key);
    if ($processValue !== false) {
        return (string)$processValue;
    }

    return $default;
};

foreach ($fileEnv as $key => $value) {
    putenv($key . '=' . $value);
    $_ENV[$key] = $value;
}

$requiredKeys = [
    'DB_HOST',
    'DB_PORT',
    'DB_DATABASE',
    'DB_USERNAME',
    'DB_PASSWORD',
    'SWPRO_PUBLIC_URL',
    'SWPRO_MINI_APP_URL',
];
foreach ($requiredKeys as $requiredKey) {
    if ($env($requiredKey) === null || $env($requiredKey) === '') {
        throw new RuntimeException(
            sprintf('Missing %s in the application configuration: %s', $requiredKey, $envFile)
        );
    }
}

return [
    'app' => [
        'name' => 'SWPro',
        'base_url' => '/admin/public',
        'public_url' => rtrim((string)$env('SWPRO_PUBLIC_URL'), '/'),
        'session_name' => 'health_admin_session',
        'automation_timezone' => $env('AUTOMATION_TIMEZONE', 'Europe/Moscow'),
    ],
    'db' => [
        'host' => $env('DB_HOST'),
        'port' => $env('DB_PORT'),
        'database' => $env('DB_DATABASE'),
        'username' => $env('DB_USERNAME'),
        'password' => $env('DB_PASSWORD'),
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
        'telegram_bot_token' => $env('TELEGRAM_BOT_TOKEN', ''),
        'telegram_oidc_client_id' => $env('TELEGRAM_OIDC_CLIENT_ID', ''),
        'telegram_oidc_client_secret' => $env('TELEGRAM_OIDC_CLIENT_SECRET', ''),
        'telegram_oidc_redirect_uri' => $env('TELEGRAM_OIDC_REDIRECT_URI', ''),
        'telegram_bot_username' => $env('TELEGRAM_BOT_USERNAME', 'SWProAssistant_bot'),
        'mini_app_url' => $env('SWPRO_MINI_APP_URL'),
        'vk_app_id' => $env('VK_APP_ID', ''),
        'ok_app_id' => $env('OK_APP_ID', '512004501421'),
        'vk_secure_key' => $env('VK_SECURE_KEY', ''),
        'vk_service_token' => $env('VK_SERVICE_TOKEN', ''),
    ],
];
