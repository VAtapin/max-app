<?php

require_once __DIR__ . '/admin/app/core/db.php';
require_once __DIR__ . '/admin/app/core/helpers.php';
require_once __DIR__ . '/admin/app/core/consultant_profiles.php';

$slug = trim((string)($_GET['m'] ?? ''));
$referralCode = consultant_referral_code($_GET['ref'] ?? $_POST['ref'] ?? null);
$profile = null;

if ($slug !== '') {
    $stmt = db()->prepare('SELECT * FROM consultant_profiles WHERE slug = :slug AND is_public = 1 LIMIT 1');
    $stmt->execute(['slug' => consultant_slug($slug, $slug)]);
    $profile = $stmt->fetch();
}

if (!$profile && $referralCode) {
    $candidate = consultant_profile_by_referral_code($referralCode);
    if ($candidate && (int)$candidate['is_public'] === 1) {
        $profile = $candidate;
    }
}

$payload = $profile ? consultant_profile_payload($profile) : null;
$blocks = $payload['blocks'] ?? [];

function public_block_enabled(array $blocks, string $blockType): bool
{
    foreach ($blocks as $block) {
        if ($block['block_type'] === $blockType) {
            return (int)$block['is_enabled'] === 1;
        }
    }
    return true;
}

function public_block_title(array $blocks, string $blockType, string $fallback): string
{
    foreach ($blocks as $block) {
        if ($block['block_type'] === $blockType && trim((string)$block['title']) !== '') {
            return (string)$block['title'];
        }
    }
    return $fallback;
}

function public_contact_links(array $profile): array
{
    return array_filter([
        'Телефон' => $profile['phone'] ?? null,
        'Email' => !empty($profile['email']) ? 'mailto:' . $profile['email'] : null,
        'Telegram' => $profile['telegram_url'] ?? null,
        'WhatsApp' => $profile['whatsapp_url'] ?? null,
        'VK' => $profile['vk_url'] ?? null,
        'OK' => $profile['ok_url'] ?? null,
    ]);
}

function public_about_titles(array $blocks): array
{
    $titles = [
        'bio' => 'Обо мне',
        'specialization' => 'Специализация',
        'experience_text' => 'Опыт',
        'certificates_text' => 'Сертификаты',
        'achievements_text' => 'Достижения',
    ];

    foreach ($blocks as $block) {
        if (($block['block_type'] ?? '') !== 'about') {
            continue;
        }

        $settings = json_decode((string)($block['settings_json'] ?? ''), true);
        if (!is_array($settings) || empty($settings['titles']) || !is_array($settings['titles'])) {
            return $titles;
        }

        foreach ($titles as $key => $defaultTitle) {
            if (!empty($settings['titles'][$key])) {
                $titles[$key] = trim((string)$settings['titles'][$key]);
            }
        }
    }

    return $titles;
}

function public_about_sections(array $profile, array $blocks): array
{
    $titles = public_about_titles($blocks);
    $sections = [];

    foreach (['bio', 'specialization', 'experience_text', 'certificates_text', 'achievements_text'] as $field) {
        $text = trim((string)($profile[$field] ?? ''));
        if ($text !== '') {
            $sections[] = [
                'title' => $titles[$field],
                'text' => $text,
                'field' => $field,
            ];
        }
    }

    return $sections;
}

function public_youtube_embed_url(?string $url): ?string
{
    $url = trim((string)$url);
    if ($url === '') {
        return null;
    }

    $parts = parse_url($url);
    $host = strtolower((string)($parts['host'] ?? ''));
    $path = trim((string)($parts['path'] ?? ''), '/');
    $videoId = null;

    if (str_contains($host, 'youtu.be')) {
        $videoId = explode('/', $path)[0] ?? null;
    } elseif (str_contains($host, 'youtube.com')) {
        if ($path === 'watch') {
            parse_str((string)($parts['query'] ?? ''), $query);
            $videoId = $query['v'] ?? null;
        } elseif (str_starts_with($path, 'shorts/')) {
            $videoId = explode('/', substr($path, 7))[0] ?? null;
        } elseif (str_starts_with($path, 'embed/')) {
            $videoId = explode('/', substr($path, 6))[0] ?? null;
        }
    }

    if (!$videoId || !preg_match('/^[a-zA-Z0-9_-]{6,}$/', $videoId)) {
        return null;
    }

    return 'https://www.youtube.com/embed/' . rawurlencode($videoId);
}

function public_profile_referral_code(array $profile): ?string
{
    $ownerType = (string)($profile['owner_type'] ?? '');
    $ownerId = (int)($profile['owner_id'] ?? 0);
    if ($ownerId <= 0 || !in_array($ownerType, ['manager', 'reseller'], true)) {
        return null;
    }

    $table = $ownerType === 'manager' ? 'managers' : 'resellers';
    $stmt = db()->prepare("SELECT referral_code FROM {$table} WHERE id = :id AND is_active = 1 LIMIT 1");
    $stmt->execute(['id' => $ownerId]);
    $code = trim((string)($stmt->fetchColumn() ?: ''));

    return $code !== '' ? $code : null;
}

