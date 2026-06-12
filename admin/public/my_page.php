<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/permissions.php';
require_once __DIR__ . '/../app/core/consultant_profiles.php';

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

function profile_select_options(string $source): array
{
    return match ($source) {
        'products' => db()->query('SELECT id, title AS label FROM products WHERE is_active = 1 ORDER BY sort_order, title')->fetchAll(),
        'tests' => db()->query('SELECT id, title AS label FROM tests WHERE is_active = 1 ORDER BY sort_order, title')->fetchAll(),
        'materials' => db()->query('SELECT id, title AS label FROM content_posts WHERE status <> "hidden" ORDER BY COALESCE(publish_at, created_at) DESC, title')->fetchAll(),
        default => [],
    };
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $profileId = (int)$profile['id'];
    $photoPath = consultant_profile_upload('photo_path', $_POST['photo_path_current'] ?? ($profile['photo_path'] ?? null), $errors);
    $bannerPath = consultant_profile_upload('banner_path', $_POST['banner_path_current'] ?? ($profile['banner_path'] ?? null), $errors);
    $slug = consultant_unique_slug(consultant_slug((string)($_POST['slug'] ?? ''), $owner['owner_type'] . '-' . $owner['owner_id']), $profileId);

    if (!$errors) {
        $stmt = db()->prepare(
            'UPDATE consultant_profiles
             SET slug = :slug,
                 display_name = :display_name,
                 title = :title,
                 subtitle = :subtitle,
                 short_description = :short_description,
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
                 is_public = :is_public
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $profileId,
            'slug' => $slug,
            'display_name' => trim((string)($_POST['display_name'] ?? '')),
            'title' => trim((string)($_POST['title'] ?? '')),
            'subtitle' => trim((string)($_POST['subtitle'] ?? '')),
            'short_description' => trim((string)($_POST['short_description'] ?? '')),
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
            'is_public' => isset($_POST['is_public']) ? 1 : 0,
        ]);

        $blockStmt = db()->prepare(
            'UPDATE profile_blocks
             SET title = :title, is_enabled = :is_enabled, sort_order = :sort_order
             WHERE profile_id = :profile_id AND block_type = :block_type'
        );
        foreach (default_consultant_blocks() as $blockType => [$defaultTitle, $defaultSort]) {
            $blockStmt->execute([
                'profile_id' => $profileId,
                'block_type' => $blockType,
                'title' => trim((string)($_POST['block_titles'][$blockType] ?? $defaultTitle)),
                'is_enabled' => isset($_POST['block_enabled'][$blockType]) ? 1 : 0,
                'sort_order' => (int)($_POST['block_sort'][$blockType] ?? $defaultSort),
            ]);
        }

        replace_consultant_items($profileId, 'profile_products', 'product_id', $_POST['products'] ?? []);
        replace_consultant_items($profileId, 'profile_tests', 'test_id', $_POST['tests'] ?? []);
        replace_consultant_items($profileId, 'profile_materials', 'content_post_id', $_POST['materials'] ?? []);

        log_activity('admin', (int)$admin['id'], 'update_consultant_profile', 'consultant_profiles', $profileId);
        redirect('my_page.php?' . profile_owner_query($owner) . '&success=saved');
    }
}

$profile = ensure_consultant_profile($owner['owner_type'], $owner['owner_id']);
$blocks = consultant_blocks((int)$profile['id']);
$selectedProducts = consultant_selected_ids((int)$profile['id'], 'profile_products', 'product_id');
$selectedTests = consultant_selected_ids((int)$profile['id'], 'profile_tests', 'test_id');
$selectedMaterials = consultant_selected_ids((int)$profile['id'], 'profile_materials', 'content_post_id');
$ownerOptions = consultant_options_for_admin($admin);

require __DIR__ . '/../app/views/layouts/header.php';
?>
<div class="toolbar">
    <h1><?= h(app_text('consultant_profile.title')) ?></h1>
    <a class="button secondary-button" target="_blank" rel="noopener" href="/?m=<?= h((string)$profile['slug']) ?>"><?= h(app_text('consultant_profile.open_public')) ?></a>
</div>

<?php if ($success === 'saved'): ?>
    <div class="notice success"><?= h(app_text('consultant_profile.saved')) ?></div>
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

