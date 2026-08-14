<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/permissions.php';
require_once __DIR__ . '/../app/core/ai_center.php';
require_once __DIR__ . '/../app/core/ai_jobs.php';
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

function ai_studio_text_lines(string $text, int $maxChars, int $maxLines = 2): array
{
    $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $lines = [];
    $line = '';
    $truncated = false;
    foreach ($words as $word) {
        $candidate = $line === '' ? $word : $line . ' ' . $word;
        if (mb_strlen($candidate, 'UTF-8') <= $maxChars) {
            $line = $candidate;
            continue;
        }
        if ($line !== '') {
            $lines[] = $line;
        }
        $line = $word;
        if (count($lines) >= $maxLines) {
            $truncated = true;
            break;
        }
    }
    if ($line !== '' && count($lines) < $maxLines) {
        $lines[] = mb_substr($line, 0, $maxChars, 'UTF-8');
        $truncated = $truncated || mb_strlen($line, 'UTF-8') > $maxChars;
    } elseif ($line !== '') {
        $truncated = true;
    }
    if ($truncated && $lines) {
        $last = count($lines) - 1;
        $lines[$last] = rtrim(mb_substr($lines[$last], 0, max(1, $maxChars - 1), 'UTF-8')) . '…';
    }
    return $lines ?: ['SWPro'];
}

