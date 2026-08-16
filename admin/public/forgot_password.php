<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/admin_password_reset.php';

function admin_password_reset_result_text(array $result): string
{
    if (($result['status'] ?? '') !== 'ok') {
        return 'Не удалось отправить ссылку для восстановления пароля. Проверьте доступные способы связи или обратитесь к администратору.';
    }

    $channels = $result['channels'] ?? [];
    if (!$channels) {
        return 'Не удалось отправить ссылку для восстановления пароля. Проверьте доступные способы связи или обратитесь к администратору.';
    }

    $hasTelegram = in_array('telegram', $channels, true);
    $hasVk = in_array('vk', $channels, true);
    $hasEmail = in_array('email', $channels, true);
    $parts = [];
    if ($hasTelegram) {
        $parts[] = 'Telegram';
    }
    if ($hasVk) {
        $parts[] = 'VK';
    }

    $maskedEmail = $result['masked_email'] ?? '';
    if ($hasEmail && $maskedEmail === '') {
        $adminEmail = trim((string)($result['email'] ?? ''));
        if ($adminEmail !== '' && str_contains($adminEmail, '@')) {
            $parts[] = 'на email: ' . admin_password_reset_mask_email($adminEmail);
        }
    } elseif ($hasEmail) {
        $parts[] = 'на email: ' . $maskedEmail;
    }

    if (!$parts) {
        return 'Не удалось отправить ссылку для восстановления пароля. Проверьте доступные способы связи или обратитесь к администратору.';
    }

    if (count($parts) === 1) {
        if (str_starts_with((string)$parts[0], 'на email: ')) {
            return 'Ссылка для восстановления пароля отправлена на email: ' . str_replace('на email: ', '', $parts[0]);
        }

        return 'Ссылка для восстановления пароля отправлена в ' . $parts[0] . '.';
    }

    if (count($parts) === 2) {
        if (str_starts_with((string)$parts[0], 'на email: ') && str_starts_with((string)$parts[1], 'на email: ')) {
            return 'Ссылка для восстановления пароля отправлена на email: ' . str_replace('на email: ', '', $parts[0]);
        }
        if (str_starts_with((string)$parts[0], 'на email: ')) {
            return 'Ссылка для восстановления пароля отправлена на email: ' . str_replace('на email: ', '', $parts[0]) . ' и ' . $parts[1] . '.';
        }
        if (str_starts_with((string)$parts[1], 'на email: ')) {
            return 'Ссылка для восстановления пароля отправлена в ' . $parts[0] . ' и ' . $parts[1] . '.';
        }

        return 'Ссылка для восстановления пароля отправлена в ' . $parts[0] . ' и ' . $parts[1] . '.';
    }

    if (count($parts) === 3) {
        return 'Ссылка для восстановления пароля отправлена в ' . $parts[0] . ', ' . $parts[1] . ' и ' . $parts[2] . '.';
    }

    return 'Ссылка для восстановления пароля отправлена.';
}

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim((string)($_POST['email'] ?? ''));
    if ($email === '') {
        $error = 'Введите email.';
    } else {
        $result = admin_password_reset_request($email);
        if ($result === null) {
            $success = 'Если такой email есть в системе, мы отправили ссылку для смены пароля.';
        } elseif (is_array($result) && ($result['status'] ?? '') === 'ok') {
            $success = admin_password_reset_result_text($result);
        } else {
            $error = admin_password_reset_result_text([
                'status' => 'error',
                'error' => (string)($result['error'] ?? 'Не удалось отправить ссылку для восстановления пароля. Проверьте доступные способы связи или обратитесь к администратору.'),
            ]);
        }
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Забыли пароль</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="theme-color" content="#0b4f86">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= (int)filemtime(__DIR__ . '/assets/css/style.css') ?>">
</head>
<body class="login-page">
    <form method="post" class="login-card">
        <h1>Восстановление пароля</h1>
        <?php if ($error !== null): ?>
            <div class="alert"><?= h($error) ?></div>
        <?php endif; ?>
        <?php if ($success !== null): ?>
            <div class="notice success"><?= h($success) ?></div>
        <?php endif; ?>
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <label>Email</label>
        <input type="email" name="email" value="<?= h((string)($_POST['email'] ?? '')) ?>" required autofocus>
        <button type="submit">Отправить ссылку</button>
        <p class="cell-muted"><a href="login.php">Вернуться ко входу</a></p>
    </form>
</body>
</html>
