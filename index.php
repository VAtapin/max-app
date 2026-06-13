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
    <main class="public-empty">
        <section class="public-card">
            <span class="eyebrow">SWPro</span>
            <h1>Найдите своего консультанта</h1>
            <p>Введите реферальный код консультанта или откройте персональную ссылку из Telegram, VK, OK или MAX.</p>
            <form method="post" class="find-form">
                <input name="ref" placeholder="Реферальный код консультанта" required>
                <button type="submit">Открыть страницу</button>
            </form>
        </section>
    </main>
<?php else: ?>
    <?php $profileData = $payload['profile']; ?>
    <main class="consultant-page">
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
                    <?php if (!empty($payload['tests'])): ?><a class="secondary" href="#tests">Пройти тест</a><?php endif; ?>
                </div>
            </div>
        </section>

        <?php if (public_block_enabled($blocks, 'video') && !empty($profileData['video_url'])): ?>
            <section class="section" id="video">
                <h2><?= h(public_block_title($blocks, 'video', 'Видеообращение консультанта')) ?></h2>
                <a class="video-card" href="<?= h((string)$profileData['video_url']) ?>" target="_blank" rel="noopener">Смотреть видеообращение</a>
            </section>
        <?php endif; ?>

        <?php if (public_block_enabled($blocks, 'about') && (!empty($profileData['bio']) || !empty($profileData['specialization']))): ?>
            <section class="section two-col" id="about">
                <div>
                    <h2><?= h(public_block_title($blocks, 'about', 'Обо мне')) ?></h2>
                    <p><?= nl2br(h((string)$profileData['bio'])) ?></p>
                </div>
                <div class="public-card">
                    <strong>Специализация</strong>
                    <p><?= nl2br(h((string)$profileData['specialization'])) ?></p>
                </div>
            </section>
        <?php endif; ?>

        <?php if (public_block_enabled($blocks, 'products') && !empty($payload['products'])): ?>
            <section class="section" id="products">
                <h2><?= h(public_block_title($blocks, 'products', 'Что я рекомендую')) ?></h2>
                <div class="horizontal-cards">
                    <?php foreach ($payload['products'] as $product): ?>
                        <article class="public-card product-card">
                            <?php if (!empty($product['image_path'])): ?><img src="<?= h((string)$product['image_path']) ?>" alt=""><?php endif; ?>
                            <strong><?= h((string)$product['title']) ?></strong>
                            <p><?= h((string)($product['short_description'] ?? '')) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if (public_block_enabled($blocks, 'tests') && !empty($payload['tests'])): ?>
            <section class="section" id="tests">
                <h2><?= h(public_block_title($blocks, 'tests', 'Рекомендуемые тесты')) ?></h2>
                <div class="grid-cards">
                    <?php foreach ($payload['tests'] as $test): ?>
                        <article class="public-card">
                            <span class="test-icon">✓</span>
                            <strong><?= h((string)$test['title']) ?></strong>
                            <p><?= h((string)($test['description'] ?? '')) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if (public_block_enabled($blocks, 'materials') && !empty($payload['materials'])): ?>
            <section class="section" id="materials">
                <h2><?= h(public_block_title($blocks, 'materials', 'Полезные материалы')) ?></h2>
                <div class="grid-cards">
                    <?php foreach ($payload['materials'] as $material): ?>
                        <article class="public-card product-card">
                            <?php if (!empty($material['image_path'])): ?><img src="<?= h((string)$material['image_path']) ?>" alt=""><?php endif; ?>
                            <strong><?= h((string)$material['title']) ?></strong>
                            <p><?= h((string)($material['short_text'] ?? '')) ?></p>
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
                <strong><?= h((string)$profileData['display_name']) ?></strong>
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
