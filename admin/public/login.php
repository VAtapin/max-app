<?php

require_once __DIR__ . '/../app/core/auth.php';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if (login_admin($email, $password)) {
        redirect('index.php');
    }

    $error = 'Неверный email или пароль.';
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Вход</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <form method="post" class="login-card">
        <h1>Вход в админку</h1>
        <?php if ($error): ?>
            <div class="alert"><?= h($error) ?></div>
        <?php endif; ?>
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <label>Email</label>
        <input type="email" name="email" value="admin@example.com" required>
        <label>Пароль</label>
        <input type="password" name="password" required>
        <button type="submit">Войти</button>
    </form>
</body>
</html>
