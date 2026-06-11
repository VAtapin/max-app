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
            <?php
            $navItems = [
                'dashboard' => ['Dashboard', 'index.php'],
                'resellers' => ['Реселлеры', 'crud.php?module=resellers'],
                'managers' => ['Менеджеры', 'crud.php?module=managers'],
                'users' => ['Пользователи', 'crud.php?module=users'],
                'platform_accounts' => ['Аккаунты платформ', 'crud.php?module=platform_accounts'],
                'leads' => ['Заявки', 'crud.php?module=leads'],
                'categories' => ['Категории продуктов', 'crud.php?module=categories'],
                'products' => ['Продукты', 'crud.php?module=products'],
                'tests' => ['Тесты', 'crud.php?module=tests'],
                'broadcasts' => ['Рассылки', 'crud.php?module=broadcasts'],
                'content' => ['Материалы', 'crud.php?module=content'],
            ];
            ?>
            <?php foreach ($navItems as $module => [$label, $href]): ?>
                <?php if (can_manage($module, $admin)): ?>
                    <a href="<?= h($href) ?>"><?= h($label) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
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
