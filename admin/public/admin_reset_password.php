<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/admin_password_reset.php';

$error = null;
$success = null;
$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$payload = null;

if ($token !== '') {
    $payload = admin_password_reset_token_data($token);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');
    if ($token === '') {
        $error = 'Ссылка не указана.';
    } elseif ($password === '' || $confirm === '') {
        $error = 'Укажите пароль и подтверждение.';
    } elseif ($password !== $confirm) {
        $error = 'Пароли не совпадают.';
    } elseif (strlen($password) < 8) {
        $error = 'Пароль должен быть не короче 8 символов.';
    } elseif (!admin_password_reset_apply($token, $password)) {
        $error = 'Ссылка уже истекла или была использована.';
    } else {
        redirect('login.php?status=reset');
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Новый пароль</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="theme-color" content="#0b4f86">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= (int)filemtime(__DIR__ . '/assets/css/style.css') ?>">
</head>
<body class="login-page">
    <form method="post" class="login-card">
        <h1>Установка нового пароля</h1>
        <?php if ($payload === null && $error === null): ?>
            <div class="alert">Ссылка недействительна или устарела. Запросите новую.</div>
        <?php endif; ?>
        <?php if ($error !== null): ?>
            <div class="alert"><?= h($error) ?></div>
        <?php endif; ?>
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="token" value="<?= h($token) ?>">
        <label>Новый пароль</label>
        <input type="password" name="password" required autocomplete="new-password">
        <label>Подтвердите пароль</label>
        <input type="password" name="confirm_password" required autocomplete="new-password">
        <button type="submit" <?= $payload === null ? 'disabled' : '' ?>>Сохранить пароль</button>
        <p class="cell-muted"><a href="login.php">Вернуться ко входу</a></p>
    </form>
</body>
</html>
