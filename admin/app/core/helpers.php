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
            exit('Файл или форма слишком большие для текущих настроек PHP/Plesk. Увеличьте upload_max_filesize и post_max_size или загрузите файл меньшего размера.');
        }

        http_response_code(419);
        exit('Сессия формы устарела или токен безопасности не был передан. Обновите страницу и попробуйте снова.');
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

function medical_disclaimer(): string
{
    return 'Информация носит ознакомительный характер и не является медицинской рекомендацией. Перед применением продуктов проконсультируйтесь со специалистом.';
}
