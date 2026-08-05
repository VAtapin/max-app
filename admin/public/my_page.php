<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/permissions.php';
require_once __DIR__ . '/../app/core/consultant_profiles.php';
require_once __DIR__ . '/../app/core/content_ownership.php';

$admin = require_auth();
if (!can_manage('my_page', $admin)) {
    http_response_code(403);
    exit('Access denied');
}

$owner = consultant_owner_from_admin($admin);
if (!$owner) {
    http_response_code(404);
    exit(app_text('consultant_profile.owner_not_found'));
}

$profile = ensure_consultant_profile($owner['owner_type'], $owner['owner_id']);
$title = app_text('consultant_profile.menu');
$errors = [];
$success = $_GET['success'] ?? null;

function profile_owner_query(array $owner): string
{
    return 'owner_type=' . urlencode($owner['owner_type']) . '&owner_id=' . (int)$owner['owner_id'];
}

function profile_owner_admin(array $owner): array
{
    if ($owner['owner_type'] === 'reseller') {
        return [
            'role' => 'reseller',
            'reseller_id' => (int)$owner['owner_id'],
            'manager_id' => null,
        ];
    }

    return [
        'role' => 'manager',
        'manager_id' => (int)$owner['owner_id'],
        'reseller_id' => owned_content_manager_reseller_id((int)$owner['owner_id']),
    ];
}