function public_mini_app_url(?string $referralCode = null, string $page = ''): string
{
    $config = app_config();
    $baseUrl = rtrim(public_base_url(), '/');
    $miniAppUrl = trim((string)($config['integrations']['mini_app_url'] ?? ''));
    if ($miniAppUrl === '' && $baseUrl !== '') {
        $miniAppUrl = $baseUrl . '/vk-mini-app/';
    }
    if ($miniAppUrl === '') {
        $miniAppUrl = '/vk-mini-app/';
    }

    $params = [];
    if ($referralCode) {
        $params['ref'] = $referralCode;
    }
    if ($page !== '') {
        $params['page'] = $page;
    }

    return $miniAppUrl . (str_contains($miniAppUrl, '?') ? '&' : '?') . http_build_query($params);
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($profile ? $profile['display_name'] . ' - SWPro' : 'SWPro') ?></title>
    <link rel="stylesheet" href="/public.css">
</head>
<body>
<?php if (!$profile): ?>
    <main class="landing-page">
        <header class="landing-topnav">
            <a class="brand-link" href="/">SWPro</a>
            <nav>
                <a href="#how">Как работает</a>
                <a href="#roles">Для кого</a>
                <a href="#start">Открыть страницу</a>
            </nav>
        </header>
        <section class="landing-hero">
            <div class="landing-copy">
                <span class="eyebrow">SWPro Assistant</span>
                <h1>Персональная страница консультанта для клиентов</h1>
                <p>Откройте страницу своего консультанта, пройдите рекомендованные тесты, получите материалы и задайте вопрос без интернет-магазина, корзины и оплаты.</p>
                <form method="post" class="find-form" id="start">
                    <input name="ref" placeholder="Код консультанта" required>
                    <button type="submit">Открыть страницу</button>
                </form>
                <div class="landing-badges">
                    <span>Без корзины</span>
                    <span>Без оплат</span>
                    <span>Через консультанта</span>
                </div>
            </div>
            <div class="landing-panel" id="how">
                <strong>Как это работает</strong>
                <ol>
                    <li>Консультант дает клиенту персональный код или ссылку.</li>
                    <li>Клиент открывает страницу и видит рекомендации своего консультанта.</li>
                    <li>Вопросы и заявки попадают менеджеру в SWPro.</li>
                </ol>
            </div>
        </section>
        <section class="landing-grid" id="roles">
            <article class="public-card">
                <span class="card-kicker">Для клиента</span>
                <strong>Все вокруг своего консультанта</strong>
                <p>Тесты, продукты, материалы и ответы собраны в одном месте и привязаны к персональному менеджеру.</p>
            </article>
            <article class="public-card">
                <span class="card-kicker">Для менеджера</span>
                <strong>Личная витрина вместо каталога</strong>
                <p>Можно вести клиентов через свою страницу, рекомендации, материалы и заявки.</p>
            </article>
            <article class="public-card">
                <span class="card-kicker">MVP</span>
                <strong>Без магазина и оплат</strong>
                <p>Система собирает интерес клиента и помогает консультанту продолжить общение.</p>
            </article>
        </section>
        <section class="landing-band">
            <div>
                <span class="eyebrow">Главная идея</span>
                <h2>SWPro показывает сначала человека, а потом продукты</h2>
            </div>
            <p>Клиент приходит не в каталог. Он открывает страницу своего консультанта, видит объяснение, материалы, тесты и может задать вопрос в удобном канале.</p>
        </section>
    </main>
<?php else: ?>
    <?php $profileData = $payload['profile']; ?>
    <?php $profileReferralCode = public_profile_referral_code($profileData); ?>
    <?php $miniAppUrl = public_mini_app_url($profileReferralCode); ?>
    <main class="consultant-page">
        <header class="public-topnav">
            <a class="brand-link" href="/">SWPro</a>
            <nav>
                <?php if (public_block_enabled($blocks, 'about') && public_about_sections($profileData, $blocks)): ?><a href="#about">О консультанте</a><?php endif; ?>
                <?php if (public_block_enabled($blocks, 'products') && !empty($payload['products'])): ?><a href="#products">Рекомендации</a><?php endif; ?>
                <?php if (public_block_enabled($blocks, 'tests') && !empty($payload['tests'])): ?><a href="#tests">Тесты</a><?php endif; ?>
                <?php if (public_block_enabled($blocks, 'materials') && !empty($payload['materials'])): ?><a href="#materials">Материалы</a><?php endif; ?>
                <a href="#contacts">Контакты</a>
            </nav>
            <a class="topnav-action" href="<?= h($miniAppUrl) ?>">Открыть Mini App</a>
        </header>
        <section class="hero">
            <div class="hero-photo-wrap">
                <?php if (!empty($profileData['photo_path'])): ?>
                    <img src="<?= h((string)$profileData['photo_path']) ?>" alt="" class="hero-photo">
                <?php else: ?>
                    <div class="hero-photo placeholder"><?= h(mb_substr((string)$profileData['display_name'], 0, 2, 'UTF-8')) ?></div>
                <?php endif; ?>
            </div>
            <div class="hero-text">
                <span class="eyebrow"><?= h((string)($profileData['title'] ?: 'SWPro')) ?></span>
                <h1><?= h((string)$profileData['display_name']) ?></h1>
                <p class="subtitle"><?= h((string)$profileData['subtitle']) ?></p>
                <?php if (!empty($profileData['short_description'])): ?>
                    <p><?= h((string)$profileData['short_description']) ?></p>
                <?php endif; ?>
                <div class="hero-actions">
                    <a class="primary" href="#contacts">Получить консультацию</a>
                    <?php if (!empty($payload['tests'])): ?><a class="secondary" href="<?= h(public_mini_app_url($profileReferralCode, 'tests')) ?>">Пройти тест</a><?php endif; ?>
                    <a class="secondary" href="<?= h($miniAppUrl) ?>">Открыть кабинет</a>
                </div>
                <?php if ($profileReferralCode): ?><p class="referral-note">Код консультанта: <strong><?= h($profileReferralCode) ?></strong></p><?php endif; ?>
                <div class="hero-metrics">
                    <?php if (!empty($payload['tests'])): ?><span><strong><?= count($payload['tests']) ?></strong> тестов</span><?php endif; ?>
                    <?php if (!empty($payload['products'])): ?><span><strong><?= count($payload['products']) ?></strong> рекомендаций</span><?php endif; ?>
                    <?php if (!empty($payload['materials'])): ?><span><strong><?= count($payload['materials']) ?></strong> материалов</span><?php endif; ?>
                </div>
            </div>
        </section>

        <?php if (public_block_enabled($blocks, 'video') && !empty($profileData['video_url'])): ?>
            <section class="section video-section" id="video">
                <div class="section-head">
                    <h2><?= h(public_block_title($blocks, 'video', 'Видеообращение консультанта')) ?></h2>
                </div>
                <div class="video-layout">
                    <?php $embedUrl = public_youtube_embed_url((string)$profileData['video_url']); ?>
                    <?php if ($embedUrl): ?>
                        <div class="video-embed">
                            <iframe src="<?= h($embedUrl) ?>" title="Видеообращение" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
                        </div>
                    <?php else: ?>
                        <a class="video-card" href="<?= h((string)$profileData['video_url']) ?>" target="_blank" rel="noopener">Смотреть видеообращение</a>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php $aboutSections = public_about_sections($profileData, $blocks); ?>
        <?php if (public_block_enabled($blocks, 'about') && $aboutSections): ?>
            <section class="section about-section" id="about">
                <div class="section-head">
                    <span class="eyebrow">Профиль</span>
                    <h2><?= h(public_block_title($blocks, 'about', 'Обо мне')) ?></h2>
                </div>
                <div class="about-grid">
                    <?php foreach ($aboutSections as $section): ?>
                        <article class="public-card about-card <?= $section['field'] === 'bio' ? 'about-card-main' : '' ?>">
                            <strong><?= h($section['title']) ?></strong>
                            <p><?= nl2br(h($section['text'])) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if (public_block_enabled($blocks, 'products') && !empty($payload['products'])): ?>
            <section class="section" id="products">
                <div class="section-head">
                    <h2><?= h(public_block_title($blocks, 'products', 'Что я рекомендую')) ?></h2>
                    <p>Подборка продуктов и материалов, которые консультант считает полезными для своей аудитории.</p>
                </div>
                <div class="horizontal-cards">
                    <?php foreach ($payload['products'] as $product): ?>
                        <article class="public-card product-card">
                            <?php if (!empty($product['image_path'])): ?>
                                <img src="<?= h((string)$product['image_path']) ?>" alt="">
                            <?php else: ?>
                                <div class="card-image-placeholder">SWPro</div>
                            <?php endif; ?>
                            <div class="card-body">
                                <span class="card-kicker">Рекомендация</span>
                                <strong><?= h((string)$product['title']) ?></strong>
                                <?php if (!empty($product['short_description'])): ?>
                                    <p><?= h((string)$product['short_description']) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($product['full_description'])): ?>
                                    <details class="card-details">
                                        <summary>Подробнее</summary>
                                        <p><?= nl2br(h((string)$product['full_description'])) ?></p>
                                    </details>
                                <?php endif; ?>
                                <div class="material-links">
                                    <?php if (!empty($product['document_path'])): ?><a href="<?= h((string)$product['document_path']) ?>" target="_blank" rel="noopener">Файл</a><?php endif; ?>
                                    <?php if (!empty($product['video_url'])): ?><a href="<?= h((string)$product['video_url']) ?>" target="_blank" rel="noopener">Видео</a><?php endif; ?>
                                    <?php if (!empty($product['purchase_url'])): ?><a href="<?= h((string)$product['purchase_url']) ?>" target="_blank" rel="noopener">Подробнее</a><?php endif; ?>
                                    <a href="<?= h(public_mini_app_url($profileReferralCode, 'products')) ?>">Задать вопрос</a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if (public_block_enabled($blocks, 'tests') && !empty($payload['tests'])): ?>
            <section class="section" id="tests">
                <div class="section-head">
                    <h2><?= h(public_block_title($blocks, 'tests', 'Рекомендуемые тесты')) ?></h2>
                    <p>Короткие диагностики помогают понять запрос клиента и подготовить персональные рекомендации.</p>
                </div>
                <div class="grid-cards">
                    <?php foreach ($payload['tests'] as $test): ?>
                        <article class="public-card test-card">
                            <span class="test-icon">✓</span>
                            <strong><?= h((string)$test['title']) ?></strong>
                            <p><?= h((string)($test['description'] ?? '')) ?></p>
                            <a class="card-action-link" href="<?= h(public_mini_app_url($profileReferralCode, 'tests')) ?>">Открыть тест</a>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if (public_block_enabled($blocks, 'materials') && !empty($payload['materials'])): ?>
            <section class="section" id="materials">
                <div class="section-head">
                    <h2><?= h(public_block_title($blocks, 'materials', 'Полезные материалы')) ?></h2>
                    <p>Статьи, видео и файлы, которые консультант подготовил для клиентов.</p>
                </div>
                <div class="grid-cards">
                    <?php foreach ($payload['materials'] as $material): ?>
                        <article class="public-card material-card">
                            <?php if (!empty($material['image_path'])): ?>
                                <img src="<?= h((string)$material['image_path']) ?>" alt="">
                            <?php endif; ?>
                            <div class="card-body">
                                <span class="card-kicker"><?= h((string)$material['content_type']) ?></span>
                                <strong><?= h((string)$material['title']) ?></strong>
                                <?php if (!empty($material['short_text'])): ?>
                                    <p><?= h((string)$material['short_text']) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($material['full_text'])): ?>
                                    <details class="card-details">
                                        <summary>Читать материал</summary>
                                        <p><?= nl2br(h((string)$material['full_text'])) ?></p>
                                    </details>
                                <?php endif; ?>
                                <div class="material-links">
                                    <?php if (!empty($material['attachment_path'])): ?><a href="<?= h((string)$material['attachment_path']) ?>" target="_blank" rel="noopener">Файл</a><?php endif; ?>
                                    <?php if (!empty($material['video_url'])): ?><a href="<?= h((string)$material['video_url']) ?>" target="_blank" rel="noopener">Видео</a><?php endif; ?>
                                    <a href="<?= h(public_mini_app_url($profileReferralCode)) ?>">Задать вопрос</a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if (public_block_enabled($blocks, 'reviews') && !empty($payload['reviews'])): ?>
            <section class="section" id="reviews">
                <h2><?= h(public_block_title($blocks, 'reviews', 'Отзывы')) ?></h2>
                <div class="horizontal-cards">
                    <?php foreach ($payload['reviews'] as $review): ?>
                        <article class="public-card">
                            <?php if (!empty($review['client_photo_path'])): ?><img class="review-photo" src="<?= h((string)$review['client_photo_path']) ?>" alt=""><?php endif; ?>
                            <strong><?= h((string)$review['client_name']) ?></strong>
                            <p><?= h((string)$review['review_text']) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="section contacts" id="contacts">
            <h2><?= h(public_block_title($blocks, 'contacts', 'Контакты')) ?></h2>
            <div class="public-card contact-card">
                <div class="contact-person">
                    <?php if (!empty($profileData['photo_path'])): ?>
                        <img src="<?= h((string)$profileData['photo_path']) ?>" alt="">
                    <?php endif; ?>
                    <div>
                        <strong><?= h((string)$profileData['display_name']) ?></strong>
                        <?php if (!empty($profileData['subtitle'])): ?><span><?= h((string)$profileData['subtitle']) ?></span><?php endif; ?>
                    </div>
                </div>
                <div class="contact-links">
                    <?php foreach (public_contact_links($profileData) as $label => $url): ?>
                        <a href="<?= h((string)$url) ?>" target="_blank" rel="noopener"><?= h($label) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    </main>
<?php endif; ?>
</body>
</html>
