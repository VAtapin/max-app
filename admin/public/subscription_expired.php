<?php

require_once __DIR__ . '/../app/core/auth.php';

$admin = current_admin();
if (!$admin) {
    redirect('login.php');
}
if (!admin_subscription_restricted($admin)) {
    redirect('index.php');
}

$title = 'Доступ приостановлен';
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Доступ приостановлен — SWPro</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
<main class="login-card">
    <h1>Доступ приостановлен</h1>
    <p>Срок оплаченного доступа кабинета лидера завершён или подписка приостановлена.</p>
    <p>Данные команды и клиентов сохранены. Для продления обратитесь к администратору SWPro.</p>
    <a class="button secondary-button" href="logout.php">Выйти</a>
</main>
</body>
</html>
