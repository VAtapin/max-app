<?php

function h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && $contentLength > 0) {
            http_response_code(413);
            exit(app_text('auto.k_0dfe59682af3'));
        }

        http_response_code(419);
        exit(app_text('auto.k_72041d871b15'));
    }
}

function app_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config/config.php';
    }

    return $config;
}


function app_translations(): array
{
    static $translations = null;
    if ($translations === null) {
        $translations = require __DIR__ . '/../lang/ru.php';
    }

    return $translations;
}

function t_choice(string $group, ?string $value): string
{
    $value = (string)$value;
    $translations = app_translations();
    return $translations[$group][$value] ?? $value;
}

function app_text(string $key, array $params = []): string
{
    $value = app_translations();
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $key;
        }
        $value = $value[$part];
    }

    if (!is_string($value)) {
        return $key;
    }

    foreach ($params as $name => $param) {
        $value = str_replace('{' . $name . '}', (string)$param, $value);
    }

    return $value;
}

function normalize_platform(?string $platform): string
{
    return match (strtolower((string)$platform)) {
        'vk' => 'VK',
        'ok' => 'OK',
        'max' => 'MAX',
        'telegram' => 'telegram',
        'web' => 'web',
        'all' => 'all',
        default => (string)$platform,
    };
}

function status_label(?string $value): string
{
    return t_choice('statuses', $value);
}

function platform_label(?string $value): string
{
    return t_choice('platforms', normalize_platform($value));
}

function target_label(?string $value): string
{
    return t_choice('targets', $value);
}

function medical_disclaimer(): string
{
    return app_text('auto.k_468e990acb20');
}
