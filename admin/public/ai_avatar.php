<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/permissions.php';
require_once __DIR__ . '/../app/core/ai_center.php';

$admin = require_auth();
if (!can_manage('ai_avatar', $admin)) {
    http_response_code(403);
    exit('Access denied');
}
$owner = ai_owner_for_admin($admin);
if (!in_array($owner['owner_type'], ['reseller', 'manager'], true) || $owner['owner_id'] <= 0) {
    http_response_code(404);
    exit('Owner not found');
}
$title = 'Мой AI-аватар';
$errors = [];
$success = (string)($_GET['success'] ?? '');
$access = ai_entitlements_for_admin($admin);

function ai_avatar_private_root(): string
{
    $configured = trim((string)(getenv('SWPRO_PRIVATE_STORAGE_PATH') ?: ''));
    return $configured !== '' ? rtrim($configured, '/\\') : dirname(__DIR__, 2) . '/storage/private';
}

function ai_avatar_upload(string $field, array $owner, array &$errors): ?string
{
    if (empty($_FILES[$field]['tmp_name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
        return null;
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES[$field]['tmp_name']) ?: '';
    $allowed = $field === 'source_photo'
        ? ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp']
        : ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov'];
    if (!isset($allowed[$mime])) {
        $errors[] = $field === 'source_photo' ? 'Загрузите JPG, PNG или WebP.' : 'Загрузите MP4, WebM или MOV.';
        return null;
    }
    $max = $field === 'source_photo' ? 15 * 1024 * 1024 : 250 * 1024 * 1024;
    if ((int)($_FILES[$field]['size'] ?? 0) > $max) {
        $errors[] = 'Файл слишком большой.';
        return null;
    }
    $relativeDir = 'ai-avatars/' . $owner['owner_type'] . '-' . $owner['owner_id'];
    $absoluteDir = ai_avatar_private_root() . '/' . $relativeDir;
    if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0770, true) && !is_dir($absoluteDir)) {
        $errors[] = 'Не удалось создать закрытый каталог аватара.';
        return null;
    }
    $name = bin2hex(random_bytes(20)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $absoluteDir . '/' . $name)) {
        $errors[] = 'Не удалось сохранить файл.';
        return null;
    }
    return $relativeDir . '/' . $name;
}

$stmt = db()->prepare('SELECT * FROM ai_avatars WHERE owner_type = :owner_type AND owner_id = :owner_id ORDER BY version DESC LIMIT 1');
$stmt->execute($owner);
$avatar = $stmt->fetch() ?: null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? 'save');
    if ($action === 'approve' && $avatar && !empty($avatar['preview_video_path'])) {
        db()->prepare('UPDATE ai_avatars SET status = "approved" WHERE id = :id')->execute(['id' => (int)$avatar['id']]);
        log_activity('admin', (int)$admin['id'], 'approve_ai_avatar', 'ai_avatars', (int)$avatar['id']);
        redirect('ai_avatar.php?success=approved');
    }
    if ($action === 'reject' && $avatar) {
        db()->prepare('UPDATE ai_avatars SET status = "rejected" WHERE id = :id')->execute(['id' => (int)$avatar['id']]);
        log_activity('admin', (int)$admin['id'], 'reject_ai_avatar', 'ai_avatars', (int)$avatar['id']);
        redirect('ai_avatar.php?success=rejected');
    }
    if ($action === 'save') {
        if (!$access['video'] && !$access['personal_video']) {
            $errors[] = 'Создание AI-аватара не входит в текущую подписку.';
        }
        if (!isset($_POST['likeness_consent'])) {
            $errors[] = 'Нужно подтвердить право на использование своего изображения и голоса.';
        }
        $photo = ai_avatar_upload('source_photo', $owner, $errors);
        $video = ai_avatar_upload('source_video', $owner, $errors);
        if (!$avatar && !$photo && !$video) {
            $errors[] = 'Загрузите фотографию или короткое исходное видео.';
        }
        if (!$errors) {
            $version = $avatar ? (int)$avatar['version'] + 1 : 1;
            $providerAvatarId = trim((string)($_POST['provider_avatar_id'] ?? ''));
            $providerVoiceId = trim((string)($_POST['provider_voice_id'] ?? ''));
            $provider = ai_setting('ai.video_provider', 'disabled') ?: 'disabled';
            $status = $providerAvatarId !== '' && ai_video_provider_configured($provider) ? 'approved' : 'draft';
            $insert = db()->prepare('INSERT INTO ai_avatars (owner_type, owner_id, provider, provider_avatar_id, avatar_type, version, source_photo_path, source_video_path, voice_id, voice_name, background_key, pose_key, consent_confirmed_at, consent_text_version, status) VALUES (:owner_type, :owner_id, :provider, :provider_avatar_id, :avatar_type, :version, :photo, :video, :voice_id, :voice_name, :background_key, :pose_key, NOW(), "2026-08-14", :status)');
            $insert->execute($owner + [
                'provider' => $provider,
                'provider_avatar_id' => $providerAvatarId ?: null,
                'avatar_type' => $video ? 'digital_twin' : 'photo',
                'version' => $version,
                'photo' => $photo ?: ($avatar['source_photo_path'] ?? null),
                'video' => $video ?: ($avatar['source_video_path'] ?? null),
                'voice_id' => $providerVoiceId ?: null,
                'voice_name' => trim((string)($_POST['voice_name'] ?? '')),
                'background_key' => trim((string)($_POST['background_key'] ?? 'neutral')),
                'pose_key' => trim((string)($_POST['pose_key'] ?? 'portrait')),
                'status' => $status,
            ]);
            log_activity('admin', (int)$admin['id'], 'create_ai_avatar_version', 'ai_avatars', (int)db()->lastInsertId(), ['version' => $version]);
            redirect('ai_avatar.php?success=saved');
        }
    }
}

