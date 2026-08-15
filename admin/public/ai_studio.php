<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/permissions.php';
require_once __DIR__ . '/../app/core/ai_center.php';
require_once __DIR__ . '/../app/core/ai_jobs.php';
require_once __DIR__ . '/../app/core/qrcode.php';
require_once __DIR__ . '/../app/core/live_chat.php';

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

function ai_studio_photo_uri(string $photo, string $base): string
{
    $photo = trim($photo);
    if ($photo === '' || str_starts_with($photo, 'data:image/')) {
        return $photo;
    }
    if (str_starts_with($photo, '/admin/uploads/')) {
        $uploadsRoot = realpath(dirname(__DIR__) . '/uploads');
        $localPath = realpath(dirname(__DIR__) . substr($photo, strlen('/admin')));
        if ($uploadsRoot && $localPath && is_file($localPath)
            && str_starts_with($localPath, $uploadsRoot . DIRECTORY_SEPARATOR)) {
            $mime = function_exists('mime_content_type') ? (string)mime_content_type($localPath) : '';
            if ($mime === '' || $mime === 'application/octet-stream') {
                $mime = match (strtolower((string)pathinfo($localPath, PATHINFO_EXTENSION))) {
                    'jpg', 'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'gif' => 'image/gif',
                    'webp' => 'image/webp',
                    'avif' => 'image/avif',
                    default => '',
                };
            }
            if (in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'], true)) {
                $contents = file_get_contents($localPath);
                if ($contents !== false) {
                    return 'data:' . $mime . ';base64,' . base64_encode($contents);
                }
            }
        }
    }
    return str_starts_with($photo, '/') ? $base . $photo : $photo;
}

function ai_studio_card_payload(array $profile): array
{
    $name = trim((string)($profile['display_name'] ?? $profile['name'] ?? 'SWPro'));
    $subtitle = trim((string)($profile['subtitle'] ?? $profile['title'] ?? 'Ваш персональный консультант SWPro'));
    $base = rtrim((string)(getenv('SWPRO_PUBLIC_URL') ?: 'https://swpro.ru'), '/');
    $code = trim((string)($profile['referral_code'] ?? ''));
    $url = $base . ($code !== '' ? '/?ref=' . rawurlencode($code) : '');
    return [
        'name' => $name !== '' ? $name : 'SWPro',
        'subtitle' => $subtitle !== '' ? $subtitle : 'Ваш персональный консультант SWPro',
        'referral_code' => $code,
        'url' => $url,
        'photo' => ai_studio_photo_uri((string)($profile['photo_path'] ?? ''), $base),
        'qr' => qr_code_svg_data_uri($url),
    ];
}

