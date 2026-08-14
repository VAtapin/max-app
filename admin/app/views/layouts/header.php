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
                ],
                'Подписка' => [
                    'billing_self' => ['Моя подписка', 'billing.php'],
                    'subscriptions' => ['Тарифы', 'subscriptions.php'],
                    'accounting' => ['Бухгалтерия', 'accounting.php'],
                    'payment_methods' => ['Методы оплаты', 'payment_methods.php'],
                ],
                'Контент' => [
                    'categories' => ['Категории продуктов', 'crud.php?module=categories'],
                    'products' => ['Продукты', 'crud.php?module=products'],
                    'tests' => [app_text('auto.k_663c94d30018'), 'crud.php?module=tests'],
                    'broadcasts' => ['Рассылки', 'crud.php?module=broadcasts'],
                    'content' => ['Материалы сайта', 'crud.php?module=content'],
                    'site_templates' => ['Шаблоны мини-сайта', 'crud.php?module=site_templates'],
                ],
                'Настройки' => [
                    'admin_accounts' => ['Пользователи админки', 'admin_accounts.php'],
                    'integrations' => ['Подключения', 'crud.php?module=integrations'],
                    'legal_documents' => ['Документы', 'crud.php?module=legal_documents'],
                    'legal_settings' => ['Реквизиты документов', 'legal_settings.php'],
                    'help' => [app_text('help.menu'), 'help.php'],
                ],
            ];
            $navIcons = [
                'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
                'account' => '<circle cx="12" cy="8" r="4"/><path d="M4.5 21a7.5 7.5 0 0 1 15 0"/>',
                'my_page' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
                'resellers' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
                'managers' => '<path d="M15 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8" cy="7" r="4"/><path d="m17 11 2 2 4-4"/>',
                'users' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
                'results' => '<rect x="3" y="3" width="18" height="18" rx="4"/><path d="m7 15 3-3 2 2 5-6"/>',
                'leads' => '<path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/><path d="M8 9h8M8 13h5"/>',
                'billing_self' => '<rect x="2" y="5" width="20" height="15" rx="3"/><path d="M2 10h20M16 15h2"/>',
                'subscriptions' => '<path d="M20.6 13.6 11 23l-9-9V4h10z"/><circle cx="7" cy="9" r="1.5"/>',
                'accounting' => '<path d="M6 2h12v20l-3-2-3 2-3-2-3 2z"/><path d="M9 7h6M9 11h6M9 15h4"/>',
                'payment_methods' => '<rect x="2" y="5" width="20" height="14" rx="3"/><path d="M2 10h20M6 15h4"/>',
                'categories' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
                'products' => '<path d="M6 7h12l1 14H5z"/><path d="M9 7a3 3 0 0 1 6 0"/>',
                'tests' => '<rect x="4" y="3" width="16" height="18" rx="3"/><path d="M8 8h.01M11 8h5M8 13h.01M11 13h5M8 18h.01M11 18h5"/>',
                'broadcasts' => '<path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/>',
                'content' => '<path d="M6 2h9l5 5v15H6z"/><path d="M14 2v6h6M9 13h6M9 17h6"/>',
                'site_templates' => '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M3 9h18M9 9v12"/>',
                'admin_accounts' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12h6M12 9v6"/>',
                'integrations' => '<path d="M12 22v-5M9 8V2M15 8V2M6 8h12v3a6 6 0 0 1-12 0z"/>',
                'legal_documents' => '<path d="M6 2h9l5 5v15H6z"/><path d="M14 2v6h6M9 13h6M9 17h4"/>',
                'legal_settings' => '<path d="M3 21h18M5 21V9l7-5 7 5v12M9 21v-6h6v6"/>',
                'help' => '<circle cx="12" cy="12" r="10"/><path d="M9.5 9a2.7 2.7 0 1 1 4.7 1.8c-1.2 1-2.2 1.4-2.2 3.2M12 18h.01"/>',
            ];
            ?>
            <?php foreach ($navSections as $sectionLabel => $navItems): ?>
                <?php
                $visibleItems = array_filter(
                    $navItems,
                    static fn($item, $module) => ($module !== 'billing_self' || ($admin['role'] ?? '') !== 'superadmin')
                        && ($module === 'help' || can_manage((string)$module, $admin)),
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
                    <a href="<?= h($href) ?>" class="<?= $isActive ? 'active' : '' ?>">
                        <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><?= $navIcons[$module] ?? $navIcons['content'] ?></svg>
                        <span><?= h($label) ?></span>
                    </a>
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
