<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/permissions.php';
require_once __DIR__ . '/../app/core/ai_center.php';
require_once __DIR__ . '/../app/core/qrcode.php';

$admin = require_auth();
if (!can_manage('ai_studio', $admin)) {
    http_response_code(403);
    exit('Access denied');
}
$owner = ai_owner_for_admin($admin);
$title = 'AI-студия';
$errors = [];
$access = ai_entitlements_for_admin($admin);

function ai_studio_owner_profile(array $owner): array
{
    $profile = ai_profile_for_owner($owner) ?: [];
    $table = $owner['owner_type'] === 'manager' ? 'managers' : 'resellers';
    $stmt = db()->prepare("SELECT name, referral_code FROM $table WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => (int)$owner['owner_id']]);
    return $profile + ($stmt->fetch() ?: []);
}

function ai_studio_card_uri(array $profile, string $theme = 'ocean'): string
{
    $name = trim((string)($profile['display_name'] ?? $profile['name'] ?? 'SWPro'));
    $subtitle = trim((string)($profile['subtitle'] ?? $profile['title'] ?? 'Ваш персональный консультант'));
    $base = rtrim((string)(getenv('SWPRO_PUBLIC_URL') ?: 'https://swpro.ru'), '/');
    $code = trim((string)($profile['referral_code'] ?? ''));
    $url = $base . ($code !== '' ? '/?ref=' . rawurlencode($code) : '');
    $qr = qr_code_svg_data_uri($url);
    $photo = trim((string)($profile['photo_path'] ?? ''));
    if ($photo !== '' && str_starts_with($photo, '/')) {
        $photo = $base . $photo;
    }
    [$from, $to, $accent] = match ($theme) {
        'warm' => ['#7b3156', '#e27a5f', '#ffe0b5'],
        'light' => ['#264653', '#68b0ab', '#e9fff9'],
        default => ['#083f69', '#19a0ae', '#b9f3ef'],
    };
    $xml = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $textX = $photo !== '' ? 300 : 80;
    $photoSvg = $photo !== '' ? '<defs><clipPath id="photo"><circle cx="170" cy="245" r="92"/></clipPath></defs><image x="78" y="153" width="184" height="184" preserveAspectRatio="xMidYMid slice" clip-path="url(#photo)" href="' . $xml($photo) . '"/><circle cx="170" cy="245" r="96" fill="none" stroke="#fff" stroke-width="7" opacity=".9"/>' : '';
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630" viewBox="0 0 1200 630">'
        . '<defs><linearGradient id="g" x2="1" y2="1"><stop stop-color="' . $from . '"/><stop offset="1" stop-color="' . $to . '"/></linearGradient></defs>'
        . '<rect width="1200" height="630" rx="44" fill="url(#g)"/><circle cx="1030" cy="90" r="250" fill="#fff" opacity=".08"/>'
        . $photoSvg
        . '<text x="80" y="105" fill="' . $accent . '" font-family="Arial" font-size="30" font-weight="700">SWPro</text>'
        . '<text x="' . $textX . '" y="245" fill="#fff" font-family="Arial" font-size="58" font-weight="700">' . $xml($name) . '</text>'
        . '<text x="' . $textX . '" y="305" fill="#e8fbfb" font-family="Arial" font-size="30">' . $xml($subtitle) . '</text>'
        . '<text x="80" y="500" fill="#fff" font-family="Arial" font-size="27">Чек-ап · материалы · персональная поддержка</text>'
        . '<text x="80" y="550" fill="#c8eeee" font-family="Arial" font-size="23">' . $xml($url) . '</text>'
        . '<rect x="870" y="290" width="250" height="250" rx="26" fill="#fff"/><image x="885" y="305" width="220" height="220" href="' . $xml($qr) . '"/>'
        . '</svg>';
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

$profile = ai_studio_owner_profile($owner);
$sources = ai_manual_sources('client', $owner, (string)$admin['role']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? 'create');
    if (in_array($action, ['queue_voice', 'queue_video'], true)) {
        $draftStmt = db()->prepare('SELECT * FROM ai_content_drafts WHERE id = :id AND owner_type = :owner_type AND owner_id = :owner_id LIMIT 1');
        $draftStmt->execute(['id' => (int)($_POST['id'] ?? 0)] + $owner);
        $draft = $draftStmt->fetch();
        if (!$draft) {
            $errors[] = 'Черновик не найден.';
        } elseif ($action === 'queue_voice') {
            $provider = (string)(ai_setting('ai.voice_provider', 'disabled') ?: 'disabled');
            $voiceMode = ($_POST['voice_mode'] ?? 'standard') === 'cloned' ? 'cloned' : 'standard';
            $voiceId = null;
            if ($voiceMode === 'cloned' && in_array($owner['owner_type'], ['reseller', 'manager'], true)) {
                $voiceStmt = db()->prepare('SELECT voice_id FROM ai_avatars WHERE owner_type = :owner_type AND owner_id = :owner_id AND consent_confirmed_at IS NOT NULL AND status = "approved" ORDER BY version DESC LIMIT 1');
                $voiceStmt->execute($owner);
                $voiceId = $voiceStmt->fetchColumn() ?: null;
            }
            if (!in_array($owner['owner_type'], ['reseller', 'manager'], true) || $owner['owner_id'] <= 0) {
                $errors[] = 'Голос создаётся только от имени конкретного лидера или консультанта.';
            } elseif (empty($access['voice'])) {
                $errors[] = 'Голосовые AI-сообщения не входят в текущую подписку.';
            } elseif ($provider === 'disabled') {
                $errors[] = 'Сначала подключите голосового провайдера в настройках ИИ.';
            } elseif ($voiceMode === 'cloned' && $voiceId === null) {
                $errors[] = 'Для клонированного голоса нужен утверждённый аватар, отдельное согласие и созданный провайдером голос.';
            } else {
                $stmt = db()->prepare('INSERT INTO ai_voice_jobs (owner_type, owner_id, voice_mode, voice_id, script_text, script_hash, provider, status) VALUES (:owner_type, :owner_id, :voice_mode, :voice_id, :script, :hash, :provider, "queued")');
                $stmt->execute($owner + ['voice_mode' => $voiceMode, 'voice_id' => $voiceId, 'script' => (string)$draft['content'], 'hash' => hash('sha256', (string)$draft['content']), 'provider' => $provider]);
                redirect('ai_studio.php?success=voice_queued');
            }
        } else {
            $provider = (string)(ai_setting('ai.video_provider', 'disabled') ?: 'disabled');
            $avatarStmt = db()->prepare('SELECT id FROM ai_avatars WHERE owner_type = :owner_type AND owner_id = :owner_id AND status = "approved" ORDER BY version DESC LIMIT 1');
            $avatarStmt->execute($owner);
            $avatarId = (int)$avatarStmt->fetchColumn();
            if (empty($access['video'])) {
                $errors[] = 'AI-видео не входит в текущую подписку.';
            } elseif ($provider === 'disabled') {
                $errors[] = 'Сначала подключите видеопровайдера в настройках ИИ.';
            } elseif ($avatarId <= 0) {
                $errors[] = 'Сначала создайте и утвердите AI-аватар.';
            } else {
                $stmt = db()->prepare('INSERT INTO ai_video_jobs (avatar_id, purpose, personalization_level, script_text, script_hash, provider, status) VALUES (:avatar_id, "material", "general", :script, :hash, :provider, "queued")');
                $stmt->execute(['avatar_id' => $avatarId, 'script' => (string)$draft['content'], 'hash' => hash('sha256', (string)$draft['content']), 'provider' => $provider]);
                redirect('ai_studio.php?success=video_queued');
            }
        }
    }
    if ($action === 'archive') {
        $stmt = db()->prepare('UPDATE ai_content_drafts SET status = "archived" WHERE id = :id AND owner_type = :owner_type AND owner_id = :owner_id');
        $stmt->execute(['id' => (int)($_POST['id'] ?? 0)] + $owner);
        redirect('ai_studio.php?success=archived');
    }
    if ($action === 'save') {
        $stmt = db()->prepare('UPDATE ai_content_drafts SET title = :title, content = :content, status = :status WHERE id = :id AND owner_type = :owner_type AND owner_id = :owner_id');
        $status = in_array($_POST['status'] ?? '', ['draft','approved','used'], true) ? $_POST['status'] : 'draft';
        $stmt->execute(['title' => trim((string)($_POST['title'] ?? '')), 'content' => trim((string)($_POST['content'] ?? '')), 'status' => $status, 'id' => (int)($_POST['id'] ?? 0)] + $owner);
        redirect('ai_studio.php?success=saved');
    }
    if ($action === 'create') {
        $type = in_array($_POST['draft_type'] ?? '', ['post','video_script','greeting','campaign','product_description','voice_script'], true) ? $_POST['draft_type'] : 'post';
        $sourceKey = (string)($_POST['source_key'] ?? '');
        $occasion = trim((string)($_POST['occasion'] ?? ''));
        $source = null;
        foreach ($sources as $candidate) {
            if ((string)$candidate['source_key'] === $sourceKey) {
                $source = $candidate;
                break;
            }
        }
        if (!$source && $occasion === '') {
            $errors[] = 'Выберите утверждённый материал или повод.';
        } else {
            $name = trim((string)($profile['display_name'] ?? $profile['name'] ?? 'ваш консультант'));
            $facts = trim((string)($source['content'] ?? ''));
            $subject = $occasion !== '' ? $occasion : (string)($source['title'] ?? 'Полезный материал');
            $content = match ($type) {
                'greeting' => 'Здравствуйте! ' . $subject . ".\n\n" . $facts . "\n\nС теплом, " . $name,
                'campaign' => $subject . ".\n\n" . $facts . "\n\nЕсли тема вам актуальна, напишите — помогу разобраться подробнее.",
                'video_script' => 'Здравствуйте! Сегодня коротко поговорим о теме «' . $subject . '». ' . $facts . ' Если появились вопросы, напишите мне.',
                'voice_script' => 'Здравствуйте! Хочу поделиться важной информацией. ' . $facts . ' Если захотите обсудить это лично, я на связи.',
                default => $subject . "\n\n" . $facts . "\n\nМатериал подготовлен по утверждённой базе SWPro. Задайте вопрос, если хотите узнать больше.",
            };
            $insert = db()->prepare('INSERT INTO ai_content_drafts (owner_type, owner_id, draft_type, occasion, source_type, source_id, title, content, status, created_by) VALUES (:owner_type, :owner_id, :draft_type, :occasion, :source_type, :source_id, :title, :content, "draft", :created_by)');
            $insert->execute($owner + ['draft_type' => $type, 'occasion' => $occasion ?: null, 'source_type' => $source ? 'knowledge' : 'occasion', 'source_id' => $source ? (int)substr((string)$source['source_key'], strlen('knowledge:')) : null, 'title' => $subject, 'content' => $content, 'created_by' => (int)$admin['id']]);
            redirect('ai_studio.php?success=created');
        }
    }
}

$stmt = db()->prepare('SELECT * FROM ai_content_drafts WHERE owner_type = :owner_type AND owner_id = :owner_id AND status <> "archived" ORDER BY updated_at DESC, id DESC LIMIT 100');
$stmt->execute($owner);
$drafts = $stmt->fetchAll();
$seasonal = match ((int)date('n')) {
    12, 1 => 'Зимнее поздравление и забота о ежедневном самочувствии',
    2, 3 => 'Начало весны: мягко возвращаем полезные привычки',
    4, 5 => 'Весеннее обновление и внимание к своему состоянию',
    6, 7, 8 => 'Летний режим, вода, отдых и поддержка энергии',
    default => 'Осенний ритм и возвращение к регулярной заботе о себе',
};
$cardUris = ['ocean' => ai_studio_card_uri($profile, 'ocean'), 'warm' => ai_studio_card_uri($profile, 'warm'), 'light' => ai_studio_card_uri($profile, 'light')];
require __DIR__ . '/../app/views/layouts/header.php';
?>
<div class="page-title-row"><div><h1>AI-студия</h1><p class="cell-muted">Черновики публикаций, сценариев, кампаний и персональные материалы на основе утверждённых данных.</p></div></div>
<?php if (isset($_GET['success'])): ?><div class="notice success"><?= h(match ((string)$_GET['success']) { 'voice_queued' => 'Голосовое сообщение поставлено в очередь.', 'video_queued' => 'Видео поставлено в очередь.', default => 'Изменения сохранены.' }) ?></div><?php endif; ?>
<?php foreach ($errors as $error): ?><div class="alert"><?= h($error) ?></div><?php endforeach; ?>

<div class="two-columns">
<section class="panel form-panel"><h2>Подготовить черновик</h2><form method="post" class="crud-form"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="create">
    <label class="field"><span>Формат</span><select name="draft_type"><option value="post">Пост для соцсетей</option><option value="campaign">Кампания/рассылка</option><option value="greeting">Поздравление</option><option value="video_script">Сценарий видео</option><option value="voice_script">Сценарий голосового сообщения</option><option value="product_description">Описание продукта</option></select></label>
    <label class="field"><span>Утверждённый источник</span><select name="source_key"><option value="">Без источника — только повод</option><?php foreach ($sources as $source): ?><option value="<?= h((string)$source['source_key']) ?>"><?= h((string)$source['title']) ?></option><?php endforeach; ?></select></label>
    <label class="field wide"><span>Повод или тема</span><input name="occasion" value="<?= h($seasonal) ?>"></label>
    <div class="form-actions"><button>Создать проверяемый черновик</button></div>
</form><div class="form-actions"><a class="button secondary-button" href="crud.php?module=broadcasts">Открыть рассылки и сегменты</a></div><p class="cell-muted">Студия не публикует и не рассылает материалы автоматически.</p></section>

<section class="panel"><h2>Персональные карточки</h2><?php foreach ($cardUris as $cardTheme => $cardUri): ?><div style="margin-bottom:16px"><img src="<?= h($cardUri) ?>" alt="Персональная карточка" style="width:100%;border-radius:14px"><div class="form-actions"><a class="button secondary-button" href="<?= h($cardUri) ?>" download="swpro-card-<?= h($cardTheme) ?>.svg">Скачать вариант</a></div></div><?php endforeach; ?><p class="cell-muted">Карточки содержат персональную ссылку и QR-код. Подходят как визитка, OG-превью и заготовка для соцсетей.</p></section>
</div>

<?php if ($drafts): ?><section class="panel"><h2>Черновики</h2>
<?php foreach ($drafts as $draft): ?><details class="faq-manage-item"><summary><strong><?= h((string)$draft['title']) ?></strong><span class="cell-muted"><?= h((string)$draft['draft_type']) ?> · <?= h((string)$draft['status']) ?></span></summary>
<form method="post" class="crud-form"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$draft['id'] ?>"><label class="field wide"><span>Название</span><input name="title" value="<?= h((string)$draft['title']) ?>"></label><label class="field wide"><span>Текст</span><textarea name="content" rows="9"><?= h((string)$draft['content']) ?></textarea></label><label class="field"><span>Статус</span><select name="status"><?php foreach (['draft'=>'Черновик','approved'=>'Проверено','used'=>'Использовано'] as $key=>$label): ?><option value="<?= h($key) ?>" <?= $draft['status'] === $key ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></label><div class="form-actions"><button>Сохранить</button></div></form>
<div class="form-actions">
<?php if ($draft['draft_type'] === 'voice_script'): ?><form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="queue_voice"><input type="hidden" name="id" value="<?= (int)$draft['id'] ?>"><select name="voice_mode"><option value="standard">Стандартный голос</option><option value="cloned">Мой голос</option></select><button>Создать голосовое сообщение</button></form><?php endif; ?>
<?php if (in_array($draft['draft_type'], ['video_script','greeting'], true)): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="queue_video"><input type="hidden" name="id" value="<?= (int)$draft['id'] ?>"><button>Создать видео</button></form><?php endif; ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="archive"><input type="hidden" name="id" value="<?= (int)$draft['id'] ?>"><button class="link-button danger">В архив</button></form></div>
</details><?php endforeach; ?></section><?php endif; ?>
<?php require __DIR__ . '/../app/views/layouts/footer.php'; ?>
