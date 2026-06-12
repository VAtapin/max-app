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
                'dashboard' => [app_text('auto.dashboard'), 'index.php'],
                'my_page' => [app_text('consultant_profile.menu'), 'my_page.php'],
                'resellers' => [app_text('auto.k_32cea47742bf'), 'crud.php?module=resellers'],
                'managers' => [app_text('auto.k_6756aa53b5b5'), 'crud.php?module=managers'],
                'users' => [app_text('auto.k_0f0b8f55edcc'), 'crud.php?module=users'],
                'platform_accounts' => [app_text('auto.k_68a410fd6049'), 'crud.php?module=platform_accounts'],
                'leads' => [app_text('auto.k_be11d71726a6'), 'crud.php?module=leads'],
                'categories' => [app_text('auto.k_f7d9b1c868fa'), 'crud.php?module=categories'],
                'products' => [app_text('auto.k_c85756a1ae45'), 'crud.php?module=products'],
                'tests' => [app_text('auto.k_663c94d30018'), 'crud.php?module=tests'],
                'broadcasts' => [app_text('auto.k_08a679f215bd'), 'crud.php?module=broadcasts'],
                'content' => [app_text('auto.k_5e30f01694b5'), 'crud.php?module=content'],
                'integrations' => [app_text('integrations.title'), 'crud.php?module=integrations'],
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
            <div><?= h($title ?? app_text('auto.dashboard')) ?></div>
            <div class="topbar-user">
                <?= h($admin['name'] ?? '') ?>
                <a href="logout.php"><?= h(app_text('auto.k_026abb1e0a5e')) ?></a>
            </div>
        </header>
        <section class="content">