function ai_studio_card_uri(array $profile, string $theme = 'ocean'): string
{
    $name = trim((string)($profile['display_name'] ?? $profile['name'] ?? 'SWPro'));
    $subtitle = trim((string)($profile['subtitle'] ?? $profile['title'] ?? 'Ваш персональный консультант'));
    $base = rtrim((string)(getenv('SWPRO_PUBLIC_URL') ?: 'https://swpro.ru'), '/');
    $code = trim((string)($profile['referral_code'] ?? ''));
    $url = $base . ($code !== '' ? '/?ref=' . rawurlencode($code) : '');
    $qr = qr_code_svg_data_uri($url);
    $photo = ai_studio_photo_uri((string)($profile['photo_path'] ?? ''), $base);
    [$from, $to, $accent] = match ($theme) {
        'warm' => ['#7b3156', '#e27a5f', '#ffe0b5'],
        'light' => ['#264653', '#68b0ab', '#e9fff9'],
        default => ['#083f69', '#19a0ae', '#b9f3ef'],
    };
    $xml = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $textX = $photo !== '' ? 310 : 80;
    $nameLines = ai_studio_text_lines($name, $photo !== '' ? 27 : 38, 2);
    $subtitleLines = ai_studio_text_lines($subtitle, $photo !== '' ? 42 : 55, 2);
    $nameSize = count($nameLines) > 1 ? 43 : 54;
    $nameY = count($nameLines) > 1 ? 180 : 215;
    $nameSvg = '<text x="' . $textX . '" y="' . $nameY . '" fill="#fff" font-family="Arial, sans-serif" font-size="' . $nameSize . '" font-weight="700">';
    foreach ($nameLines as $index => $line) {
        $nameSvg .= '<tspan x="' . $textX . '" dy="' . ($index === 0 ? 0 : 54) . '">' . $xml($line) . '</tspan>';
    }
    $nameSvg .= '</text>';
    $subtitleY = $nameY + (count($nameLines) * 54) + 18;
    $subtitleSvg = '<text x="' . $textX . '" y="' . $subtitleY . '" fill="#e8fbfb" font-family="Arial, sans-serif" font-size="27">';
    foreach ($subtitleLines as $index => $line) {
        $subtitleSvg .= '<tspan x="' . $textX . '" dy="' . ($index === 0 ? 0 : 36) . '">' . $xml($line) . '</tspan>';
    }
    $subtitleSvg .= '</text>';
    $photoSvg = $photo !== '' ? '<image x="82" y="150" width="176" height="176" preserveAspectRatio="xMidYMid slice" clip-path="url(#photo)" href="' . $xml($photo) . '"/><circle cx="170" cy="238" r="92" fill="none" stroke="#fff" stroke-width="7" opacity=".92"/>' : '';
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630" viewBox="0 0 1200 630">'
        . '<defs><linearGradient id="g" x2="1" y2="1"><stop stop-color="' . $from . '"/><stop offset="1" stop-color="' . $to . '"/></linearGradient><clipPath id="photo"><circle cx="170" cy="238" r="88"/></clipPath></defs>'
        . '<rect width="1200" height="630" rx="44" fill="url(#g)"/><circle cx="1050" cy="70" r="270" fill="#fff" opacity=".08"/>'
        . $photoSvg
        . '<text x="80" y="95" fill="' . $accent . '" font-family="Arial, sans-serif" font-size="30" font-weight="700">SWPro</text>'
        . $nameSvg . $subtitleSvg
        . '<text x="80" y="500" fill="#fff" font-family="Arial, sans-serif" font-size="25" font-weight="700">Персональная страница</text>'
        . '<text x="80" y="545" fill="#d8f5f3" font-family="Arial, sans-serif" font-size="22">' . $xml($url) . '</text>'
        . '<rect x="915" y="330" width="225" height="225" rx="28" fill="#fff"/><image x="930" y="345" width="195" height="195" href="' . $xml($qr) . '"/>'
        . '<text x="1027" y="585" text-anchor="middle" fill="#fff" font-family="Arial, sans-serif" font-size="20">Открыть мини-сайт</text>'
        . '</svg>';
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
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
                $stmt = db()->prepare('INSERT INTO ai_voice_jobs (owner_type, owner_id, voice_mode, voice_id, script_text, script_hash, provider, status) VALUES (:owner_type, :owner_id, :voice_mode, :voice_id, :script, :hash, :provider, "queued")');
                $stmt->execute($owner + ['voice_mode' => $voiceMode, 'voice_id' => null, 'script' => (string)$draft['content'], 'hash' => hash('sha256', (string)$draft['content']), 'provider' => $provider]);
                try {
                    ai_process_voice_job((int)db()->lastInsertId(), $owner);
                    redirect('ai_studio.php?success=voice_ready');
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
            $name = trim((string)($profile['display_name'] ?? $profile['name'] ?? 'ваш консультант'));
            $facts = trim((string)($source['content'] ?? ''));
            $subject = $occasion !== '' ? $occasion : (string)($source['title'] ?? 'Полезный материал');
            $provider = 'swpro';
            $model = 'verified-template';
            $inputTokens = null;
            $outputTokens = null;
            $content = match ($type) {
                'greeting' => 'Здравствуйте! ' . $subject . ".\n\n" . $facts . "\n\nС теплом, " . $name,
                'campaign' => $subject . ".\n\n" . $facts . "\n\nЕсли тема вам актуальна, напишите — помогу разобраться подробнее.",
                'video_script' => 'Здравствуйте! Сегодня коротко поговорим о теме «' . $subject . '». ' . $facts . ' Если появились вопросы, напишите мне.',
                'voice_script' => 'Здравствуйте! Хочу поделиться важной информацией. ' . $facts . ' Если захотите обсудить это лично, я на связи.',
                default => $subject . "\n\n" . $facts . "\n\nМатериал подготовлен по утверждённой базе SWPro. Задайте вопрос, если хотите узнать больше.",
            };
            $useOpenAi = ai_setting('ai.external_processing_enabled', '0') === '1'
                && ai_setting('ai.studio_external_enabled', '0') === '1'
                && ai_openai_key_configured();
            if ($useOpenAi) {
                try {
                    $generated = ai_openai_studio_draft($type, $subject, $facts, $personalization);
                    $content = trim((string)$generated['text']);
                    if ($type === 'greeting' && $name !== '') {
                        $content .= "\n\nС теплом, " . $name;
                    }
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
                db()->prepare('INSERT INTO ai_usage_events (owner_type, owner_id, admin_user_id, event_type, provider, model, metadata_json) VALUES (:owner_type, :owner_id, :admin_id, "studio", :provider, :model, :metadata)')->execute($owner + [
                    'admin_id' => (int)$admin['id'],
                    'provider' => $provider,
                    'model' => $model,
                    'metadata' => json_encode(['input_tokens' => $inputTokens, 'output_tokens' => $outputTokens], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
                redirect('ai_studio.php?success=created');
            }
        }
    }
}

$stmt = db()->prepare('SELECT * FROM ai_content_drafts WHERE owner_type = :owner_type AND owner_id = :owner_id AND status <> "archived" ORDER BY updated_at DESC, id DESC LIMIT 100');
$stmt->execute($owner);
$drafts = $stmt->fetchAll();
$voiceStmt = db()->prepare('SELECT id, model, duration_seconds, status, error_text, created_at FROM ai_voice_jobs WHERE owner_type = :owner_type AND owner_id = :owner_id ORDER BY id DESC LIMIT 20');
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
$cardUris = ['ocean' => ai_studio_card_uri($profile, 'ocean'), 'warm' => ai_studio_card_uri($profile, 'warm'), 'light' => ai_studio_card_uri($profile, 'light')];
require __DIR__ . '/../app/views/layouts/header.php';
?>
<div class="page-title-row"><div><h1>AI-студия</h1><p class="cell-muted">Черновики публикаций, сценариев, кампаний и персональные материалы на основе утверждённых данных.</p></div></div>
<?php if (isset($_GET['success'])): ?><div class="notice success"><?= h(match ((string)$_GET['success']) { 'voice_ready' => 'Голосовое сообщение создано.', 'video_queued' => 'Видео поставлено в очередь.', default => 'Изменения сохранены.' }) ?></div><?php endif; ?>
<?php foreach ($errors as $error): ?><div class="alert"><?= h($error) ?></div><?php endforeach; ?>

<div class="two-columns">
<section class="panel form-panel"><h2>Подготовить черновик</h2><form method="post" class="crud-form"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="create">
    <label class="field"><span>Формат</span><select name="draft_type"><option value="post">Пост для соцсетей</option><option value="campaign">Кампания/рассылка</option><option value="greeting">Поздравление</option><option value="video_script">Сценарий видео</option><option value="voice_script">Сценарий голосового сообщения</option><option value="product_description">Описание продукта</option></select></label>
    <label class="field"><span>Утверждённый источник</span><select name="source_key"><option value="">Без источника — только повод</option><?php foreach ($sources as $source): ?><option value="<?= h((string)$source['source_key']) ?>"><?= h((string)$source['title']) ?></option><?php endforeach; ?></select></label>
    <label class="field"><span>Персонализация для клиента</span><select name="end_user_id"><option value="">Без персонализации</option><?php foreach ($clients as $client): ?><option value="<?= (int)$client['id'] ?>"><?= h(trim((string)$client['first_name'] . ' ' . (string)$client['last_name']) ?: 'Клиент #' . (int)$client['id']) ?></option><?php endforeach; ?></select><small class="field-hint">Передаются сведения профиля и содержание последнего чек-апа. Контакты, точный адрес, ID аккаунтов, логины и токены не передаются.</small></label>
    <label class="field wide"><span>Повод или тема</span><input name="occasion" value="<?= h($seasonal) ?>"></label>
    <?php if (ai_setting('ai.external_processing_enabled', '0') === '1' && ai_setting('ai.studio_external_enabled', '0') === '1' && ai_openai_key_configured()): ?><div class="notice wide">После нажатия тема и выбранный утверждённый материал будут отправлены в OpenAI. При персонализации также передаются имя, пол, возраст или дата рождения, город и содержание последнего чек-апа. Контакты, точный адрес, ID, логины и токены не передаются.</div><?php else: ?><div class="alert wide">OpenAI для студии пока выключен. Будет создан только локальный шаблон; включить настоящую генерацию может суперадминистратор в настройках ИИ.</div><?php endif; ?>
    <div class="form-actions"><button>Создать проверяемый черновик</button></div>
</form><div class="form-actions"><a class="button secondary-button" href="crud.php?module=broadcasts">Открыть рассылки и сегменты</a></div><p class="cell-muted">Студия не публикует и не рассылает материалы автоматически.</p></section>

<section class="panel"><h2>Персональные карточки</h2><?php foreach ($cardUris as $cardTheme => $cardUri): ?><div style="margin-bottom:16px"><img src="<?= h($cardUri) ?>" alt="Персональная карточка" style="width:100%;border-radius:14px"><div class="form-actions"><a class="button secondary-button" href="<?= h($cardUri) ?>" download="swpro-card-<?= h($cardTheme) ?>.svg">Скачать вариант</a></div></div><?php endforeach; ?><p class="cell-muted">Карточки содержат персональную ссылку и QR-код. Подходят как визитка, OG-превью и заготовка для соцсетей.</p></section>
</div>

<?php if ($drafts): ?><section class="panel"><h2>Черновики</h2>
<?php foreach ($drafts as $draft): ?><details class="faq-manage-item"><summary><strong><?= h((string)$draft['title']) ?></strong><span class="cell-muted"><?= h((string)$draft['draft_type']) ?> · <?= h((string)$draft['status']) ?> · <?= h((string)($draft['provider'] ?? 'swpro')) ?></span></summary>
<form method="post" class="crud-form"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$draft['id'] ?>"><label class="field wide"><span>Название</span><input name="title" value="<?= h((string)$draft['title']) ?>"></label><label class="field wide"><span>Текст</span><textarea name="content" rows="9"><?= h((string)$draft['content']) ?></textarea></label><label class="field"><span>Статус</span><select name="status"><?php foreach (['draft'=>'Черновик','approved'=>'Проверено','used'=>'Использовано'] as $key=>$label): ?><option value="<?= h($key) ?>" <?= $draft['status'] === $key ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></label><div class="form-actions"><button>Сохранить</button></div></form>
<div class="form-actions">
<?php if ($draft['draft_type'] === 'voice_script'): ?><form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="queue_voice"><input type="hidden" name="id" value="<?= (int)$draft['id'] ?>"><label class="check-row"><input type="checkbox" name="external_voice_confirm" value="1"><span>Я проверил текст и разрешаю отправить его в OpenAI для создания аудио</span></label><button>Создать голосовое сообщение</button></form><?php endif; ?>
<?php if (in_array($draft['draft_type'], ['video_script','greeting'], true)): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="queue_video"><input type="hidden" name="id" value="<?= (int)$draft['id'] ?>"><label class="check-row"><input type="checkbox" name="external_video_confirm" value="1"><span>Я проверил сценарий и разрешаю отправить его подключённому видеопровайдеру</span></label><button>Создать видео</button></form><?php endif; ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="archive"><input type="hidden" name="id" value="<?= (int)$draft['id'] ?>"><button class="link-button danger">В архив</button></form></div>
</details><?php endforeach; ?></section><?php endif; ?>
<?php if ($voiceJobs): ?><section class="panel"><h2>Голосовые сообщения</h2><p class="cell-muted">Голос создан искусственным интеллектом. При отправке обязательно сообщите это получателю.</p><?php foreach ($voiceJobs as $job): ?><div class="faq-manage-item"><strong><?= h(date('d.m.Y H:i', strtotime((string)$job['created_at']))) ?></strong> · <?= h((string)$job['status']) ?><?php if ($job['status'] === 'ready'): ?> · <?= h((string)$job['duration_seconds']) ?> сек.<div><audio controls preload="none" src="ai_voice_media.php?id=<?= (int)$job['id'] ?>"></audio> <a class="button secondary-button" href="ai_voice_media.php?id=<?= (int)$job['id'] ?>" download>Скачать MP3</a></div><?php elseif (!empty($job['error_text'])): ?><div class="alert"><?= h((string)$job['error_text']) ?></div><?php endif; ?></div><?php endforeach; ?></section><?php endif; ?>
<?php if ($videoJobs): ?><section class="panel"><h2>AI-видео</h2><p class="cell-muted">Видео создано искусственным интеллектом. Обработка у провайдера может занять несколько минут.</p><?php foreach ($videoJobs as $job): ?><div class="faq-manage-item"><strong><?= h(date('d.m.Y H:i', strtotime((string)$job['created_at']))) ?></strong> · <?= h((string)$job['provider']) ?> · <?= h((string)$job['status']) ?><?php if ($job['status'] === 'ready'): ?><div><video controls preload="metadata" style="max-width:640px;width:100%" src="ai_video_media.php?id=<?= (int)$job['id'] ?>"></video><br><a class="button secondary-button" href="ai_video_media.php?id=<?= (int)$job['id'] ?>" download>Скачать MP4</a></div><?php elseif (!empty($job['error_text'])): ?><div class="alert"><?= h((string)$job['error_text']) ?></div><?php endif; ?></div><?php endforeach; ?></section><?php endif; ?>
<?php require __DIR__ . '/../app/views/layouts/footer.php'; ?>