<form method="post" class="profile-builder" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="owner_type" value="<?= h($owner['owner_type']) ?>">
    <input type="hidden" name="owner_id" value="<?= (int)$owner['owner_id'] ?>">

    <section class="panel profile-hero-editor">
        <div>
            <h2><?= h(app_text('consultant_profile.main_section')) ?></h2>
            <div class="profile-preview-card">
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
            <label class="field">
                <span><?= h(app_text('consultant_profile.slug')) ?></span>
                <input name="slug" value="<?= h((string)$profile['slug']) ?>">
            </label>
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
                <span><?= h(app_text('consultant_profile.photo')) ?></span>
                <input type="hidden" name="photo_path_current" value="<?= h((string)$profile['photo_path']) ?>">
                <input type="file" name="photo_path" accept="image/*">
            </label>
            <label class="field">
                <span><?= h(app_text('consultant_profile.banner')) ?></span>
                <input type="hidden" name="banner_path_current" value="<?= h((string)$profile['banner_path']) ?>">
                <input type="file" name="banner_path" accept="image/*">
            </label>
            <label class="checkbox-line wide">
                <input type="checkbox" name="is_public" value="1" <?= (int)$profile['is_public'] === 1 ? 'checked' : '' ?>>
                <?= h(app_text('consultant_profile.is_public')) ?>
            </label>
        </div>
    </section>

    <section class="panel">
        <h2><?= h(app_text('consultant_profile.video_about')) ?></h2>
        <div class="profile-form-grid">
            <label class="field wide">
                <span><?= h(app_text('consultant_profile.video_url')) ?></span>
                <input name="video_url" value="<?= h((string)$profile['video_url']) ?>" placeholder="https://...">
            </label>
            <label class="field wide">
                <span><?= h(app_text('consultant_profile.bio')) ?></span>
                <textarea name="bio" rows="5"><?= h((string)$profile['bio']) ?></textarea>
            </label>
            <label class="field">
                <span><?= h(app_text('consultant_profile.specialization')) ?></span>
                <textarea name="specialization" rows="4"><?= h((string)$profile['specialization']) ?></textarea>
            </label>
            <label class="field">
                <span><?= h(app_text('consultant_profile.experience')) ?></span>
                <textarea name="experience_text" rows="4"><?= h((string)$profile['experience_text']) ?></textarea>
            </label>
            <label class="field">
                <span><?= h(app_text('consultant_profile.certificates')) ?></span>
                <textarea name="certificates_text" rows="4"><?= h((string)$profile['certificates_text']) ?></textarea>
            </label>
            <label class="field">
                <span><?= h(app_text('consultant_profile.achievements')) ?></span>
                <textarea name="achievements_text" rows="4"><?= h((string)$profile['achievements_text']) ?></textarea>
            </label>
        </div>
    </section>

    <section class="panel">
        <h2><?= h(app_text('consultant_profile.contacts')) ?></h2>
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
        <div class="block-settings">
            <?php foreach ($blocks as $block): ?>
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
        <div class="profile-form-grid">
            <?php
            $selectGroups = [
                'products' => [app_text('consultant_profile.products'), profile_select_options('products'), $selectedProducts],
                'tests' => [app_text('consultant_profile.tests'), profile_select_options('tests'), $selectedTests],
                'materials' => [app_text('consultant_profile.materials'), profile_select_options('materials'), $selectedMaterials],
            ];
            ?>
            <?php foreach ($selectGroups as $name => [$label, $options, $selected]): ?>
                <label class="field">
                    <span><?= h($label) ?></span>
                    <select name="<?= h($name) ?>[]" multiple size="8">
                        <?php foreach ($options as $option): ?>
                            <option value="<?= (int)$option['id'] ?>" <?= in_array((int)$option['id'], $selected, true) ? 'selected' : '' ?>>
                                #<?= (int)$option['id'] ?> <?= h($option['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="cell-muted"><?= h(app_text('consultant_profile.multi_select_hint')) ?></small>
                </label>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="sticky-actions">
        <button type="submit"><?= h(app_text('auto.k_4864057d626a')) ?></button>
        <a class="button secondary-button" target="_blank" rel="noopener" href="/?m=<?= h((string)$profile['slug']) ?>"><?= h(app_text('consultant_profile.open_public')) ?></a>
    </div>
</form>

<script>
    document.querySelector('.profile-owner-panel select[name="owner_selector"]')?.addEventListener('change', (event) => {
        const [ownerType, ownerId] = event.target.value.split(':');
        const form = event.target.closest('form');
        form.querySelector('[name="owner_type"]').value = ownerType;
        form.querySelector('[name="owner_id"]').value = ownerId;
    });
</script>
<?php require __DIR__ . '/../app/views/layouts/footer.php'; ?>
