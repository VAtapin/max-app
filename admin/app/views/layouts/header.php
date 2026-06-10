<?php $config = app_config(); ?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title ?? $config['app']['name']) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="brand"><?= h($config['app']['name']) ?></div>
        <nav>
            <a href="index.php">Dashboard</a>
            <a href="crud.php?module=resellers">Реселлеры</a>
            <a href="crud.php?module=managers">Менеджеры</a>
            <a href="crud.php?module=users">Пользователи</a>
            <a href="crud.php?module=leads">Лиды</a>
            <a href="crud.php?module=categories">Категории</a>
            <a href="crud.php?module=products">Продукты</a>
            <a href="crud.php?module=tests">Тесты</a>
            <a href="crud.php?module=broadcasts">Рассылки</a>
            <a href="crud.php?module=content">Контент</a>
        </nav>
    </aside>
    <main class="main">
        <header class="topbar">
            <div><?= h($title ?? 'Dashboard') ?></div>
            <div class="topbar-user">
                <?= h($admin['name'] ?? '') ?>
                <a href="logout.php">Выйти</a>
            </div>
        </header>
        <section class="content">