$profile = ai_studio_owner_profile($owner);
$sources = ai_manual_sources('client', $owner, (string)$admin['role']);
$sourceResellerId = $owner['owner_type'] === 'reseller' ? (int)$owner['owner_id'] : 0;
if ($owner['owner_type'] === 'manager') {
    $sourceOwnerStmt = db()->prepare('SELECT reseller_id FROM managers WHERE id = :id LIMIT 1');
    $sourceOwnerStmt->execute(['id' => (int)$owner['owner_id']]);
    $sourceResellerId = (int)$sourceOwnerStmt->fetchColumn();
}
foreach (ai_client_sources(['id' => 0, 'reseller_id' => $sourceResellerId], $owner) as $automaticSource) {
    $sources[] = $automaticSource;
}
$sourcesByKey = [];
foreach ($sources as $sourceItem) {
    $sourcesByKey[(string)$sourceItem['source_key']] = $sourceItem;
}
$sources = array_values($sourcesByKey);
$clientWhere = match ($owner['owner_type']) {
    'manager' => ['sql' => 'manager_id = :owner_id', 'params' => ['owner_id' => $owner['owner_id']]],
    'reseller' => ['sql' => 'reseller_id = :owner_id', 'params' => ['owner_id' => $owner['owner_id']]],
    default => ['sql' => '1 = 0', 'params' => []],
};
$clientStmt = db()->prepare('SELECT id, first_name, last_name, gender, birth_date, age_years, city FROM end_users WHERE ' . $clientWhere['sql'] . ' AND onboarding_completed_at IS NOT NULL AND merged_into_user_id IS NULL ORDER BY first_name, last_name, id LIMIT 500');
$clientStmt->execute($clientWhere['params']);
$clients = $clientStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? 'create');
    if ($action === 'send_voice') {
        $voiceStmt = db()->prepare('SELECT * FROM ai_voice_jobs WHERE id = :id AND owner_type = :owner_type AND owner_id = :owner_id LIMIT 1');
        $voiceStmt->execute(['id' => (int)($_POST['id'] ?? 0)] + $owner);
        $voiceJob = $voiceStmt->fetch();
        if (!$voiceJob || (string)$voiceJob['status'] !== 'ready') {
            $errors[] = 'Голосовое сообщение ещё не готово.';
        } elseif (empty($voiceJob['end_user_id'])) {
            $errors[] = 'Голосовое сообщение не привязано к клиенту. Создайте персональный сценарий заново.';
        } else {
            try {
                $deliveryUrl = ai_voice_delivery_url($voiceJob);
                $result = live_chat_send_client(
                    $admin,
                    (int)$voiceJob['end_user_id'],
                    'Для вас голосовое сообщение. Аудио создано с помощью ИИ.',
                    (string)($_POST['channel'] ?? ''),
                    [$deliveryUrl]
                );
                if (empty($result['ok'])) {
                    ai_revoke_voice_delivery_url($deliveryUrl);
                    db()->prepare('UPDATE ai_voice_jobs SET delivery_error = :error WHERE id = :id')->execute([
                        'error' => mb_substr((string)($result['error'] ?? 'Не удалось отправить сообщение.'), 0, 1000, 'UTF-8'),
                        'id' => (int)$voiceJob['id'],
                    ]);
                    $errors[] = (string)($result['error'] ?? 'Не удалось отправить голосовое сообщение.');
                } else {
                    db()->prepare('UPDATE ai_voice_jobs SET sent_at = NOW(), delivery_channel = :channel, delivery_error = NULL, chat_message_id = :message_id WHERE id = :id')->execute([
                        'channel' => (string)($result['channel'] ?? 'web'),
                        'message_id' => (int)($result['message_id'] ?? 0) ?: null,
                        'id' => (int)$voiceJob['id'],
                    ]);
                    if (!empty($voiceJob['draft_id'])) {
                        db()->prepare('UPDATE ai_content_drafts SET status = "used" WHERE id = :id')->execute(['id' => (int)$voiceJob['draft_id']]);
                    }
                    log_activity('admin', (int)$admin['id'], 'send_ai_voice', 'ai_voice_jobs', (int)$voiceJob['id'], [
                        'end_user_id' => (int)$voiceJob['end_user_id'],
                        'channel' => (string)($result['channel'] ?? 'web'),
                    ]);
                    redirect('ai_studio.php?success=voice_sent#voice-' . (int)$voiceJob['id']);
                }
            } catch (Throwable $error) {
                $errors[] = 'Не удалось отправить голосовое сообщение: ' . $error->getMessage();
            }
        }
    }
    if ($action === 'send_draft') {
        $draftStmt = db()->prepare('SELECT * FROM ai_content_drafts WHERE id = :id AND owner_type = :owner_type AND owner_id = :owner_id LIMIT 1');
        $draftStmt->execute(['id' => (int)($_POST['id'] ?? 0)] + $owner);
        $draft = $draftStmt->fetch();
        if (!$draft || empty($draft['end_user_id'])) {
            $errors[] = 'Этот материал не привязан к клиенту.';
        } else {
            $result = live_chat_send_client($admin, (int)$draft['end_user_id'], (string)$draft['content']);
            if (empty($result['ok'])) {
                $errors[] = (string)($result['error'] ?? 'Не удалось отправить сообщение.');
            } else {
                db()->prepare('UPDATE ai_content_drafts SET status = "used" WHERE id = :id')->execute(['id' => (int)$draft['id']]);
                log_activity('admin', (int)$admin['id'], 'send_ai_draft', 'ai_content_drafts', (int)$draft['id'], ['channel' => $result['channel'] ?? null]);
                redirect('ai_studio.php?success=sent&draft=' . (int)$draft['id'] . '#draft-' . (int)$draft['id']);
            }
        }
    }
    if (in_array($action, ['queue_voice', 'queue_video'], true)) {
        $draftStmt = db()->prepare('SELECT * FROM ai_content_drafts WHERE id = :id AND owner_type = :owner_type AND owner_id = :owner_id LIMIT 1');
        $draftStmt->execute(['id' => (int)($_POST['id'] ?? 0)] + $owner);
        $draft = $draftStmt->fetch();
        if (!$draft) {
            $errors[] = 'Черновик не найден.';
        } elseif ($action === 'queue_voice') {
            $provider = (string)(ai_setting('ai.voice_provider', 'disabled') ?: 'disabled');
            $voiceMode = 'standard';
            $estimatedSeconds = ai_estimated_audio_seconds((string)$draft['content']);
            if (!in_array($owner['owner_type'], ['reseller', 'manager'], true) || $owner['owner_id'] <= 0) {
                $errors[] = 'Голос создаётся только от имени конкретного лидера или консультанта.';
            } elseif (empty($access['voice'])) {
                $errors[] = 'Голосовые AI-сообщения не входят в текущую подписку.';
            } elseif ((string)$draft['status'] !== 'approved') {
                $errors[] = 'Сначала проверьте текст, выберите статус «Проверено» и сохраните черновик.';
            } elseif ($provider !== 'openai') {
                $errors[] = 'Для создания голоса выберите OpenAI Voice в настройках ИИ.';
            } elseif (ai_setting('ai.external_processing_enabled', '0') !== '1' || ai_setting('ai.voice_external_enabled', '0') !== '1') {
                $errors[] = 'Синтез голоса через OpenAI выключен супер-администратором.';
            } elseif (empty($_POST['external_voice_confirm'])) {
                $errors[] = 'Подтвердите отправку именно этого проверенного текста в OpenAI для создания аудио.';
            } elseif ($access['voice_limit'] !== null && ai_monthly_usage($owner, 'voice') + $estimatedSeconds > (int)$access['voice_limit']) {
                $errors[] = 'Месячный лимит голосовых сообщений исчерпан.';
            } else {
                $stmt = db()->prepare('INSERT INTO ai_voice_jobs (owner_type, owner_id, end_user_id, draft_id, voice_mode, voice_id, script_text, script_hash, provider, status) VALUES (:owner_type, :owner_id, :end_user_id, :draft_id, :voice_mode, :voice_id, :script, :hash, :provider, "queued")');
                $stmt->execute($owner + ['end_user_id' => !empty($draft['end_user_id']) ? (int)$draft['end_user_id'] : null, 'draft_id' => (int)$draft['id'], 'voice_mode' => $voiceMode, 'voice_id' => null, 'script' => (string)$draft['content'], 'hash' => hash('sha256', (string)$draft['content']), 'provider' => $provider]);
                try {
                    $voiceJobId = (int)db()->lastInsertId();
                    ai_process_voice_job($voiceJobId, $owner);
                    redirect('ai_studio.php?success=voice_ready#voice-' . $voiceJobId);
                } catch (Throwable $error) {
                    $errors[] = 'Не удалось создать голосовое сообщение: ' . $error->getMessage();
                }
            }
        } else {
            $provider = (string)(ai_setting('ai.video_provider', 'disabled') ?: 'disabled');
            $avatarStmt = db()->prepare('SELECT id, provider_avatar_id, voice_id FROM ai_avatars WHERE owner_type = :owner_type AND owner_id = :owner_id AND status = "approved" ORDER BY version DESC LIMIT 1');
            $avatarStmt->execute($owner);
            $avatar = $avatarStmt->fetch() ?: null;
            $avatarId = (int)($avatar['id'] ?? 0);
            $personalVideo = !empty($draft['end_user_id']);
            $eventType = $personalVideo ? 'personal_video' : 'video';
            $limitKey = $personalVideo ? 'personal_video_limit' : 'video_limit';
            $estimatedSeconds = ai_estimated_audio_seconds((string)$draft['content']);
            if (empty($access[$eventType])) {
                $errors[] = $personalVideo ? 'Персональное AI-видео не входит в текущую подписку.' : 'AI-видео не входит в текущую подписку.';
            } elseif ($provider === 'disabled') {
                $errors[] = 'Сначала подключите видеопровайдера в настройках ИИ.';
            } elseif (!ai_video_provider_configured($provider)) {
                $errors[] = 'На сервере не найден ключ выбранного видеопровайдера.';
            } elseif ($avatarId <= 0) {
                $errors[] = 'Сначала укажите внешний ID аватара в разделе «Мой AI-аватар».';
            } elseif ((string)$draft['status'] !== 'approved') {
                $errors[] = 'Сначала проверьте сценарий, выберите статус «Проверено» и сохраните черновик.';
            } elseif (empty($_POST['external_video_confirm'])) {
                $errors[] = 'Подтвердите отправку именно этого проверенного сценария видеопровайдеру.';
            } elseif ($access[$limitKey] !== null && ai_monthly_usage($owner, $eventType) + $estimatedSeconds > (int)$access[$limitKey]) {
                $errors[] = 'Месячный лимит AI-видео исчерпан.';
            } else {
                $stmt = db()->prepare('INSERT INTO ai_video_jobs (avatar_id, purpose, personalization_level, script_text, script_hash, provider, status) VALUES (:avatar_id, "material", "general", :script, :hash, :provider, "queued")');
                $stmt->execute(['avatar_id' => $avatarId, 'script' => (string)$draft['content'], 'hash' => hash('sha256', (string)$draft['content']), 'provider' => $provider]);
                $videoId = (int)db()->lastInsertId();
                if ($personalVideo) {
                    db()->prepare('UPDATE ai_video_jobs SET personalization_level = "personal", end_user_id = :user_id WHERE id = :id')->execute(['user_id' => (int)$draft['end_user_id'], 'id' => $videoId]);
                }
                try {
                    ai_submit_video_job($videoId, $owner);
                    redirect('ai_studio.php?success=video_queued');
                } catch (Throwable $error) {
                    db()->prepare('UPDATE ai_video_jobs SET status = "failed", error_text = :error, completed_at = NOW() WHERE id = :id')->execute(['error' => mb_substr($error->getMessage(), 0, 1000, 'UTF-8'), 'id' => $videoId]);
                    $errors[] = 'Не удалось отправить видео провайдеру: ' . $error->getMessage();
                }
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
        $clientId = max(0, (int)($_POST['end_user_id'] ?? 0));
        $personalization = [];
        if ($clientId > 0) {
            $selectedClient = null;
            foreach ($clients as $candidateClient) {
                if ((int)$candidateClient['id'] === $clientId) {
                    $selectedClient = $candidateClient;
                    break;
                }
            }
            if (!$selectedClient) {
                $errors[] = 'Выбранный клиент недоступен.';
            } else {
                $checkupStmt = db()->prepare('SELECT ts.title scale_title, uss.score, tsr.title result_title, tsr.summary_text, tsr.advice_text FROM user_test_sessions uts JOIN user_test_scale_scores uss ON uss.session_id = uts.id JOIN test_scales ts ON ts.id = uss.scale_id LEFT JOIN test_scale_results tsr ON tsr.id = uss.result_id WHERE uts.end_user_id = :user_id AND uts.completed_at IS NOT NULL AND uts.is_preview = 0 AND uts.id = (SELECT MAX(id) FROM user_test_sessions WHERE end_user_id = :latest_user AND completed_at IS NOT NULL AND is_preview = 0) ORDER BY uss.score DESC');
                $checkupStmt->execute(['user_id' => $clientId, 'latest_user' => $clientId]);
                $checkupLines = [];
                foreach ($checkupStmt->fetchAll() as $checkupRow) {
                    $checkupLines[] = implode(' — ', array_filter([(string)$checkupRow['scale_title'], (string)$checkupRow['score'] . ' баллов', (string)($checkupRow['result_title'] ?? ''), (string)($checkupRow['summary_text'] ?? ''), (string)($checkupRow['advice_text'] ?? '')]));
                }
                $calculatedAge = (int)($selectedClient['age_years'] ?? 0);
                if ($calculatedAge <= 0 && !empty($selectedClient['birth_date'])) {
                    $calculatedAge = date_diff(date_create((string)$selectedClient['birth_date']), date_create('today'))->y;
                }
                $personalization = [
                    'display_name' => trim((string)$selectedClient['first_name'] . ' ' . (string)$selectedClient['last_name']),
                    'gender' => match ((string)($selectedClient['gender'] ?? '')) { 'female' => 'женский', 'male' => 'мужской', default => '' },
                    'birth_date' => (string)($selectedClient['birth_date'] ?? ''),
                    'age' => $calculatedAge > 0 ? (string)$calculatedAge : '',
                    'city' => trim((string)($selectedClient['city'] ?? '')),
                    'checkup' => implode("\n", $checkupLines),
                ];
            }
        }
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
            $facts = trim((string)($source['content'] ?? ''));
            $subject = $occasion !== '' ? $occasion : (string)($source['title'] ?? 'Полезный материал');
            $useOpenAi = ai_setting('ai.external_processing_enabled', '0') === '1'
                && ai_setting('ai.studio_external_enabled', '0') === '1'
                && ai_openai_key_configured();
            if (!$useOpenAi) {
                $errors[] = 'OpenAI для AI-студии не подключён. Черновик не создан — локальные шаблоны больше не используются.';
            } else {
                try {
                    $generated = ai_openai_studio_draft($type, $subject, $facts, $personalization);
                    $content = trim((string)$generated['text']);
                    $provider = 'openai';
                    $model = (string)$generated['model'];
                    $inputTokens = (int)$generated['input_tokens'];
                    $outputTokens = (int)$generated['output_tokens'];
                } catch (Throwable $error) {
                    $errors[] = 'OpenAI не создал черновик: ' . $error->getMessage();
                }
            }
            if (!$errors) {
                $sourceType = 'occasion';
                $sourceId = null;
                if ($source) {
                    [$sourceType, $sourceRawId] = array_pad(explode(':', (string)$source['source_key'], 2), 2, '');
                    $sourceId = ctype_digit($sourceRawId) ? (int)$sourceRawId : null;
                }
                $insert = db()->prepare('INSERT INTO ai_content_drafts (owner_type, owner_id, end_user_id, draft_type, occasion, source_type, source_id, title, content, provider, model, input_tokens, output_tokens, status, created_by) VALUES (:owner_type, :owner_id, :end_user_id, :draft_type, :occasion, :source_type, :source_id, :title, :content, :provider, :model, :input_tokens, :output_tokens, "draft", :created_by)');
                $insert->execute($owner + ['end_user_id' => $clientId ?: null, 'draft_type' => $type, 'occasion' => $occasion ?: null, 'source_type' => $sourceType, 'source_id' => $sourceId, 'title' => $subject, 'content' => $content, 'provider' => $provider, 'model' => $model, 'input_tokens' => $inputTokens, 'output_tokens' => $outputTokens, 'created_by' => (int)$admin['id']]);
                $draftId = (int)db()->lastInsertId();
                db()->prepare('INSERT INTO ai_usage_events (owner_type, owner_id, admin_user_id, event_type, provider, model, metadata_json) VALUES (:owner_type, :owner_id, :admin_id, "studio", :provider, :model, :metadata)')->execute($owner + [
                    'admin_id' => (int)$admin['id'],
                    'provider' => $provider,
                    'model' => $model,
                    'metadata' => json_encode(['input_tokens' => $inputTokens, 'output_tokens' => $outputTokens], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
                redirect('ai_studio.php?success=created&draft=' . $draftId . '#draft-' . $draftId);
            }
        }
    }
}

$stmt = db()->prepare('SELECT * FROM ai_content_drafts WHERE owner_type = :owner_type AND owner_id = :owner_id AND status <> "archived" ORDER BY updated_at DESC, id DESC LIMIT 100');
$stmt->execute($owner);
$drafts = $stmt->fetchAll();
$voiceStmt = db()->prepare('SELECT j.id, j.model, j.duration_seconds, j.status, j.error_text, j.output_path, j.script_text, j.end_user_id, j.sent_at, j.delivery_channel, j.delivery_error, j.created_at, CONCAT_WS(" ", NULLIF(eu.first_name,""), NULLIF(eu.last_name,"")) client_name FROM ai_voice_jobs j LEFT JOIN end_users eu ON eu.id = j.end_user_id WHERE j.owner_type = :owner_type AND j.owner_id = :owner_id ORDER BY j.id DESC LIMIT 20');
$voiceStmt->execute($owner);
$voiceJobs = $voiceStmt->fetchAll();
$videoStmt = db()->prepare('SELECT j.id, j.duration_seconds, j.status, j.error_text, j.created_at, j.provider FROM ai_video_jobs j JOIN ai_avatars a ON a.id = j.avatar_id WHERE a.owner_type = :owner_type AND a.owner_id = :owner_id ORDER BY j.id DESC LIMIT 20');
$videoStmt->execute($owner);
$videoJobs = $videoStmt->fetchAll();
$seasonal = match ((int)date('n')) {
    12, 1 => 'Зимнее поздравление и забота о ежедневном самочувствии',
    2, 3 => 'Начало весны: мягко возвращаем полезные привычки',
    4, 5 => 'Весеннее обновление и внимание к своему состоянию',
    6, 7, 8 => 'Летний режим, вода, отдых и поддержка энергии',
    default => 'Осенний ритм и возвращение к регулярной заботе о себе',
};
$openAiReady = ai_setting('ai.external_processing_enabled', '0') === '1'
    && ai_setting('ai.studio_external_enabled', '0') === '1'
    && ai_openai_key_configured();
$selectedDraftId = max(0, (int)($_GET['draft'] ?? 0));
$cardPayload = ai_studio_card_payload($profile);
$voiceStudioEnabled = (string)ai_setting('ai.voice_provider', 'disabled') === 'openai'
    && ai_setting('ai.external_processing_enabled', '0') === '1'
    && ai_setting('ai.voice_external_enabled', '0') === '1'
    && !empty($access['voice']);
require __DIR__ . '/../app/views/layouts/header.php';
?>
<div class="page-title-row"><div><h1>AI-студия</h1><p class="cell-muted">Черновики публикаций, сценариев, кампаний и персональные материалы на основе утверждённых данных.</p></div></div>
<?php if (isset($_GET['success'])): ?><div class="notice success"><?= h(match ((string)$_GET['success']) { 'created' => 'OpenAI создал текст. Проверьте его ниже.', 'voice_ready' => 'Голосовое сообщение создано. Прослушайте и отправьте его клиенту.', 'voice_sent' => 'Голосовое сообщение отправлено клиенту и появилось в живом чате.', 'video_queued' => 'Видео поставлено в очередь.', 'sent' => 'Сообщение отправлено клиенту и появилось в живом чате.', default => 'Изменения сохранены.' }) ?></div><?php endif; ?>
<?php foreach ($errors as $error): ?><div class="alert"><?= h($error) ?></div><?php endforeach; ?>

<section class="panel ai-studio-create"><div class="ai-studio-heading"><div><h2>Создать материал с OpenAI</h2><p class="cell-muted">Выберите формат, источник и при необходимости клиента — готовый текст откроется сразу под этой формой.</p></div><span class="ai-status <?= $openAiReady ? 'is-ready' : 'is-offline' ?>"><?= $openAiReady ? 'OpenAI подключён' : 'OpenAI выключен' ?></span></div>
<div class="ai-studio-steps"><span><b>1</b> Выберите формат</span><span><b>2</b> Укажите тему</span><span><b>3</b> Получите и проверьте текст</span></div>
<form method="post" class="crud-form"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="create">
    <label class="field"><span>Формат</span><select name="draft_type" id="ai-draft-type"><option value="post">Пост для соцсетей</option><option value="campaign">Кампания/рассылка</option><option value="greeting">Поздравление</option><option value="video_script">Сценарий видео</option><?php if ($voiceStudioEnabled): ?><option value="voice_script">Сценарий голосового сообщения</option><?php endif; ?><option value="product_description">Описание продукта</option></select></label>
    <label class="field"><span>Утверждённый источник</span><select name="source_key"><option value="">Без источника — только повод</option><?php foreach ($sources as $source): ?><option value="<?= h((string)$source['source_key']) ?>"><?= h((string)$source['title']) ?></option><?php endforeach; ?></select></label>
    <label class="field" id="ai-client-personalization"><span>Персонализация для клиента</span><select name="end_user_id"><option value="">Без персонализации</option><?php foreach ($clients as $client): ?><option value="<?= (int)$client['id'] ?>"><?= h(trim((string)$client['first_name'] . ' ' . (string)$client['last_name']) ?: 'Клиент #' . (int)$client['id']) ?></option><?php endforeach; ?></select><small class="field-hint">Для личных поздравлений и сценариев сведения помогают подобрать содержание, но не перечисляются в тексте. Контакты, точный адрес, ID аккаунтов, логины и токены не передаются.</small></label>
    <label class="field" id="ai-occasion-preset-wrap"><span>Готовый повод</span><select id="ai-occasion-preset"><option value="">Написать свой</option><option value="Приветствие нового клиента">Приветствие нового клиента</option><option value="Благодарность за прохождение чек-апа и предложение обсудить результат">После чек-апа</option><option value="Тёплое поздравление с днём рождения">День рождения</option><option value="Доброжелательное напоминание вернуться к плану">Напоминание о плане</option><option value="Приглашение пройти повторный чек-ап и сравнить изменения">Повторный чек-ап</option></select></label>
    <label class="field wide"><span>Повод или тема</span><input name="occasion" id="ai-occasion" value="<?= h($seasonal) ?>"></label>
    <?php if ($openAiReady): ?><div class="notice wide">OpenAI получит тему, утверждённый материал и выбранные сведения для персонализации. Контакты, точный адрес, ID, логины и токены не передаются.</div><?php else: ?><div class="alert wide"><strong>Создание текста недоступно.</strong> Суперадминистратору нужно включить OpenAI для AI‑студии в настройках ИИ. Локальный текст вместо ИИ создаваться не будет.<?php if (($admin['role'] ?? '') === 'superadmin'): ?> <a href="ai_settings.php#openai-studio-access">Открыть настройку</a>.<?php endif; ?></div><?php endif; ?>
    <div class="form-actions"><button <?= $openAiReady ? '' : 'disabled' ?>>Создать текст с OpenAI</button><a class="button secondary-button" href="crud.php?module=broadcasts">Рассылки и сегменты</a></div>
</form><p class="cell-muted">Ничего не публикуется и не рассылается автоматически.</p></section>
<script>
(() => {
    const type = document.getElementById('ai-draft-type');
    const field = document.getElementById('ai-client-personalization');
    const select = field?.querySelector('select');
    const preset = document.getElementById('ai-occasion-preset');
    const occasion = document.getElementById('ai-occasion');
    if (!type || !field || !select) return;
    const update = () => {
        const isPublic = type.value === 'post' || type.value === 'product_description';
        field.hidden = isPublic;
        select.disabled = isPublic;
        if (isPublic) select.value = '';
    };
    type.addEventListener('change', update);
    preset?.addEventListener('change', () => { if (preset.value && occasion) occasion.value = preset.value; });
    update();
})();
</script>

<?php if ($drafts): ?><section class="panel ai-studio-results"><h2><?= $selectedDraftId > 0 ? 'Готовый текст' : 'Созданные тексты' ?></h2><p class="cell-muted">Проверьте текст, при необходимости исправьте его и сохраните. Последний созданный материал открыт автоматически.</p>
<?php foreach ($drafts as $draft): $draftOpen = $selectedDraftId > 0 ? (int)$draft['id'] === $selectedDraftId : $draft === $drafts[0]; ?><details id="draft-<?= (int)$draft['id'] ?>" class="faq-manage-item ai-draft-item" <?= $draftOpen ? 'open' : '' ?>><summary><strong><?= h((string)$draft['title']) ?></strong><span class="cell-muted"><?= h((string)$draft['draft_type']) ?> · <?= h((string)$draft['status']) ?> · <?= h((string)($draft['provider'] ?? 'swpro')) ?><?= !empty($draft['model']) ? ' · ' . h((string)$draft['model']) : '' ?></span></summary>
<form method="post" class="crud-form"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$draft['id'] ?>"><label class="field wide"><span>Название</span><input name="title" value="<?= h((string)$draft['title']) ?>"></label><label class="field wide"><span>Текст</span><textarea name="content" rows="9"><?= h((string)$draft['content']) ?></textarea></label><label class="field"><span>Статус</span><select name="status"><?php foreach (['draft'=>'Черновик','approved'=>'Проверено','used'=>'Использовано'] as $key=>$label): ?><option value="<?= h($key) ?>" <?= $draft['status'] === $key ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></label><div class="form-actions"><button>Сохранить</button></div></form>
<?php if (!empty($draft['end_user_id'])): ?><div class="form-actions ai-draft-send-actions"><form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="send_draft"><input type="hidden" name="id" value="<?= (int)$draft['id'] ?>"><button type="submit">Отправить клиенту</button></form><a class="button secondary-button" href="index.php?chat_user_id=<?= (int)$draft['end_user_id'] ?>#live-chat">Открыть чат</a><small class="field-hint">Перед отправкой сохраните исправленный текст.</small></div><?php endif; ?>
<div class="form-actions">
<?php if ($draft['draft_type'] === 'voice_script' && $voiceStudioEnabled): ?><form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="queue_voice"><input type="hidden" name="id" value="<?= (int)$draft['id'] ?>"><label class="check-row"><input type="checkbox" name="external_voice_confirm" value="1"><span>Я проверил текст и разрешаю отправить его в OpenAI для создания аудио</span></label><button>Создать голосовое сообщение</button></form><?php elseif ($draft['draft_type'] === 'voice_script'): ?><small class="field-hint">Создание голоса отключено в настройках ИИ.</small><?php endif; ?>
<?php if (in_array($draft['draft_type'], ['video_script','greeting'], true)): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="queue_video"><input type="hidden" name="id" value="<?= (int)$draft['id'] ?>"><label class="check-row"><input type="checkbox" name="external_video_confirm" value="1"><span>Я проверил сценарий и разрешаю отправить его подключённому видеопровайдеру</span></label><button>Создать видео</button></form><?php endif; ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="archive"><input type="hidden" name="id" value="<?= (int)$draft['id'] ?>"><button class="link-button danger">В архив</button></form></div>
</details><?php endforeach; ?></section><?php endif; ?>

<?php if ($voiceJobs): ?><section class="panel ai-voice-results"><div class="ai-studio-heading"><div><h2>Голосовые сообщения</h2><p class="cell-muted">Прослушайте результат и отправьте его выбранному клиенту. Файл также можно скачать.</p></div></div>
<?php foreach ($voiceJobs as $job):
    $voiceExtension = strtolower((string)pathinfo((string)($job['output_path'] ?? ''), PATHINFO_EXTENSION));
    $voiceDownloadLabel = $voiceExtension === 'ogg' ? 'Скачать OGG' : 'Скачать MP3';
    $clientLabel = trim((string)($job['client_name'] ?? '')) ?: (!empty($job['end_user_id']) ? 'Клиент #' . (int)$job['end_user_id'] : 'Клиент не выбран');
?>
<article id="voice-<?= (int)$job['id'] ?>" class="faq-manage-item ai-voice-item">
    <div class="ai-voice-item-heading"><div><strong><?= h($clientLabel) ?></strong><small><?= h(date('d.m.Y H:i', strtotime((string)$job['created_at']))) ?> · <?= h((string)$job['status']) ?><?= !empty($job['duration_seconds']) ? ' · ' . h((string)$job['duration_seconds']) . ' сек.' : '' ?></small></div><?php if (!empty($job['sent_at'])): ?><span class="badge badge-sent">Отправлено <?= h(date('d.m.Y H:i', strtotime((string)$job['sent_at']))) ?> · <?= h((string)$job['delivery_channel']) ?></span><?php endif; ?></div>
    <?php if ($job['status'] === 'ready'): ?>
        <p><?= nl2br(h((string)$job['script_text'])) ?></p>
        <audio controls preload="none" src="ai_voice_media.php?id=<?= (int)$job['id'] ?>"></audio>
        <div class="form-actions ai-voice-actions">
            <?php if (!empty($job['end_user_id'])): ?><form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="send_voice"><input type="hidden" name="id" value="<?= (int)$job['id'] ?>"><label class="field"><span>Канал</span><select name="channel"><option value="">Автоматически</option><option value="telegram">Telegram</option><option value="VK">VK</option><option value="MAX">MAX</option><option value="web">Web-чат</option></select></label><button type="submit">Отправить клиенту</button></form><a class="button secondary-button" href="index.php?chat_user_id=<?= (int)$job['end_user_id'] ?>#live-chat">Открыть чат</a><?php else: ?><span class="notice">Чтобы отправить голос, сначала создайте персональный сценарий с выбранным клиентом.</span><?php endif; ?>
            <a class="button secondary-button" href="ai_voice_media.php?id=<?= (int)$job['id'] ?>" download><?= h($voiceDownloadLabel) ?></a>
        </div>
        <?php if (!empty($job['delivery_error'])): ?><div class="alert"><?= h((string)$job['delivery_error']) ?></div><?php endif; ?>
    <?php elseif (!empty($job['error_text'])): ?><div class="alert"><?= h((string)$job['error_text']) ?></div><?php endif; ?>
</article>
<?php endforeach; ?></section><?php endif; ?>

<section class="panel ai-card-section"><div class="ai-studio-heading"><div><h2>Карточки для отправки</h2><p class="cell-muted">Готовые изображения с вашей ссылкой и QR‑кодом. Их можно скачать как PNG или сразу отправить с телефона.</p></div></div>
<div class="ai-card-controls">
<details class="ai-card-picker" id="ai-theme-picker"><summary><span class="ai-theme-preview theme-ocean" id="ai-theme-current-preview"></span><span><small>Фон</small><strong id="ai-theme-current-label">Океан</strong></span></summary>
<div class="ai-card-picker-menu ai-card-themes" role="group" aria-label="Фон карточек">
    <button type="button" class="ai-card-theme is-active" data-card-theme="ocean" aria-pressed="true"><span class="ai-theme-preview theme-ocean"></span><span><strong>Океан</strong><small>Основной фирменный</small></span></button>
    <button type="button" class="ai-card-theme" data-card-theme="pearl" aria-pressed="false"><span class="ai-theme-preview theme-pearl"></span><span><strong>Жемчуг</strong><small>Светлый и спокойный</small></span></button>
    <button type="button" class="ai-card-theme" data-card-theme="botanical" aria-pressed="false"><span class="ai-theme-preview theme-botanical"></span><span><strong>Ботаника</strong><small>Натуральный зелёный</small></span></button>
    <button type="button" class="ai-card-theme" data-card-theme="berry" aria-pressed="false"><span class="ai-theme-preview theme-berry"></span><span><strong>Ягода</strong><small>Тёплый выразительный</small></span></button>
    <button type="button" class="ai-card-theme" data-card-theme="wellness" aria-pressed="false"><span class="ai-theme-preview theme-wellness"></span><span><strong>Свежесть</strong><small>Светлый wellness</small></span></button>
    <button type="button" class="ai-card-theme" data-card-theme="alpine" aria-pressed="false"><span class="ai-theme-preview theme-alpine"></span><span><strong>Алтай</strong><small>Горы и золото</small></span></button>
    <button type="button" class="ai-card-theme" data-card-theme="technology" aria-pressed="false"><span class="ai-theme-preview theme-technology"></span><span><strong>Технологии</strong><small>Синий 3D-стиль</small></span></button>
    <button type="button" class="ai-card-theme" data-card-theme="herbarium" aria-pressed="false"><span class="ai-theme-preview theme-herbarium"></span><span><strong>Гербарий</strong><small>Зелень и золото</small></span></button>
    <button type="button" class="ai-card-theme" data-card-theme="sunlight" aria-pressed="false"><span class="ai-theme-preview theme-sunlight"></span><span><strong>Солнечный</strong><small>Тёплый светлый</small></span></button>
</div></details>
<details class="ai-card-picker" id="ai-layout-picker"><summary><span class="ai-layout-preview layout-classic" id="ai-layout-current-preview"><i></i><b></b><em></em></span><span><small>Компоновка</small><strong id="ai-layout-current-label">Классика</strong></span></summary>
<div class="ai-card-picker-menu ai-card-layouts" role="group" aria-label="Компоновка карточек">
    <button type="button" class="ai-card-layout is-active" data-card-layout="classic" aria-pressed="true"><span class="ai-layout-preview layout-classic"><i></i><b></b><em></em></span><span><strong>Классика</strong><small>Фото слева, QR справа</small></span></button>
    <button type="button" class="ai-card-layout" data-card-layout="portrait" aria-pressed="false"><span class="ai-layout-preview layout-portrait"><i></i><b></b><em></em></span><span><strong>Фото крупно</strong><small>Акцент на специалисте</small></span></button>
    <button type="button" class="ai-card-layout" data-card-layout="columns" aria-pressed="false"><span class="ai-layout-preview layout-columns"><i></i><b></b><em></em></span><span><strong>Две колонки</strong><small>Строгая визитка</small></span></button>
    <button type="button" class="ai-card-layout" data-card-layout="centered" aria-pressed="false"><span class="ai-layout-preview layout-centered"><i></i><b></b><em></em></span><span><strong>По центру</strong><small>Симметричный вариант</small></span></button>
    <button type="button" class="ai-card-layout" data-card-layout="minimal" aria-pressed="false"><span class="ai-layout-preview layout-minimal"><i></i><b></b><em></em></span><span><strong>Минимализм</strong><small>Только самое важное</small></span></button>
</div></details>
</div>
<div class="ai-card-grid">
    <article class="ai-card-item"><div><h3>Горизонтальная визитка</h3><p class="cell-muted">Для сообщений, публикаций и превью ссылки.</p></div><canvas id="ai-card-wide" data-variant="wide" aria-label="Горизонтальная персональная карточка"></canvas><div class="form-actions"><button type="button" class="secondary-button ai-card-download" data-canvas="ai-card-wide" data-name="swpro-card.png" disabled>Скачать PNG</button><button type="button" class="secondary-button ai-card-share" data-canvas="ai-card-wide" data-name="swpro-card.png" disabled>Поделиться</button></div></article>
    <article class="ai-card-item"><div><h3>Вертикальная карточка</h3><p class="cell-muted">Для историй и публикаций в мобильных соцсетях.</p></div><canvas id="ai-card-story" data-variant="story" aria-label="Вертикальная персональная карточка"></canvas><div class="form-actions"><button type="button" class="secondary-button ai-card-download" data-canvas="ai-card-story" data-name="swpro-story.png" disabled>Скачать PNG</button><button type="button" class="secondary-button ai-card-share" data-canvas="ai-card-story" data-name="swpro-story.png" disabled>Поделиться</button></div></article>
</div></section>
<?php if ($videoJobs): ?><section class="panel"><h2>AI-видео</h2><p class="cell-muted">Видео создано искусственным интеллектом. Обработка у провайдера может занять несколько минут.</p><?php foreach ($videoJobs as $job): ?><div class="faq-manage-item"><strong><?= h(date('d.m.Y H:i', strtotime((string)$job['created_at']))) ?></strong> · <?= h((string)$job['provider']) ?> · <?= h((string)$job['status']) ?><?php if ($job['status'] === 'ready'): ?><div><video controls preload="metadata" style="max-width:640px;width:100%" src="ai_video_media.php?id=<?= (int)$job['id'] ?>"></video><br><a class="button secondary-button" href="ai_video_media.php?id=<?= (int)$job['id'] ?>" download>Скачать MP4</a></div><?php elseif (!empty($job['error_text'])): ?><div class="alert"><?= h((string)$job['error_text']) ?></div><?php endif; ?></div><?php endforeach; ?></section><?php endif; ?>
<script>
(() => {
    const profile = <?= json_encode($cardPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const fileBase = String(profile.referral_code || 'SWPro').trim().replace(/[^a-zA-Z0-9_-]+/g, '-').replace(/^-+|-+$/g, '') || 'SWPro';
    document.querySelectorAll('[data-canvas="ai-card-wide"]').forEach(button => { button.dataset.name = `${fileBase}.png`; });
    document.querySelectorAll('[data-canvas="ai-card-story"]').forEach(button => { button.dataset.name = `${fileBase}-story.png`; });
    const loadImage = (src) => new Promise((resolve) => {
        if (!src) return resolve(null);
        const image = new Image();
        if (!src.startsWith('data:')) image.crossOrigin = 'anonymous';
        image.onload = () => resolve(image);
        image.onerror = () => resolve(null);
        image.src = src;
    });
    const roundRect = (ctx, x, y, width, height, radius) => {
        ctx.beginPath();
        ctx.roundRect(x, y, width, height, radius);
    };
    const coverImage = (ctx, image, x, y, width, height) => {
        const scale = Math.max(width / image.width, height / image.height);
        const sourceWidth = width / scale;
        const sourceHeight = height / scale;
        ctx.drawImage(image, (image.width - sourceWidth) / 2, (image.height - sourceHeight) / 2, sourceWidth, sourceHeight, x, y, width, height);
    };
    const fitText = (ctx, text, maxWidth, maxLines) => {
        const words = String(text || '').trim().split(/\s+/).filter(Boolean);
        const lines = [];
        let line = '';
        for (const word of words) {
            const candidate = line ? `${line} ${word}` : word;
            if (ctx.measureText(candidate).width <= maxWidth) {
                line = candidate;
            } else {
                if (line) lines.push(line);
                line = word;
                if (lines.length === maxLines) break;
            }
        }
        if (line && lines.length < maxLines) lines.push(line);
        if (lines.length === maxLines && words.join(' ') !== lines.join(' ')) {
            while (lines[maxLines - 1].length > 2 && ctx.measureText(lines[maxLines - 1] + '…').width > maxWidth) {
                lines[maxLines - 1] = lines[maxLines - 1].slice(0, -1);
            }
            lines[maxLines - 1] = lines[maxLines - 1].trim() + '…';
        }
        return lines;
    };
    const drawLines = (ctx, lines, x, y, lineHeight) => lines.forEach((line, index) => ctx.fillText(line, x, y + index * lineHeight));
    const themes = {
        ocean: {text: '#ffffff', muted: '#d8f7f3', accent: '#b9fff2', dark: '#073b5c', panel: '#ffffff', stops: ['#063a5a', '#087481', '#27a9a2']},
        pearl: {text: '#172a39', muted: '#526872', accent: '#9b365e', dark: '#792747', panel: '#ffffff', stops: ['#fffaf2', '#f5eadb', '#e9d5cb']},
        botanical: {text: '#fffdf5', muted: '#dcebd9', accent: '#f4d79b', dark: '#244b37', panel: '#fffdf8', stops: ['#173e35', '#356c4d', '#78966b']},
        berry: {text: '#ffffff', muted: '#ffe5ec', accent: '#ffd5a7', dark: '#742b4a', panel: '#fffafc', stops: ['#672340', '#9d3d63', '#d16d72']},
        wellness: {text: '#1d392d', muted: '#4e685d', accent: '#4f8e25', dark: '#315d26', panel: '#ffffff', image: 'assets/img/cards/wellness-light.webp', overlay: 'rgba(255,255,255,.72)'},
        alpine: {text: '#fff9e8', muted: '#eadbb8', accent: '#e4b84f', dark: '#214735', panel: '#fffdf5', image: 'assets/img/cards/alpine-gold.webp', overlay: 'rgba(5,45,35,.76)'},
        technology: {text: '#ffffff', muted: '#cceaff', accent: '#9df14d', dark: '#073f82', panel: '#ffffff', image: 'assets/img/cards/tech-blue.webp', overlay: 'rgba(3,38,96,.72)'},
        herbarium: {text: '#234233', muted: '#52685d', accent: '#a97422', dark: '#31523f', panel: '#fffdf7', image: 'assets/img/cards/botanical-gold.webp', overlay: 'rgba(255,252,242,.76)'},
        sunlight: {text: '#623b34', muted: '#865f55', accent: '#bf654d', dark: '#914a3d', panel: '#fffdf9', image: 'assets/img/cards/warm-glow.webp', overlay: 'rgba(255,250,243,.72)'},
    };
    const themeBackgrounds = {};
    let selectedTheme = localStorage.getItem('swpro.cardTheme');
    if (!themes[selectedTheme]) selectedTheme = 'ocean';
    const layouts = ['classic', 'portrait', 'columns', 'centered', 'minimal'];
    let selectedLayout = localStorage.getItem('swpro.cardLayout');
    if (!layouts.includes(selectedLayout)) selectedLayout = 'classic';
    const theme = () => themes[selectedTheme];
    const drawBackground = (ctx, width, height) => {
        const colors = theme();
        if (colors.image) {
            if (themeBackgrounds[selectedTheme]) coverImage(ctx, themeBackgrounds[selectedTheme], 0, 0, width, height);
            else { ctx.fillStyle = colors.panel; ctx.fillRect(0, 0, width, height); }
            return;
        }
        const gradient = ctx.createLinearGradient(0, 0, width, height);
        gradient.addColorStop(0, colors.stops[0]);
        gradient.addColorStop(.58, colors.stops[1]);
        gradient.addColorStop(1, colors.stops[2]);
        ctx.fillStyle = gradient;
        ctx.fillRect(0, 0, width, height);
        ctx.save();
        if (selectedTheme === 'ocean') {
            ctx.globalAlpha = .1; ctx.fillStyle = '#fff';
            ctx.beginPath(); ctx.arc(width * .91, height * .08, width * .3, 0, Math.PI * 2); ctx.fill();
            ctx.globalAlpha = .18; ctx.strokeStyle = '#b9fff2'; ctx.lineWidth = Math.max(3, width / 320);
            ctx.beginPath(); ctx.moveTo(-40, height * .78); ctx.bezierCurveTo(width * .28, height * .58, width * .55, height * .98, width + 40, height * .72); ctx.stroke();
        } else if (selectedTheme === 'pearl') {
            ctx.globalAlpha = .5; ctx.fillStyle = '#ffffff';
            ctx.beginPath(); ctx.arc(width * .86, height * .08, width * .3, 0, Math.PI * 2); ctx.fill();
            ctx.globalAlpha = .25; ctx.fillStyle = '#a23f66';
            ctx.beginPath(); ctx.arc(width * .05, height * .96, width * .22, 0, Math.PI * 2); ctx.fill();
            ctx.globalAlpha = .6; ctx.strokeStyle = '#c69b55'; ctx.lineWidth = Math.max(3, width / 330);
            ctx.beginPath(); ctx.moveTo(width * .05, height * .16); ctx.bezierCurveTo(width * .34, height * .02, width * .64, height * .3, width * .96, height * .13); ctx.stroke();
        } else if (selectedTheme === 'botanical') {
            ctx.globalAlpha = .16; ctx.strokeStyle = '#f4d79b'; ctx.lineWidth = Math.max(3, width / 360);
            for (let i = 0; i < 4; i++) {
                const x = width * (.73 + i * .07), y = height * (.12 + i * .12);
                ctx.beginPath(); ctx.moveTo(x, y + height * .28); ctx.quadraticCurveTo(x + width * .05, y + height * .1, x + width * .02, y); ctx.stroke();
                ctx.beginPath(); ctx.ellipse(x - width * .018, y + height * .12, width * .035, height * .07, -.6, 0, Math.PI * 2); ctx.stroke();
                ctx.beginPath(); ctx.ellipse(x + width * .04, y + height * .19, width * .035, height * .07, .6, 0, Math.PI * 2); ctx.stroke();
            }
        } else {
            ctx.globalAlpha = .12; ctx.fillStyle = '#fff';
            ctx.beginPath(); ctx.arc(width * .86, height * .04, width * .32, 0, Math.PI * 2); ctx.fill();
            ctx.globalAlpha = .13; ctx.fillStyle = '#ffd5a7';
            ctx.beginPath(); ctx.arc(width * .04, height * .98, width * .28, 0, Math.PI * 2); ctx.fill();
            ctx.globalAlpha = .25; ctx.strokeStyle = '#ffd5a7'; ctx.lineWidth = Math.max(3, width / 340);
            ctx.beginPath(); ctx.moveTo(width * .58, -20); ctx.bezierCurveTo(width * .48, height * .28, width * .75, height * .46, width * .62, height + 20); ctx.stroke();
        }
        ctx.restore();
    };
    const ellipsize = (ctx, value, maxWidth) => {
        let text = String(value || '');
        if (ctx.measureText(text).width <= maxWidth) return text;
        while (text.length > 2 && ctx.measureText(text + '…').width > maxWidth) text = text.slice(0, -1);
        return text.trim() + '…';
    };
    const drawQrPanel = (ctx, qr, x, y, width, height, qrSize, label) => {
        const colors = theme();
        roundRect(ctx, x, y, width, height, Math.round(width * .11)); ctx.fillStyle = colors.panel; ctx.fill();
        if (qr) ctx.drawImage(qr, x + (width - qrSize) / 2, y + 24, qrSize, qrSize);
        ctx.fillStyle = colors.dark; ctx.font = `700 ${Math.max(18, Math.round(width * .075))}px Arial, sans-serif`; ctx.textAlign = 'center';
        ctx.fillText(label, x + width / 2, y + height - 26); ctx.textAlign = 'left';
    };
    const drawContentPanel = (ctx, x, y, width, height, radius = 28) => {
        const colors = theme();
        if (!colors.image) return;
        roundRect(ctx, x, y, width, height, radius);
        ctx.fillStyle = colors.overlay;
        ctx.fill();
    };
    const drawPhotoCircle = (ctx, photo, cx, cy, radius, colors) => {
        if (!photo) return;
        ctx.save(); ctx.beginPath(); ctx.arc(cx, cy, radius, 0, Math.PI * 2); ctx.clip(); coverImage(ctx, photo, cx - radius, cy - radius, radius * 2, radius * 2); ctx.restore();
        ctx.strokeStyle = colors.panel; ctx.lineWidth = Math.max(7, radius * .07); ctx.beginPath(); ctx.arc(cx, cy, radius + 3, 0, Math.PI * 2); ctx.stroke();
    };
    const drawPhotoRect = (ctx, photo, x, y, width, height, radius, colors) => {
        if (!photo) return;
        ctx.save(); roundRect(ctx, x, y, width, height, radius); ctx.clip(); coverImage(ctx, photo, x, y, width, height); ctx.restore();
        roundRect(ctx, x, y, width, height, radius); ctx.strokeStyle = colors.panel; ctx.lineWidth = 8; ctx.stroke();
    };
    const drawNameBlock = (ctx, colors, x, y, maxWidth, fontSize, align = 'left') => {
        ctx.textAlign = align; ctx.fillStyle = colors.text; ctx.font = `700 ${fontSize}px Arial, sans-serif`;
        const nameLines = fitText(ctx, profile.name, maxWidth, 2); drawLines(ctx, nameLines, x, y, fontSize + 8);
        const subtitleY = y + nameLines.length * (fontSize + 8) + 10;
        ctx.fillStyle = colors.muted; ctx.font = `${Math.max(24, Math.round(fontSize * .52))}px Arial, sans-serif`;
        const subtitleLines = fitText(ctx, profile.subtitle, maxWidth, 2); drawLines(ctx, subtitleLines, x, subtitleY, Math.max(34, Math.round(fontSize * .7)));
        ctx.textAlign = 'left';
        return subtitleY + subtitleLines.length * Math.max(34, Math.round(fontSize * .7));
    };
    const drawFeatures = (ctx, colors, x, y, maxWidth, centered = false) => {
        ctx.textAlign = centered ? 'center' : 'left'; ctx.fillStyle = colors.text; ctx.font = '700 25px Arial, sans-serif';
        drawLines(ctx, fitText(ctx, 'Чек‑ап • полезные материалы • личная поддержка', maxWidth, 2), x, y, 32); ctx.textAlign = 'left';
    };
    const drawUrl = (ctx, colors, x, y, maxWidth, centered = false, fontSize = 22) => {
        ctx.textAlign = centered ? 'center' : 'left'; ctx.fillStyle = colors.muted; ctx.font = `${fontSize}px Arial, sans-serif`; ctx.fillText(ellipsize(ctx, profile.url, maxWidth), x, y); ctx.textAlign = 'left';
    };
    const renderWide = (canvas, photo, qr) => {
        canvas.width = 1200; canvas.height = 630;
        const ctx = canvas.getContext('2d');
        drawBackground(ctx, canvas.width, canvas.height);
        const colors = theme();
        ctx.fillStyle = colors.accent; ctx.font = '700 30px Arial, sans-serif'; ctx.fillText('SWPro', 70, 72);
        if (selectedLayout === 'portrait') {
            drawContentPanel(ctx, 45, 90, 1110, 500);
            drawPhotoRect(ctx, photo, 75, 120, 310, 400, 35, colors);
            drawNameBlock(ctx, colors, 430, 175, 420, profile.name.length > 28 ? 41 : 47);
            drawFeatures(ctx, colors, 430, 385, 410);
            drawUrl(ctx, colors, 430, 500, 410);
            drawQrPanel(ctx, qr, 875, 130, 250, 335, 205, 'Открыть сайт');
        } else if (selectedLayout === 'columns') {
            drawContentPanel(ctx, 45, 90, 630, 500);
            drawPhotoCircle(ctx, photo, 180, 235, 112, colors);
            drawNameBlock(ctx, colors, 330, 190, 300, profile.name.length > 25 ? 38 : 44);
            drawFeatures(ctx, colors, 90, 430, 535);
            drawUrl(ctx, colors, 90, 550, 535);
            drawQrPanel(ctx, qr, 760, 120, 355, 430, 285, 'Открыть сайт');
        } else if (selectedLayout === 'centered') {
            drawContentPanel(ctx, 45, 90, 1110, 500);
            drawPhotoCircle(ctx, photo, 390, 225, 118, colors);
            drawQrPanel(ctx, qr, 655, 85, 270, 350, 220, 'Открыть сайт');
            drawNameBlock(ctx, colors, 600, 485, 920, profile.name.length > 28 ? 40 : 47, 'center');
            drawUrl(ctx, colors, 600, 580, 850, true, 20);
        } else if (selectedLayout === 'minimal') {
            drawContentPanel(ctx, 45, 90, 805, 430);
            drawPhotoCircle(ctx, photo, 180, 260, 125, colors);
            drawNameBlock(ctx, colors, 350, 215, 455, profile.name.length > 28 ? 42 : 50);
            drawUrl(ctx, colors, 350, 405, 440);
            drawQrPanel(ctx, qr, 880, 105, 270, 360, 220, 'Открыть сайт');
        } else {
            drawContentPanel(ctx, 45, 105, 800, 300);
            drawContentPanel(ctx, 45, 450, 800, 125, 24);
            drawPhotoCircle(ctx, photo, 165, 240, 98, colors);
            drawNameBlock(ctx, colors, 305, 205, 520, profile.name.length > 28 ? 43 : 50);
            drawFeatures(ctx, colors, 70, 485, 760);
            drawUrl(ctx, colors, 70, 550, 760);
            drawQrPanel(ctx, qr, 880, 115, 260, 345, 210, 'Открыть сайт');
        }
    };
    const renderStory = (canvas, photo, qr) => {
        canvas.width = 1080; canvas.height = 1350;
        const ctx = canvas.getContext('2d');
        drawBackground(ctx, canvas.width, canvas.height);
        const colors = theme();
        ctx.fillStyle = colors.accent; ctx.font = '700 42px Arial, sans-serif'; ctx.fillText('SWPro', 80, 92);
        if (selectedLayout === 'portrait') {
            drawContentPanel(ctx, 45, 115, 990, 1110);
            drawPhotoRect(ctx, photo, 70, 135, 940, 430, 38, colors);
            drawNameBlock(ctx, colors, 80, 665, 900, profile.name.length > 28 ? 48 : 56);
            drawFeatures(ctx, colors, 80, 850, 440);
            drawQrPanel(ctx, qr, 605, 750, 365, 430, 280, 'Наведите камеру');
            drawUrl(ctx, colors, 80, 1170, 480);
        } else if (selectedLayout === 'columns') {
            drawContentPanel(ctx, 45, 115, 990, 1110);
            drawPhotoRect(ctx, photo, 75, 155, 410, 520, 36, colors);
            drawNameBlock(ctx, colors, 75, 770, 410, profile.name.length > 25 ? 43 : 50);
            drawUrl(ctx, colors, 75, 1120, 410, false, 20);
            drawQrPanel(ctx, qr, 590, 220, 390, 465, 305, 'Наведите камеру');
            drawFeatures(ctx, colors, 590, 800, 390);
        } else if (selectedLayout === 'centered') {
            drawContentPanel(ctx, 45, 115, 990, 1110);
            drawPhotoCircle(ctx, photo, 540, 290, 145, colors);
            drawNameBlock(ctx, colors, 540, 510, 850, profile.name.length > 28 ? 48 : 56, 'center');
            drawQrPanel(ctx, qr, 355, 735, 370, 440, 285, 'Наведите камеру');
            drawUrl(ctx, colors, 540, 1240, 850, true, 22);
        } else if (selectedLayout === 'minimal') {
            drawContentPanel(ctx, 45, 115, 990, 1110);
            drawPhotoCircle(ctx, photo, 185, 285, 125, colors);
            drawNameBlock(ctx, colors, 365, 245, 610, profile.name.length > 28 ? 46 : 54);
            drawQrPanel(ctx, qr, 300, 610, 480, 535, 390, 'Наведите камеру');
            drawUrl(ctx, colors, 540, 1235, 850, true, 22);
        } else {
            drawContentPanel(ctx, 45, 115, 990, 300);
            drawContentPanel(ctx, 50, 465, 480, 435);
            drawContentPanel(ctx, 80, 1070, 890, 140);
            drawPhotoCircle(ctx, photo, 185, 260, 112, colors);
            drawNameBlock(ctx, colors, 350, 230, 620, profile.name.length > 28 ? 48 : 56);
            ctx.fillStyle = colors.text; ctx.font = '700 36px Arial, sans-serif'; drawLines(ctx, fitText(ctx, 'Всё полезное — в одном месте', 430, 2), 80, 525, 45);
            ctx.fillStyle = colors.muted; ctx.font = '28px Arial, sans-serif'; drawLines(ctx, ['Чек‑ап ощущений', 'Полезные материалы', 'Личная поддержка'], 80, 655, 56);
            drawQrPanel(ctx, qr, 590, 500, 390, 455, 305, 'Наведите камеру');
            drawUrl(ctx, colors, 120, 1172, 800);
        }
    };
    const toBlob = (canvas) => new Promise((resolve, reject) => canvas.toBlob(blob => blob ? resolve(blob) : reject(new Error('PNG не создан')), 'image/png', 1));
    const saveBlob = (blob, name) => { const url = URL.createObjectURL(blob); const link = document.createElement('a'); link.href = url; link.download = name; link.click(); setTimeout(() => URL.revokeObjectURL(url), 1000); };
    let loadedPhoto = null, loadedQr = null;
    const renderCards = () => {
        document.querySelectorAll('canvas[data-variant]').forEach(canvas => canvas.dataset.variant === 'story' ? renderStory(canvas, loadedPhoto, loadedQr) : renderWide(canvas, loadedPhoto, loadedQr));
    };
    const selectTheme = (name) => {
        if (!themes[name]) return;
        selectedTheme = name;
        localStorage.setItem('swpro.cardTheme', name);
        document.querySelectorAll('.ai-card-theme').forEach(button => {
            const active = button.dataset.cardTheme === name;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        const selected = document.querySelector(`.ai-card-theme[data-card-theme="${name}"]`);
        const preview = document.getElementById('ai-theme-current-preview');
        if (preview) preview.className = `ai-theme-preview theme-${name}`;
        if (selected) document.getElementById('ai-theme-current-label').textContent = selected.querySelector('strong').textContent;
        renderCards();
    };
    const selectLayout = (name) => {
        if (!layouts.includes(name)) return;
        selectedLayout = name;
        localStorage.setItem('swpro.cardLayout', name);
        document.querySelectorAll('.ai-card-layout').forEach(button => {
            const active = button.dataset.cardLayout === name;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        const selected = document.querySelector(`.ai-card-layout[data-card-layout="${name}"]`);
        const preview = document.getElementById('ai-layout-current-preview');
        if (preview) preview.className = `ai-layout-preview layout-${name}`;
        if (selected) document.getElementById('ai-layout-current-label').textContent = selected.querySelector('strong').textContent;
        renderCards();
    };
    document.querySelectorAll('.ai-card-theme').forEach(button => button.addEventListener('click', () => { selectTheme(button.dataset.cardTheme); document.getElementById('ai-theme-picker').removeAttribute('open'); }));
    document.querySelectorAll('.ai-card-layout').forEach(button => button.addEventListener('click', () => { selectLayout(button.dataset.cardLayout); document.getElementById('ai-layout-picker').removeAttribute('open'); }));
    document.querySelectorAll('.ai-card-picker').forEach(picker => picker.addEventListener('toggle', () => {
        if (!picker.open) return;
        document.querySelectorAll('.ai-card-picker[open]').forEach(other => { if (other !== picker) other.removeAttribute('open'); });
    }));
    selectTheme(selectedTheme);
    selectLayout(selectedLayout);
    const imageThemeEntries = Object.entries(themes).filter(([, value]) => value.image);
    Promise.all([loadImage(profile.photo), loadImage(profile.qr), ...imageThemeEntries.map(([, value]) => loadImage(value.image))]).then(([photo, qr, ...backgrounds]) => {
        imageThemeEntries.forEach(([name], index) => { themeBackgrounds[name] = backgrounds[index]; });
        loadedPhoto = photo; loadedQr = qr; renderCards();
        document.querySelectorAll('.ai-card-download, .ai-card-share').forEach(button => button.disabled = false);
    });
    document.querySelectorAll('.ai-card-download').forEach(button => button.addEventListener('click', async () => saveBlob(await toBlob(document.getElementById(button.dataset.canvas)), button.dataset.name)));
    document.querySelectorAll('.ai-card-share').forEach(button => {
        if (!navigator.share || !window.File) { button.hidden = true; return; }
        button.addEventListener('click', async () => {
            const blob = await toBlob(document.getElementById(button.dataset.canvas));
            const file = new File([blob], button.dataset.name, {type: 'image/png'});
            if (navigator.canShare && !navigator.canShare({files: [file]})) return saveBlob(blob, button.dataset.name);
            try { await navigator.share({title: 'Моя страница SWPro', files: [file]}); } catch (error) { if (error.name !== 'AbortError') saveBlob(blob, button.dataset.name); }
        });
    });
})();
</script>
<?php require __DIR__ . '/../app/views/layouts/footer.php'; ?>
