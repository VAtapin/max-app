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
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="theme-color" content="#0b4f86">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= (int)filemtime(__DIR__ . '/../../../public/assets/css/style.css') ?>">
</head>
<body>
<div class="app-shell">
    <input type="checkbox" id="mobile-nav-toggle" class="mobile-nav-toggle" aria-hidden="true">
    <label class="mobile-nav-backdrop" for="mobile-nav-toggle" aria-label="Закрыть меню"></label>
    <aside class="sidebar">
        <div class="brand">
            <div>
                <span><?= h($config['app']['name']) ?></span>
                <small>SWPro</small>
            </div>
            <label class="mobile-nav-close" for="mobile-nav-toggle" aria-label="Закрыть меню">×</label>
        </div>
        <div class="sidebar-mobile-user">
            <span class="user-chip"><?= h($admin['name'] ?? '') ?></span>
            <a href="logout.php"><?= h(app_text('auto.k_026abb1e0a5e')) ?></a>
        </div>
        <nav>
            <?php
            $navSections = [
                'Работа' => [
                    'dashboard' => [app_text('auto.dashboard'), 'index.php'],
                    'account' => ['Мои данные', 'account.php'],
                    'my_page' => ['Мой мини-сайт', 'my_page.php'],
                ],
                'Команда' => [
                    'resellers' => ['Лидеры', 'crud.php?module=resellers'],
                    'managers' => ['Консультанты', 'crud.php?module=managers'],
                    'users' => ['Клиенты', 'crud.php?module=users'],
                    'results' => ['Результаты чек-апов', 'results.php'],
                    'leads' => ['Обращения', 'crud.php?module=leads'],
                    'subscriptions' => ['Оплата лидеров', 'subscriptions.php'],
                ],
                'Контент' => [
                    'categories' => ['Категории продуктов', 'crud.php?module=categories'],
                    'products' => ['Продукты', 'crud.php?module=products'],
                    'tests' => [app_text('auto.k_663c94d30018'), 'crud.php?module=tests'],
                    'broadcasts' => ['Рассылки', 'crud.php?module=broadcasts'],
                    'content' => ['Материалы сайта', 'crud.php?module=content'],
                ],
                'Настройки' => [
                    'admin_accounts' => ['Пользователи админки', 'admin_accounts.php'],
                    'integrations' => ['Подключения', 'crud.php?module=integrations'],
                    'legal_documents' => ['Документы', 'crud.php?module=legal_documents'],
                    'legal_settings' => ['Реквизиты документов', 'legal_settings.php'],
                    'help' => [app_text('help.menu'), 'help.php'],
                ],
            ];
            ?>
            <?php foreach ($navSections as $sectionLabel => $navItems): ?>
                <?php
                $visibleItems = array_filter(
                    $navItems,
                    static fn($item, $module) => $module === 'help' || can_manage((string)$module, $admin),
                    ARRAY_FILTER_USE_BOTH
                );
                ?>
                <?php if ($visibleItems): ?>
                    <div class="nav-section">
                        <span class="nav-heading"><?= h($sectionLabel) ?></span>
                    <?php foreach ($visibleItems as $module => [$label, $href]): ?>
                    <?php
                    $hrefPath = basename((string)parse_url($href, PHP_URL_PATH));
                    $hrefQuery = [];
                    parse_str((string)parse_url($href, PHP_URL_QUERY), $hrefQuery);
                    $isActive = $currentPath === $hrefPath
                        && (($hrefQuery['module'] ?? null) === ($currentQuery['module'] ?? null)
                            || (!isset($hrefQuery['module']) && !isset($currentQuery['module'])));
                    ?>
                    <a href="<?= h($href) ?>" class="<?= $isActive ? 'active' : '' ?>"><?= h($label) ?></a>
                    <?php endforeach; ?>
                    </div>
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
            <div class="topbar-right">
                <div class="topbar-user">
                    <a class="user-chip" href="account.php"><?= h($admin['name'] ?? '') ?></a>
                    <a href="logout.php"><?= h(app_text('auto.k_026abb1e0a5e')) ?></a>
                </div>
                <label class="mobile-menu-button" for="mobile-nav-toggle" aria-label="Открыть меню">
                    <span></span>
                    <span></span>
                    <span></span>
                </label>
            </div>
        </header>
        <section class="content">