$stmt->execute($owner);
$avatar = $stmt->fetch() ?: null;
require __DIR__ . '/../app/views/layouts/header.php';
?>
<div class="page-title-row"><div><h1>Мой AI-аватар</h1><p class="cell-muted">Исходные фото и видео хранятся закрыто и недоступны по прямой публичной ссылке.</p></div></div>
<?php if ($success): ?><div class="notice success"><?= h(match ($success) { 'approved' => 'Аватар утверждён.', 'rejected' => 'Аватар отклонён. Можно создать новую версию.', default => 'Новая версия аватара сохранена.' }) ?></div><?php endif; ?>
<?php foreach ($errors as $error): ?><div class="alert"><?= h($error) ?></div><?php endforeach; ?>
<?php if (!$access['video'] && !$access['personal_video']): ?><div class="alert">AI-видео не входит в текущую подписку.</div><?php endif; ?>

<?php if ($avatar): ?><section class="panel"><h2>Текущая версия №<?= (int)$avatar['version'] ?></h2><p>Статус: <strong><?= h((string)$avatar['status']) ?></strong> · провайдер: <?= h((string)$avatar['provider']) ?></p>
    <?php if ($avatar['source_photo_path']): ?><a class="button secondary-button" href="ai_avatar_media.php?id=<?= (int)$avatar['id'] ?>&type=photo" target="_blank">Просмотреть исходное фото</a><?php endif; ?>
    <?php if ($avatar['preview_video_path']): ?><video controls preload="metadata" style="max-width:100%" src="ai_avatar_media.php?id=<?= (int)$avatar['id'] ?>&type=preview"></video><div class="form-actions"><form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="approve"><button>Утвердить</button></form><form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="reject"><button class="secondary-button">Создать заново</button></form></div>
    <?php else: ?><p class="cell-muted">Материалы подготовлены. Предпросмотр появится после подключения и запуска выбранного видеопровайдера.</p><?php endif; ?>
</section><?php endif; ?>

<section class="panel form-panel"><h2><?= $avatar ? 'Создать новую версию' : 'Подготовить аватар' ?></h2>
<form method="post" enctype="multipart/form-data" class="crud-form"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save">
    <label class="field"><span>Портретная фотография</span><input type="file" name="source_photo" accept="image/jpeg,image/png,image/webp"><small class="field-hint">Лицо прямо, хорошее освещение, без других людей.</small></label>
    <label class="field"><span>Короткое исходное видео</span><input type="file" name="source_video" accept="video/mp4,video/webm,video/quicktime"><small class="field-hint">Спокойная речь, чистый звук, без монтажа.</small></label>
    <label class="field"><span>Желаемый голос</span><input name="voice_name" value="<?= h((string)($avatar['voice_name'] ?? '')) ?>" placeholder="Русский, спокойный женский"></label>
    <label class="field"><span>ID аватара в HeyGen или Tavus</span><input name="provider_avatar_id" value="<?= h((string)($avatar['provider_avatar_id'] ?? '')) ?>"><small class="field-hint">Берётся в кабинете подключённого видеопровайдера.</small></label>
    <label class="field"><span>ID голоса в HeyGen</span><input name="provider_voice_id" value="<?= h((string)($avatar['voice_id'] ?? '')) ?>"><small class="field-hint">Для Tavus можно оставить пустым.</small></label>
    <label class="field"><span>Фон</span><select name="background_key"><option value="neutral">Нейтральный</option><option value="office">Светлый кабинет</option><option value="home">Домашний</option><option value="transparent">Прозрачный</option></select></label>
    <label class="field"><span>Поза и кадр</span><select name="pose_key"><option value="portrait">Портрет</option><option value="waist">По пояс</option><option value="seated">Сидя</option></select></label>
    <label class="check-row wide"><input type="checkbox" name="likeness_consent" value="1" required><span>Подтверждаю, что загружаю собственное изображение/голос и разрешаю SWPro создать и использовать мой AI-аватар.</span></label>
    <div class="form-actions"><button type="submit" <?= (!$access['video'] && !$access['personal_video']) ? 'disabled' : '' ?>>Сохранить новую версию</button></div>
</form></section>
<?php require __DIR__ . '/../app/views/layouts/footer.php'; ?>
