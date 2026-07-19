<?php
$config = app_config();
$currentPath = basename((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
$currentQuery = [];
parse_str((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY), $currentQuery);
?>
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
        <div class="brand">
            <span><?= h($config['app']['name']) ?></span>
            <small>SWPro</small>
        </div>
        <nav>
            <?php
            $navItems = [
                'dashboard' => [app_text('auto.dashboard'), 'index.php'],
                'my_page' => ['Мой мини-сайт', 'my_page.php'],
                'resellers' => ['Лидеры', 'crud.php?module=resellers'],
                'managers' => ['Консультанты', 'crud.php?module=managers'],
                'users' => ['Клиенты', 'crud.php?module=users'],
                'results' => ['Результаты чек-апов', 'results.php'],
                'leads' => ['Обращения', 'crud.php?module=leads'],
                'categories' => ['Категории продуктов', 'crud.php?module=categories'],
                'products' => ['Продукты', 'crud.php?module=products'],
                'tests' => [app_text('auto.k_663c94d30018'), 'crud.php?module=tests'],
                'broadcasts' => ['Рассылки', 'crud.php?module=broadcasts'],
                'content' => ['Материалы сайта', 'crud.php?module=content'],
                'subscriptions' => ['Подписки лидеров', 'subscriptions.php'],
                'legal_documents' => ['Документы', 'crud.php?module=legal_documents'],
                'legal_settings' => ['Реквизиты документов', 'legal_settings.php'],
                'help' => [app_text('help.menu'), 'help.php'],
            ];
            ?>
            <?php foreach ($navItems as $module => [$label, $href]): ?>
                <?php if ($module === 'help' || can_manage($module, $admin)): ?>
                    <?php
                    $hrefPath = basename((string)parse_url($href, PHP_URL_PATH));
                    $hrefQuery = [];
                    parse_str((string)parse_url($href, PHP_URL_QUERY), $hrefQuery);
                    $isActive = $currentPath === $hrefPath
                        && (($hrefQuery['module'] ?? null) === ($currentQuery['module'] ?? null)
                            || (!isset($hrefQuery['module']) && !isset($currentQuery['module'])));
                    ?>
                    <a href="<?= h($href) ?>" class="<?= $isActive ? 'active' : '' ?>"><?= h($label) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    </aside>
    <main class="main">
        <header class="topbar">
            <div class="topbar-title">
                <span><?= h($title ?? app_text('auto.dashboard')) ?></span>
                <small><?= h($config['app']['name']) ?></small>
            </div>
            <div class="topbar-user">
                <span class="user-chip"><?= h($admin['name'] ?? '') ?></span>
                <a href="logout.php"><?= h(app_text('auto.k_026abb1e0a5e')) ?></a>
            </div>
        </header>
        <section class="content">