function profile_select_options(string $source, array $owner): array
{
    $ownerAdmin = profile_owner_admin($owner);

    if ($source === 'materials') {
        [$where, $params] = owned_content_scope_condition('content', $ownerAdmin, 'cp');
        $where = $where ? $where . ' AND cp.status <> "hidden"' : 'WHERE cp.status <> "hidden"';
        $stmt = db()->prepare(
            'SELECT id, title AS label
             FROM content_posts cp
             ' . $where . '
             ORDER BY COALESCE(publish_at, created_at) DESC, title'
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    if ($source === 'products') {
        [$where, $params] = owned_content_scope_condition('products', $ownerAdmin, 'p');
        $where = $where ? $where . ' AND p.is_active = 1' : 'WHERE p.is_active = 1';
        $stmt = db()->prepare('SELECT p.id, p.title AS label FROM products p ' . $where . ' ORDER BY p.sort_order, p.title');
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    if ($source === 'tests') {
        [$where, $params] = owned_content_scope_condition('tests', $ownerAdmin, 't');
        $where = $where ? $where . ' AND t.is_active = 1' : 'WHERE t.is_active = 1';
        $stmt = db()->prepare('SELECT t.id, t.title AS label FROM tests t ' . $where . ' ORDER BY t.sort_order, t.title');
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    return [];
}

function about_section_titles(array $blocks): array
{
    $defaults = [
        'bio' => app_text('consultant_profile.bio'),
        'specialization' => app_text('consultant_profile.specialization'),
        'experience_text' => app_text('consultant_profile.experience'),
        'certificates_text' => app_text('consultant_profile.certificates'),
        'achievements_text' => app_text('consultant_profile.achievements'),
    ];

    foreach ($blocks as $block) {
        if (($block['block_type'] ?? '') !== 'about') {
            continue;
        }
        $settings = json_decode((string)($block['settings_json'] ?? ''), true);
        if (!is_array($settings) || !isset($settings['titles']) || !is_array($settings['titles'])) {
            return $defaults;
        }
        foreach ($defaults as $key => $title) {
            if (isset($settings['titles'][$key]) && trim((string)$settings['titles'][$key]) !== '') {
                $defaults[$key] = trim((string)$settings['titles'][$key]);
            }
        }
    }

    return $defaults;
}

function profile_public_base_url(): string
{
    $configured = trim((string)(app_config()['app']['public_url'] ?? ''));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }

    $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return 'https://swpro.ru';
    }

    return 'https://' . $host;
}

function profile_public_url(array $profile): string
{
    return profile_public_base_url() . '/?m=' . rawurlencode((string)($profile['slug'] ?? ''));
}

function profile_slug_from_public_address(string $value, string $fallback): string
{
    $value = trim(str_replace('&amp;', '&', $value));
    if ($value === '') {
        return $fallback;
    }

    if (preg_match('/[?&]m=([^&#]+)/', $value, $matches)) {
        return rawurldecode((string)$matches[1]);
    }

    $query = parse_url($value, PHP_URL_QUERY);
    if (is_string($query) && $query !== '') {
        parse_str($query, $params);
        if (isset($params['m']) && trim((string)$params['m']) !== '') {
            return (string)$params['m'];
        }
    }

    $path = parse_url($value, PHP_URL_PATH);
    if (is_string($path) && trim($path, '/') !== '') {
        return basename(trim($path, '/'));
    }

    return $value;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $postAction = (string)($_POST['action'] ?? 'save_profile');

    $profileId = (int)$profile['id'];
    if ($postAction === 'apply_template') {
        $templateId = (int)($_POST['template_id'] ?? 0);
        $ownerAdmin = profile_owner_admin($owner);
        try {
            if (!$templateId || !site_template_row($templateId, $ownerAdmin) || !site_template_apply_to_profile($profileId, $owner['owner_type'], (int)$owner['owner_id'], $templateId, true)) {
                $errors[] = 'Выберите активный шаблон оформления.';
            }
        } catch (Throwable $e) {
            $errors[] = 'Не удалось применить шаблон: ' . $e->getMessage();
        }

        if (!$errors) {
            log_activity('admin', (int)$admin['id'], 'apply_site_template', 'consultant_profiles', $profileId, [
                'template_id' => $templateId,
                'owner_type' => $owner['owner_type'],
                'owner_id' => (int)$owner['owner_id'],
            ]);
            redirect('my_page.php?' . profile_owner_query($owner) . '&success=template_applied');
        }
    } elseif ($postAction === 'reset_to_parent') {
        try {
            $parentProfile = consultant_parent_profile($owner['owner_type'], (int)$owner['owner_id']);
            if (!$parentProfile) {
                $errors[] = 'Для этой страницы нет версии выше.';
            } else {
                consultant_profile_reset_to_parent($profileId, (int)$parentProfile['id']);
            }
        } catch (Throwable $e) {
            $errors[] = 'Не удалось вернуть версию выше: ' . $e->getMessage();
        }

        if (!$errors) {
            log_activity('admin', (int)$admin['id'], 'reset_consultant_profile_to_parent', 'consultant_profiles', $profileId, [
                'owner_type' => $owner['owner_type'],
                'owner_id' => (int)$owner['owner_id'],
            ]);
            redirect('my_page.php?' . profile_owner_query($owner) . '&success=profile_reset');
        }
    }

    if (!in_array($postAction, ['apply_template', 'reset_to_parent'], true)) {
    $currentPhotoPath = isset($_POST['remove_photo_path']) ? null : ($_POST['photo_path_current'] ?? ($profile['photo_path'] ?? null));
    $currentBannerPath = isset($_POST['remove_banner_path']) ? null : ($_POST['banner_path_current'] ?? ($profile['banner_path'] ?? null));
    $currentWelcomePath = isset($_POST['remove_welcome_image_path']) ? null : ($_POST['welcome_image_path_current'] ?? ($profile['welcome_image_path'] ?? null));
    $currentCashbackPath = isset($_POST['remove_cashback_image_path']) ? null : ($_POST['cashback_image_path_current'] ?? ($profile['cashback_image_path'] ?? null));
    $currentCooperationPath = isset($_POST['remove_cooperation_image_path']) ? null : ($_POST['cooperation_image_path_current'] ?? ($profile['cooperation_image_path'] ?? null));
    $photoPath = consultant_profile_upload('photo_path', $currentPhotoPath, $errors);
    $bannerPath = consultant_profile_upload('banner_path', $currentBannerPath, $errors);
    $welcomeImagePath = consultant_profile_upload('welcome_image_path', $currentWelcomePath, $errors);
    $cashbackImagePath = consultant_profile_upload('cashback_image_path', $currentCashbackPath, $errors);
    $cooperationImagePath = consultant_profile_upload('cooperation_image_path', $currentCooperationPath, $errors);
    $publicAddress = (string)($_POST['page_url'] ?? $_POST['slug'] ?? '');
    $slug = consultant_unique_slug(
        consultant_slug(
            profile_slug_from_public_address($publicAddress, $owner['owner_type'] . '-' . $owner['owner_id']),
            $owner['owner_type'] . '-' . $owner['owner_id']
        ),
        $profileId
    );

    if (!$errors) {
        $stmt = db()->prepare(
            'UPDATE consultant_profiles
             SET slug = :slug,
                 display_name = :display_name,
                 title = :title,
                 subtitle = :subtitle,
                 short_description = :short_description,
                 welcome_text = :welcome_text,
                 welcome_image_path = :welcome_image_path,
                 welcome_video_url = :welcome_video_url,
                 cashback_title = :cashback_title,
                 cashback_text = :cashback_text,
                 cashback_image_path = :cashback_image_path,
                 cashback_url = :cashback_url,
                 cooperation_title = :cooperation_title,
                 cooperation_text = :cooperation_text,
                 cooperation_image_path = :cooperation_image_path,
                 cooperation_video_url = :cooperation_video_url,
                 bio = :bio,
                 specialization = :specialization,
                 experience_text = :experience_text,
                 achievements_text = :achievements_text,
                 certificates_text = :certificates_text,
                 photo_path = :photo_path,
                 banner_path = :banner_path,
                 video_url = :video_url,
                 phone = :phone,
                 email = :email,
                 telegram_url = :telegram_url,
                 whatsapp_url = :whatsapp_url,
                 vk_url = :vk_url,
                 ok_url = :ok_url,
                 theme_key = :theme_key,
                 is_public = :is_public,
                 source_profile_id = NULL,
                 template_customized_at = NOW()
             WHERE id = :id'
        );
        $themeKey = (string)($_POST['theme_key'] ?? 'classic');
        if (!array_key_exists($themeKey, consultant_theme_options())) {
            $themeKey = 'classic';
        }
        $stmt->execute([
            'id' => $profileId,
            'slug' => $slug,
            'display_name' => trim((string)($_POST['display_name'] ?? '')),
            'title' => trim((string)($_POST['title'] ?? '')),
            'subtitle' => trim((string)($_POST['subtitle'] ?? '')),
            'short_description' => trim((string)($_POST['short_description'] ?? '')),
            'welcome_text' => trim((string)($_POST['welcome_text'] ?? '')),
            'welcome_image_path' => $welcomeImagePath,
            'welcome_video_url' => trim((string)($_POST['welcome_video_url'] ?? '')),
            'cashback_title' => trim((string)($_POST['cashback_title'] ?? '')),
            'cashback_text' => trim((string)($_POST['cashback_text'] ?? '')),
            'cashback_image_path' => $cashbackImagePath,
            'cashback_url' => trim((string)($_POST['cashback_url'] ?? '')),
            'cooperation_title' => trim((string)($_POST['cooperation_title'] ?? '')),
            'cooperation_text' => trim((string)($_POST['cooperation_text'] ?? '')),
            'cooperation_image_path' => $cooperationImagePath,
            'cooperation_video_url' => trim((string)($_POST['cooperation_video_url'] ?? '')),
            'bio' => trim((string)($_POST['bio'] ?? '')),
            'specialization' => trim((string)($_POST['specialization'] ?? '')),
            'experience_text' => trim((string)($_POST['experience_text'] ?? '')),
            'achievements_text' => trim((string)($_POST['achievements_text'] ?? '')),
            'certificates_text' => trim((string)($_POST['certificates_text'] ?? '')),
            'photo_path' => $photoPath,
            'banner_path' => $bannerPath,
            'video_url' => trim((string)($_POST['video_url'] ?? '')),
            'phone' => trim((string)($_POST['phone'] ?? '')),
            'email' => trim((string)($_POST['email'] ?? '')),
            'telegram_url' => trim((string)($_POST['telegram_url'] ?? '')),
            'whatsapp_url' => trim((string)($_POST['whatsapp_url'] ?? '')),
            'vk_url' => trim((string)($_POST['vk_url'] ?? '')),
            'ok_url' => trim((string)($_POST['ok_url'] ?? '')),
            'theme_key' => $themeKey,
            'is_public' => isset($_POST['is_public']) ? 1 : 0,
        ]);

        $blockStmt = db()->prepare(
            'UPDATE profile_blocks
             SET title = :title, is_enabled = :is_enabled, sort_order = :sort_order, settings_json = COALESCE(:settings_json, settings_json)
             WHERE profile_id = :profile_id AND block_type = :block_type'
        );
        foreach (default_consultant_blocks() as $blockType => [$defaultTitle, $defaultSort]) {
            $settingsJson = null;
            if ($blockType === 'about') {
                $aboutTitles = [];
                foreach (['bio', 'specialization', 'experience_text', 'certificates_text', 'achievements_text'] as $field) {
                    $aboutTitles[$field] = trim((string)($_POST['about_titles'][$field] ?? app_text('consultant_profile.' . match ($field) {
                        'experience_text' => 'experience',
                        'certificates_text' => 'certificates',
                        'achievements_text' => 'achievements',
                        default => $field,
                    })));
                }
                $settingsJson = json_encode(['titles' => $aboutTitles], JSON_UNESCAPED_UNICODE);
            }
            $blockStmt->execute([
                'profile_id' => $profileId,
                'block_type' => $blockType,
                'title' => trim((string)($_POST['block_titles'][$blockType] ?? $defaultTitle)),
                'is_enabled' => isset($_POST['block_enabled'][$blockType]) ? 1 : 0,
                'sort_order' => (int)($_POST['block_sort'][$blockType] ?? $defaultSort),
                'settings_json' => $settingsJson,
            ]);
        }

        replace_consultant_items($profileId, 'profile_products', 'product_id', $_POST['products'] ?? []);
        ensure_consultant_primary_test($profileId);
        replace_consultant_items($profileId, 'profile_materials', 'content_post_id', $_POST['materials'] ?? []);

        log_activity('admin', (int)$admin['id'], 'update_consultant_profile', 'consultant_profiles', $profileId);
        redirect('my_page.php?' . profile_owner_query($owner) . '&success=saved');
    }
    }
}

$storedProfile = ensure_consultant_profile($owner['owner_type'], $owner['owner_id']);
$effectiveProfileId = consultant_effective_profile_id($storedProfile);
$profile = consultant_effective_profile($storedProfile);
$blocks = consultant_blocks($effectiveProfileId);
$aboutTitles = about_section_titles($blocks);
$selectedProducts = consultant_selected_ids($effectiveProfileId, 'profile_products', 'product_id');
$selectedMaterials = consultant_selected_ids($effectiveProfileId, 'profile_materials', 'content_post_id');
$ownerOptions = consultant_options_for_admin($admin);
$templateOptions = site_template_options(profile_owner_admin($owner));
$parentProfile = consultant_parent_profile($owner['owner_type'], (int)$owner['owner_id']);
$isInheritedProfile = consultant_profile_inherits($storedProfile);

require __DIR__ . '/../app/views/layouts/header.php';
?>
<div class="toolbar">
    <h1><?= h(app_text('consultant_profile.title')) ?></h1>
    <a class="button secondary-button" target="_blank" rel="noopener" href="<?= h(profile_public_url($profile)) ?>"><?= h(app_text('consultant_profile.open_public')) ?></a>
</div>

<?php if ($success === 'saved'): ?>
    <div class="notice success"><?= h(app_text('consultant_profile.saved')) ?></div>
<?php elseif ($success === 'template_applied'): ?>
    <div class="notice success">Шаблон применён. Личные фотографии и загруженные изображения сохранены.</div>
<?php elseif ($success === 'profile_reset'): ?>
    <div class="notice success">Страница снова использует версию вышестоящего лидера.</div>
<?php endif; ?>
<?php foreach ($errors as $error): ?>
    <div class="alert"><?= h($error) ?></div>
<?php endforeach; ?>

<?php if ($ownerOptions): ?>
    <section class="panel profile-owner-panel">
        <form method="get" class="inline-form">
            <label class="field">
                <span><?= h(app_text('consultant_profile.profile_owner')) ?></span>
                <select name="owner_selector">
                    <?php foreach ($ownerOptions as $option): ?>
                        <?php $value = $option['owner_type'] . ':' . $option['owner_id']; ?>
                        <option value="<?= h($value) ?>" <?= $value === $owner['owner_type'] . ':' . $owner['owner_id'] ? 'selected' : '' ?>><?= h($option['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <input type="hidden" name="owner_type" value="<?= h($owner['owner_type']) ?>">
            <input type="hidden" name="owner_id" value="<?= (int)$owner['owner_id'] ?>">
            <button type="submit"><?= h(app_text('auto.k_7788a11e4dbf')) ?></button>
        </form>
    </section>
<?php endif; ?>

<?php if ($templateOptions): ?>
    <section class="panel form-panel">
        <h2>Шаблон оформления</h2>
        <p class="cell-muted">Можно быстро вернуть страницу к одному из готовых вариантов. Ваши личные фото и загруженные изображения не затираются.</p>
        <form method="post" class="inline-form" onsubmit="return confirm('Применить выбранный шаблон к странице? Тексты и настройки блоков будут заменены шаблонными.');">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="apply_template">
            <input type="hidden" name="owner_type" value="<?= h($owner['owner_type']) ?>">
            <input type="hidden" name="owner_id" value="<?= (int)$owner['owner_id'] ?>">
            <label class="field">
                <span>Вариант</span>
                <select name="template_id">
                    <?php foreach ($templateOptions as $option): ?>
                        <option value="<?= (int)$option['id'] ?>" <?= (int)($profile['template_id'] ?? 0) === (int)$option['id'] ? 'selected' : '' ?>>
                            <?= h((string)$option['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="secondary-button">Применить шаблон</button>
        </form>
        <?php if ($parentProfile): ?>
            <form method="post" class="inline-form" onsubmit="return confirm('Вернуть страницу к версии вышестоящего лидера? Ваша личная настройка блоков и подборок будет сброшена.');">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="reset_to_parent">
                <input type="hidden" name="owner_type" value="<?= h($owner['owner_type']) ?>">
                <input type="hidden" name="owner_id" value="<?= (int)$owner['owner_id'] ?>">
                <button type="submit" class="secondary-button"><?= $isInheritedProfile ? 'Обновить из версии выше' : 'Вернуть версию выше' ?></button>
                <span class="cell-muted">Версия выше: <?= h((string)($parentProfile['display_name'] ?? '#' . (int)$parentProfile['id'])) ?></span>
            </form>
        <?php endif; ?>
    </section>
<?php endif; ?>

<form method="post" class="profile-builder" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="save_profile">
    <input type="hidden" name="owner_type" value="<?= h($owner['owner_type']) ?>">
    <input type="hidden" name="owner_id" value="<?= (int)$owner['owner_id'] ?>">

    <section class="panel profile-hero-editor">
        <div>
            <h2><?= h(app_text('consultant_profile.main_section')) ?></h2>
            <p class="cell-muted"><?= h(app_text('consultant_profile.main_hint')) ?></p>
            <div class="profile-preview-card">
                <?php if (!empty($profile['banner_path'])): ?>
                    <img class="profile-preview-banner" src="<?= h((string)$profile['banner_path']) ?>" alt="">
                <?php endif; ?>
                <?php if (!empty($profile['photo_path'])): ?>
                    <img src="<?= h((string)$profile['photo_path']) ?>" alt="">
                <?php else: ?>
                    <div class="profile-photo-placeholder"><?= h(mb_substr((string)$profile['display_name'], 0, 2, 'UTF-8')) ?></div>
                <?php endif; ?>
                <div>
                    <strong><?= h((string)$profile['display_name']) ?></strong>
                    <span><?= h((string)$profile['subtitle']) ?></span>
                </div>
            </div>
        </div>
        <div class="profile-form-grid">
            <label class="field">
                <span><?= h(app_text('consultant_profile.display_name')) ?></span>
                <input name="display_name" value="<?= h((string)$profile['display_name']) ?>" required>
            </label>
            <div class="field profile-slug-field">
                <span>Адрес сайта</span>
                <div
                    class="profile-slug-control"
                    data-profile-slug-control
                    data-base-url="<?= h(profile_public_base_url() . '/?m=') ?>"
                >
                    <span class="profile-slug-prefix"><?= h(profile_public_base_url() . '/?m=') ?></span>
                    <input name="page_url" value="<?= h((string)($profile['slug'] ?? '')) ?>" autocomplete="off">
                    <button type="button" class="secondary-button" data-profile-copy-url>Скопировать</button>
                </div>
            </div>
            <label class="field">
                <span><?= h(app_text('consultant_profile.profile_title')) ?></span>
                <input name="title" value="<?= h((string)$profile['title']) ?>">
            </label>
            <label class="field">
                <span><?= h(app_text('consultant_profile.subtitle')) ?></span>
                <input name="subtitle" value="<?= h((string)$profile['subtitle']) ?>">
            </label>
            <label class="field wide">
                <span><?= h(app_text('consultant_profile.short_description')) ?></span>
                <textarea name="short_description" rows="3"><?= h((string)$profile['short_description']) ?></textarea>
            </label>
            <label class="field">
                <span><?= h(app_text('consultant_profile.theme')) ?></span>
                <select name="theme_key">
                    <?php foreach (consultant_theme_options() as $themeKey => $themeLabel): ?>
                        <option value="<?= h($themeKey) ?>" <?= ($profile['theme_key'] ?? 'classic') === $themeKey ? 'selected' : '' ?>><?= h($themeLabel) ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="cell-muted"><?= h(app_text('consultant_profile.theme_hint')) ?></small>
            </label>
            <label class="field">
                <span><?= h(app_text('consultant_profile.photo')) ?></span>
                <input type="hidden" name="photo_path_current" value="<?= h((string)$profile['photo_path']) ?>">
                <input type="file" name="photo_path" accept="image/*">
                <?php if (!empty($profile['photo_path'])): ?>
                    <label class="checkbox-line subtle-checkbox">
                        <input type="checkbox" name="remove_photo_path" value="1">
                        <?= h(app_text('consultant_profile.remove_photo')) ?>
                    </label>
                <?php endif; ?>
            </label>
            <label class="field">
                <span><?= h(app_text('consultant_profile.banner')) ?></span>
                <input type="hidden" name="banner_path_current" value="<?= h((string)$profile['banner_path']) ?>">
                <input type="file" name="banner_path" accept="image/*">
                <small class="cell-muted"><?= h(app_text('consultant_profile.banner_hint')) ?></small>
                <?php if (!empty($profile['banner_path'])): ?>
                    <label class="checkbox-line subtle-checkbox">
                        <input type="checkbox" name="remove_banner_path" value="1">
                        <?= h(app_text('consultant_profile.remove_banner')) ?>
                    </label>
                <?php endif; ?>
            </label>
            <label class="checkbox-line wide">
                <input type="checkbox" name="is_public" value="1" <?= (int)$profile['is_public'] === 1 ? 'checked' : '' ?>>
                <?= h(app_text('consultant_profile.is_public')) ?>
            </label>
        </div>
    </section>

    <section class="panel">
        <h2>Приветствие в боте и Mini App</h2>
        <p class="cell-muted">Этот блок пользователь видит при первом запуске от имени конкретного консультанта.</p>
        <div class="profile-form-grid">
            <label class="field wide">
                <span>Текст приветствия</span>
                <textarea name="welcome_text" rows="5"><?= h((string)$profile['welcome_text']) ?></textarea>
            </label>
            <label class="field">
                <span>Приветственное изображение</span>
                <input type="hidden" name="welcome_image_path_current" value="<?= h((string)$profile['welcome_image_path']) ?>">
                <input type="file" name="welcome_image_path" accept="image/*">
                <?php if (!empty($profile['welcome_image_path'])): ?>
                    <a href="<?= h((string)$profile['welcome_image_path']) ?>" target="_blank" rel="noopener">Открыть изображение</a>
                    <label class="checkbox-line"><input type="checkbox" name="remove_welcome_image_path" value="1"> Удалить изображение</label>
                <?php endif; ?>
            </label>
            <label class="field">
                <span>Ссылка на приветственное видео</span>
                <input name="welcome_video_url" value="<?= h((string)$profile['welcome_video_url']) ?>" placeholder="https://...">
            </label>
        </div>
    </section>

    <section class="panel">
        <h2><?= h(app_text('consultant_profile.video_about')) ?></h2>
        <p class="cell-muted"><?= h(app_text('consultant_profile.video_about_hint')) ?></p>
        <div class="profile-form-grid">
            <label class="field wide">
                <span><?= h(app_text('consultant_profile.video_url')) ?></span>
                <input name="video_url" value="<?= h((string)$profile['video_url']) ?>" placeholder="https://...">
            </label>
            <label class="field wide">
                <span><?= h(app_text('consultant_profile.bio')) ?></span>
                <input name="about_titles[bio]" value="<?= h($aboutTitles['bio']) ?>">
                <textarea name="bio" rows="5"><?= h((string)$profile['bio']) ?></textarea>
            </label>
            <label class="field">
                <span><?= h(app_text('consultant_profile.specialization')) ?></span>
                <input name="about_titles[specialization]" value="<?= h($aboutTitles['specialization']) ?>">
                <textarea name="specialization" rows="4"><?= h((string)$profile['specialization']) ?></textarea>
            </label>
            <label class="field">
                <span><?= h(app_text('consultant_profile.experience')) ?></span>
                <input name="about_titles[experience_text]" value="<?= h($aboutTitles['experience_text']) ?>">
                <textarea name="experience_text" rows="4"><?= h((string)$profile['experience_text']) ?></textarea>
            </label>
            <label class="field">
                <span><?= h(app_text('consultant_profile.certificates')) ?></span>
                <input name="about_titles[certificates_text]" value="<?= h($aboutTitles['certificates_text']) ?>">
                <textarea name="certificates_text" rows="4"><?= h((string)$profile['certificates_text']) ?></textarea>
            </label>
            <label class="field">
                <span><?= h(app_text('consultant_profile.achievements')) ?></span>
                <input name="about_titles[achievements_text]" value="<?= h($aboutTitles['achievements_text']) ?>">
                <textarea name="achievements_text" rows="4"><?= h((string)$profile['achievements_text']) ?></textarea>
            </label>
        </div>
    </section>

    <section class="panel">
        <h2>Кэшбэк и подарки</h2>
        <div class="profile-form-grid">
            <label class="field">
                <span>Заголовок</span>
                <input name="cashback_title" value="<?= h((string)$profile['cashback_title']) ?>">
            </label>
            <label class="field">
                <span>Персональная ссылка оформления карты</span>
                <input type="url" name="cashback_url" value="<?= h((string)$profile['cashback_url']) ?>" placeholder="https://...">
            </label>
            <label class="field wide">
                <span>Описание преимуществ</span>
                <textarea name="cashback_text" rows="6"><?= h((string)$profile['cashback_text']) ?></textarea>
            </label>
            <label class="field">
                <span>Изображение</span>
                <input type="hidden" name="cashback_image_path_current" value="<?= h((string)$profile['cashback_image_path']) ?>">
                <input type="file" name="cashback_image_path" accept="image/*">
                <?php if (!empty($profile['cashback_image_path'])): ?>
                    <a href="<?= h((string)$profile['cashback_image_path']) ?>" target="_blank" rel="noopener">Открыть изображение</a>
                    <label class="checkbox-line"><input type="checkbox" name="remove_cashback_image_path" value="1"> Удалить изображение</label>
                <?php endif; ?>
            </label>
        </div>
    </section>

    <section class="panel">
        <h2>Возможность сотрудничества</h2>
        <div class="profile-form-grid">
            <label class="field">
                <span>Заголовок</span>
                <input name="cooperation_title" value="<?= h((string)$profile['cooperation_title']) ?>">
            </label>
            <label class="field">
                <span>Ссылка на видео</span>
                <input name="cooperation_video_url" value="<?= h((string)$profile['cooperation_video_url']) ?>" placeholder="https://...">
            </label>
            <label class="field wide">
                <span>Описание вариантов сотрудничества</span>
                <textarea name="cooperation_text" rows="6"><?= h((string)$profile['cooperation_text']) ?></textarea>
            </label>
            <label class="field">
                <span>Изображение</span>
                <input type="hidden" name="cooperation_image_path_current" value="<?= h((string)$profile['cooperation_image_path']) ?>">
                <input type="file" name="cooperation_image_path" accept="image/*">
                <?php if (!empty($profile['cooperation_image_path'])): ?>
                    <a href="<?= h((string)$profile['cooperation_image_path']) ?>" target="_blank" rel="noopener">Открыть изображение</a>
                    <label class="checkbox-line"><input type="checkbox" name="remove_cooperation_image_path" value="1"> Удалить изображение</label>
                <?php endif; ?>
            </label>
        </div>
    </section>

    <section class="panel">
        <h2><?= h(app_text('consultant_profile.contacts')) ?></h2>
        <p class="cell-muted"><?= h(app_text('consultant_profile.contacts_hint')) ?></p>
        <div class="profile-form-grid">
            <?php foreach (['phone', 'email', 'telegram_url', 'whatsapp_url', 'vk_url', 'ok_url'] as $field): ?>
                <label class="field">
                    <span><?= h(app_text('consultant_profile.' . $field)) ?></span>
                    <input name="<?= h($field) ?>" value="<?= h((string)$profile[$field]) ?>">
                </label>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="panel">
        <h2><?= h(app_text('consultant_profile.blocks')) ?></h2>
        <p class="cell-muted"><?= h(app_text('consultant_profile.blocks_hint')) ?></p>
        <div class="block-settings">
            <?php foreach ($blocks as $block): ?>
                <?php if (($block['block_type'] ?? '') === 'products') continue; ?>
                <div class="block-row">
                    <label class="checkbox-line">
                        <input type="checkbox" name="block_enabled[<?= h($block['block_type']) ?>]" value="1" <?= (int)$block['is_enabled'] === 1 ? 'checked' : '' ?>>
                        <?= h(app_text('consultant_profile.block_' . $block['block_type'])) ?>
                    </label>
                    <input name="block_titles[<?= h($block['block_type']) ?>]" value="<?= h((string)$block['title']) ?>">
                    <input type="number" name="block_sort[<?= h($block['block_type']) ?>]" value="<?= (int)$block['sort_order'] ?>">
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="panel">
        <h2><?= h(app_text('consultant_profile.showcase')) ?></h2>
        <p class="cell-muted"><?= h(app_text('consultant_profile.showcase_hint')) ?></p>
        <div class="profile-form-grid">
            <?php
            $selectGroups = [
                'materials' => [app_text('consultant_profile.materials'), profile_select_options('materials', $owner), $selectedMaterials],
            ];
            ?>
            <?php foreach ($selectGroups as $name => [$label, $options, $selected]): ?>
                <section class="field showcase-group">
                    <span><?= h($label) ?></span>
                    <div class="showcase-picker">
                        <?php foreach ($options as $option): ?>
                            <label class="showcase-option">
                                <input type="checkbox" name="<?= h($name) ?>[]" value="<?= (int)$option['id'] ?>" <?= in_array((int)$option['id'], $selected, true) ? 'checked' : '' ?>>
                                <span>#<?= (int)$option['id'] ?></span>
                                <strong><?= h($option['label']) ?></strong>
                            </label>
                        <?php endforeach; ?>
                        <?php if (!$options): ?>
                            <div class="cell-muted"><?= h(app_text('consultant_profile.no_showcase_items')) ?></div>
                        <?php endif; ?>
                    </div>
                    <small class="cell-muted"><?= h(app_text('consultant_profile.checkbox_select_hint')) ?></small>
                </section>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="sticky-actions">
        <button type="submit"><?= h(app_text('auto.k_4864057d626a')) ?></button>
        <a class="button secondary-button" target="_blank" rel="noopener" href="<?= h(profile_public_url($profile)) ?>"><?= h(app_text('consultant_profile.open_public')) ?></a>
    </div>
</form>

<script>
    document.querySelector('.profile-owner-panel select[name="owner_selector"]')?.addEventListener('change', (event) => {
        const [ownerType, ownerId] = event.target.value.split(':');
        const form = event.target.closest('form');
        form.querySelector('[name="owner_type"]').value = ownerType;
        form.querySelector('[name="owner_id"]').value = ownerId;
    });

    document.addEventListener('DOMContentLoaded', () => {
        const control = document.querySelector('[data-profile-slug-control]');
        const button = document.querySelector('[data-profile-copy-url]');
        const input = control?.querySelector('input[name="page_url"]');
        if (!control || !button || !input) return;

        const initialText = button.textContent;
        const copyText = async (text) => {
            try {
                await navigator.clipboard.writeText(text);
            } catch (_) {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.setAttribute('readonly', '');
                textarea.style.position = 'fixed';
                textarea.style.left = '-9999px';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                textarea.remove();
            }
        };

        button.addEventListener('click', async () => {
            const slug = (input.value || '').trim();
            await copyText((control.dataset.baseUrl || '') + encodeURIComponent(slug));
            button.textContent = 'Скопировано';
            setTimeout(() => button.textContent = initialText, 1500);
        });
    });
</script>
<?php require __DIR__ . '/../app/views/layouts/footer.php'; ?>
