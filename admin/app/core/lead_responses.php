<?php

require_once __DIR__ . '/client_journey.php';
require_once __DIR__ . '/social_messaging.php';

function absolute_public_url(?string $path): ?string
{
    if (!$path) {
        return null;
    }

    if (preg_match('/^https?:\/\//i', $path)) {
        return $path;
    }

    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host === '') {
        return $path;
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . $host . '/' . ltrim($path, '/');
}

function lead_response_upload_dir(): string
{
    return dirname(__DIR__, 2) . '/uploads/responses';
}

function lead_response_attachment_paths(?string $value): array
{
    $value = trim((string)$value);
    if ($value === '') {
        return [];
    }

    $decoded = json_decode($value, true);
    if (is_array($decoded)) {
        return array_values(array_filter(array_map('strval', $decoded), static fn($path) => trim($path) !== ''));
    }

    $paths = preg_split('/\r\n|\r|\n/', $value) ?: [];
    return array_values(array_filter(array_map('trim', $paths), static fn($path) => $path !== ''));
}

function normalize_uploaded_files(?array $file): array
{
    if (!$file) {
        return [];
    }

    if (!is_array($file['name'] ?? null)) {
        return [$file];
    }

    $files = [];
    $count = count($file['name']);
    for ($i = 0; $i < $count; $i++) {
        $files[] = [
            'name' => $file['name'][$i] ?? '',
            'type' => $file['type'][$i] ?? '',
            'tmp_name' => $file['tmp_name'][$i] ?? '',
            'error' => $file['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size' => $file['size'][$i] ?? 0,
        ];
    }

    return $files;
}

function save_single_response_attachment(array $file, array &$errors): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        $errors[] = app_text('lead_response.upload_failed');
        return null;
    }

    $config = app_config();
    $maxBytes = (int)($config['security']['upload_max_bytes'] ?? 0);
    $allowedTypes = $config['security']['allowed_attachment_types'] ?? [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
        'video/mp4',
        'audio/mpeg',
        'audio/ogg',
        'audio/mp4',
        'audio/x-m4a',
        'audio/webm',
    ];

    if ($maxBytes > 0 && (int)$file['size'] > $maxBytes) {
        $errors[] = app_text('lead_response.upload_too_large', ['size' => round($maxBytes / 1024 / 1024, 1)]);
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowedTypes, true)) {
        $errors[] = app_text('lead_response.invalid_attachment_type');
        return null;
    }

    $extension = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
        'video/mp4' => 'mp4',
        'audio/mpeg' => 'mp3',
        'audio/ogg' => 'ogg',
        'audio/mp4' => 'm4a',
        'audio/x-m4a' => 'm4a',
        'audio/webm' => 'webm',
        default => null,
    };
    if (!$extension) {
        $errors[] = app_text('lead_response.unknown_attachment_type');
        return null;
    }

    $directory = lead_response_upload_dir();
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        $errors[] = app_text('lead_response.create_response_dir_failed');
        return null;
    }

    $filename = date('YmdHis') . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
    $target = $directory . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        $errors[] = app_text('lead_response.save_attachment_failed');
        return null;
    }

    return '/admin/uploads/responses/' . $filename;
}

function save_response_attachments(array &$errors): array
{
    $input = $_FILES['response_attachments'] ?? ($_FILES['response_attachment'] ?? null);
    $paths = [];

    foreach (normalize_uploaded_files($input) as $file) {
        $path = save_single_response_attachment($file, $errors);
        if ($path) {
            $paths[] = $path;
        }
    }

    return $paths;
}

function lead_context(int $leadId): ?array
{
    $stmt = db()->prepare(
        'SELECT l.*, eu.id AS end_user_id, eu.platform AS user_platform, eu.platform_user_id, eu.username,
                eu.first_name, eu.last_name, eu.referral_code_used
         FROM leads l
         INNER JOIN end_users eu ON eu.id = l.end_user_id
         WHERE l.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $leadId]);
    $lead = $stmt->fetch();

    return $lead ?: null;
}

function lead_response_referral_code(array $lead): ?string
{
    $code = trim((string)($lead['referral_code_used'] ?? ''));
    if ($code !== '') {
        return $code;
    }

    if (!empty($lead['manager_id'])) {
        $stmt = db()->prepare('SELECT referral_code FROM managers WHERE id = :id AND is_active = 1 LIMIT 1');
        $stmt->execute(['id' => (int)$lead['manager_id']]);
        $code = trim((string)$stmt->fetchColumn());
        if ($code !== '') {
            return $code;
        }
    }

    if (!empty($lead['reseller_id'])) {
        $stmt = db()->prepare('SELECT referral_code FROM resellers WHERE id = :id AND is_active = 1 LIMIT 1');
        $stmt->execute(['id' => (int)$lead['reseller_id']]);
        $code = trim((string)$stmt->fetchColumn());
        if ($code !== '') {
            return $code;
        }
    }

    return null;
}

function lead_response_account_link_secret(): string
{
    $config = app_config();
    $botToken = (string)($config['integrations']['telegram_bot_token'] ?? getenv('TELEGRAM_BOT_TOKEN') ?: '');
    $dbPassword = (string)($config['db']['password'] ?? '');

    return hash('sha256', $botToken . '|' . $dbPassword . '|swpro-account-link');
}

function lead_response_account_link_token(int $endUserId, int $ttlSeconds = 604800): ?string
{
    if ($endUserId <= 0) {
        return null;
    }

    $expiresAt = time() + $ttlSeconds;
    $payload = $endUserId . '|' . $expiresAt;
    $signature = substr(hash_hmac('sha256', $payload, lead_response_account_link_secret()), 0, 20);

    return 'l_' . $endUserId . '_' . $expiresAt . '_' . $signature;
}

function lead_platform_account_id(array $lead, string $platform): ?string
{
    $stmt = db()->prepare(
        'SELECT platform_user_id
         FROM platform_accounts
         WHERE end_user_id = :end_user_id AND platform = :platform
         ORDER BY id DESC
         LIMIT 1'
    );
    $stmt->execute([
        'end_user_id' => (int)$lead['end_user_id'],
        'platform' => normalize_platform($platform),
    ]);
    $platformUserId = $stmt->fetchColumn();
    if ($platformUserId !== false && trim((string)$platformUserId) !== '') {
        return (string)$platformUserId;
    }

    if (normalize_platform((string)($lead['user_platform'] ?? '')) === normalize_platform($platform)) {
        return trim((string)($lead['platform_user_id'] ?? '')) ?: null;
    }

    return null;
}

function lead_platform_accounts(array $lead): array
{
    $stmt = db()->prepare(
        'SELECT platform, platform_user_id
         FROM platform_accounts
         WHERE end_user_id = :end_user_id
         ORDER BY FIELD(platform, "telegram", "VK", "OK", "MAX", "web"), id'
    );
    $stmt->execute(['end_user_id' => (int)$lead['end_user_id']]);
    $accounts = $stmt->fetchAll();
    if ($accounts) {
        return $accounts;
    }

    if (!empty($lead['user_platform']) && !empty($lead['platform_user_id'])) {
        return [[
            'platform' => (string)$lead['user_platform'],
            'platform_user_id' => (string)$lead['platform_user_id'],
        ]];
    }

    return [];
}

function content_snippet(?int $contentId): ?array
{
    if (!$contentId) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT id, title, short_text, full_text, image_path, attachment_path, video_url, button_text, button_url
         FROM content_posts
         WHERE id = :id AND status IN ("published", "draft")
         LIMIT 1'
    );
    $stmt->execute(['id' => $contentId]);
    $content = $stmt->fetch();

    return $content ?: null;
}

function test_snippet(?int $testId): ?array
{
    if (!$testId) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, title, description FROM tests WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $testId]);
    $test = $stmt->fetch();

    return $test ?: null;
}

function mini_app_url(
    ?int $testId = null,
    ?string $platform = null,
    ?string $referralCode = null,
    ?int $materialId = null,
    ?int $endUserId = null,
    ?string $page = null
): string
{
    $config = app_config();
    $platform = normalize_platform($platform);
    $referralCode = trim((string)$referralCode);
    $params = [];
    if ($referralCode !== '') {
        $params['ref'] = $referralCode;
    }
    $page = trim((string)$page);
    if ($testId) {
        $params['page'] = 'tests';
        $params['test_id'] = $testId;
    } elseif ($materialId) {
        $params['page'] = 'home';
        $params['material_id'] = $materialId;
    } elseif ($page !== '') {
        $params['page'] = $page;
    }

    if ($platform === 'VK') {
        $vkAppId = preg_replace('/\D+/', '', (string)($config['integrations']['vk_app_id'] ?? '')) ?: '';
        if ($vkAppId !== '') {
            $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
            return 'https://vk.ru/app' . $vkAppId . ($query !== '' ? '#' . $query : '');
        }
    }

    if ($platform === 'OK') {
        $okAppId = preg_replace('/\D+/', '', (string)($config['integrations']['ok_app_id'] ?? '')) ?: '';
        if ($okAppId !== '') {
            $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
            return 'https://ok.ru/app/' . $okAppId . ($query !== '' ? '?' . $query : '');
        }
    }

    $configured = $config['integrations']['mini_app_url'] ?? '';
    $url = $configured !== '' ? $configured : (absolute_public_url('/vk-mini-app/') ?: '/vk-mini-app/');
    $linkToken = $platform !== 'telegram' ? lead_response_account_link_token((int)$endUserId) : null;
    if ($linkToken !== null) {
        $params['link_token'] = $linkToken;
    }
    if ($params) {
        $separator = str_contains($url, '?') ? '&' : '?';
        $url .= $separator . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    return $url;
}

function build_lead_response_text(string $message, ?array $content, ?array $test, ?string $sourcePlatform = null): string
{
    $parts = [];
    if ($sourcePlatform) {
        $parts[] = app_text('referrals.lead_source') . ': ' . platform_label($sourcePlatform);
    }

    if (trim($message) !== '') {
        $parts[] = trim($message);
    }

    if ($content) {
        $parts[] = app_text('lead_response.content_prefix') . ': ' . $content['title'];
        $contentText = trim((string)($content['short_text'] ?: $content['full_text'] ?: ''));
        if ($contentText !== '') {
            $parts[] = $contentText;
        }
    }

    if ($test) {
        $parts[] = app_text('lead_response.test_recommendation_prefix') . ': ' . $test['title'];
    }

    return trim(implode("\n\n", array_filter($parts)));
}

function telegram_api_request(string $method, array $payload): array
{
    $token = app_config()['integrations']['telegram_bot_token'] ?? '';
    if ($token === '') {
        return ['ok' => false, 'error' => app_text('auto.telegram_token_missing')];
    }

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $json,
            'timeout' => 15,
            'ignore_errors' => true,
        ],
    ]);

    $response = @file_get_contents('https://api.telegram.org/bot' . $token . '/' . $method, false, $context);
    $decoded = $response ? json_decode($response, true) : null;
    if (is_array($decoded) && ($decoded['ok'] ?? false)) {
        return ['ok' => true, 'error' => null];
    }

    return [
        'ok' => false,
        'error' => is_array($decoded) ? ($decoded['description'] ?? 'Telegram API error') : 'Telegram API request failed',
    ];
}

function telegram_buttons(?array $content, ?array $test, ?string $externalUrl, ?string $referralCode = null): array
{
    $buttons = [];
    if ($test) {
        $buttons[] = [[
            'text' => app_text('lead_response.pass_test'),
            'web_app' => ['url' => mini_app_url((int)$test['id'], 'telegram', $referralCode)],
        ]];
    }

    if ($content) {
        $buttons[] = [[
            'text' => app_text('lead_response.open_material'),
            'web_app' => ['url' => mini_app_url(null, 'telegram', $referralCode, (int)$content['id'])],
        ]];
    }

    $videoUrl = trim((string)($content['video_url'] ?? ''));
    if ($videoUrl !== '') {
        $buttons[] = [[
            'text' => app_text('lead_response.open_video'),
            'url' => $videoUrl,
        ]];
    }

    $buttonUrl = trim((string)($content['button_url'] ?? ''));
    if ($buttonUrl !== '') {
        $buttons[] = [[
            'text' => trim((string)($content['button_text'] ?? app_text('lead_response.open_material'))) ?: app_text('lead_response.open_material'),
            'url' => $buttonUrl,
        ]];
    }

    if (trim((string)$externalUrl) !== '') {
        $buttons[] = [[
            'text' => app_text('lead_response.open_link'),
            'url' => trim((string)$externalUrl),
        ]];
    }

    return $buttons;
}

function lead_response_social_buttons(?array $content, ?array $test, ?string $externalUrl, ?string $sourcePlatform = null, ?string $referralCode = null, ?int $endUserId = null): array
{
    $buttons = [];
    if ($test) {
        $buttons[] = [
            'text' => 'Пройти тест',
            'url' => mini_app_url((int)$test['id'], $sourcePlatform, $referralCode, null, $endUserId),
        ];
    }

    if ($content) {
        $buttons[] = [
            'text' => 'Открыть материал',
            'url' => mini_app_url(null, $sourcePlatform, $referralCode, (int)$content['id'], $endUserId),
        ];
    }

    $videoUrl = trim((string)($content['video_url'] ?? ''));
    if ($videoUrl !== '') {
        $buttons[] = [
            'text' => 'Открыть видео',
            'url' => $videoUrl,
        ];
    }

    $buttonUrl = trim((string)($content['button_url'] ?? ''));
    if ($buttonUrl !== '') {
        $buttons[] = [
            'text' => trim((string)($content['button_text'] ?? 'Открыть материал')) ?: 'Открыть материал',
            'url' => $buttonUrl,
        ];
    }

    if (trim((string)$externalUrl) !== '') {
        $buttons[] = [
            'text' => 'Открыть ссылку',
            'url' => trim((string)$externalUrl),
        ];
    }

    return $buttons;
}

function lead_response_media_urls(?array $content, array $attachmentPaths, bool $includeContentMedia = true): array
{
    $urls = [];
    if ($includeContentMedia) {
        foreach ([$content['image_path'] ?? null, $content['attachment_path'] ?? null] as $path) {
            $url = absolute_public_url($path);
            if ($url) {
                $urls[] = $url;
            }
        }
    }
    foreach ($attachmentPaths as $path) {
        $url = absolute_public_url($path);
        if ($url) {
            $urls[] = $url;
        }
    }

    return $urls;
}

function send_telegram_text(string $chatId, string $text, array $buttons = []): array
{
    $payload = [
        'chat_id' => $chatId,
        'text' => $text !== '' ? $text : app_text('lead_response.default_response_text'),
        'disable_web_page_preview' => false,
    ];

    if ($buttons) {
        $payload['reply_markup'] = ['inline_keyboard' => $buttons];
    }

    return telegram_api_request('sendMessage', $payload);
}

function telegram_media_method(string $path): ?array
{
    if (str_contains((string)(parse_url($path, PHP_URL_PATH) ?: $path), '/api/voice_media.php')) {
        return ['method' => 'sendVoice', 'field' => 'voice'];
    }
    $extension = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION));
    return match ($extension) {
        'jpg', 'jpeg', 'png', 'webp' => ['method' => 'sendPhoto', 'field' => 'photo'],
        'mp4' => ['method' => 'sendVideo', 'field' => 'video'],
        'mp3', 'm4a' => ['method' => 'sendAudio', 'field' => 'audio'],
        'ogg', 'oga' => ['method' => 'sendVoice', 'field' => 'voice'],
        'pdf' => ['method' => 'sendDocument', 'field' => 'document'],
        default => ['method' => 'sendDocument', 'field' => 'document'],
    };
}

function send_telegram_media(string $chatId, ?string $path, string $caption = ''): array
{
    $url = absolute_public_url($path);
    if (!$url) {
        return ['ok' => true, 'error' => null];
    }

    $media = telegram_media_method($url);
    if (!$media) {
        return ['ok' => false, 'error' => 'Unsupported attachment type.'];
    }

    $payload = [
        'chat_id' => $chatId,
        $media['field'] => $url,
    ];
    if ($caption !== '') {
        $payload['caption'] = function_exists('mb_substr') ? mb_substr($caption, 0, 1024) : substr($caption, 0, 1024);
    }

    return telegram_api_request($media['method'], $payload);
}

function send_telegram_response(string $chatId, string $text, ?array $content, ?array $test, array $attachmentPaths, ?string $externalUrl, ?string $referralCode = null): array
{
    $errors = [];
    $messageResult = send_telegram_text($chatId, $text, telegram_buttons($content, $test, $externalUrl, $referralCode));
    if (!$messageResult['ok']) {
        $errors[] = $messageResult['error'];
    }

    $items = [
        ['path' => $content['image_path'] ?? null, 'caption' => $content ? (string)$content['title'] : ''],
        ['path' => $content['attachment_path'] ?? null, 'caption' => $content ? (string)$content['title'] : ''],
    ];

    foreach ($attachmentPaths as $index => $attachmentPath) {
        $caption = count($attachmentPaths) > 1 ? app_text('lead_response.lead_file_numbered', ['index' => $index + 1, 'total' => count($attachmentPaths)]) : app_text('lead_response.lead_file');
        $items[] = ['path' => $attachmentPath, 'caption' => $caption];
    }

    foreach ($items as $item) {
        if (!$item['path']) {
            continue;
        }
        $result = send_telegram_media($chatId, $item['path'], $item['caption']);
        if (!$result['ok']) {
            $errors[] = $result['error'];
        }
    }

    return $errors ? ['ok' => false, 'error' => implode('; ', array_filter($errors))] : ['ok' => true, 'error' => null];
}

function create_and_send_lead_response(int $leadId, array $admin, array &$errors): ?int
{
    $lead = lead_context($leadId);
    if (!$lead) {
        $errors[] = app_text('lead_response.lead_not_found');
        return null;
    }

    $message = trim((string)($_POST['response_text'] ?? ''));
    $contentId = isset($_POST['response_content_id']) && $_POST['response_content_id'] !== '' ? (int)$_POST['response_content_id'] : null;
    $testId = isset($_POST['response_test_id']) && $_POST['response_test_id'] !== '' ? (int)$_POST['response_test_id'] : null;
    $externalUrl = trim((string)($_POST['response_external_url'] ?? ''));
    $attachmentPaths = save_response_attachments($errors);

    $content = content_snippet($contentId);
    $test = test_snippet($testId);
    $text = build_lead_response_text($message, $content, $test);
    if ($text === '' && !$attachmentPaths && $externalUrl === '') {
        $errors[] = app_text('lead_response.empty_response');
        return null;
    }

    if ($errors) {
        return null;
    }

    $attachmentValue = $attachmentPaths ? json_encode($attachmentPaths, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
    $platform = normalize_platform((string)$lead['source_platform']);
    $referralCode = lead_response_referral_code($lead);

    $stmt = db()->prepare(
        'INSERT INTO lead_responses
            (lead_id, admin_user_id, content_post_id, test_id, platform, message_text, attachment_path, external_url, status)
         VALUES
            (:lead_id, :admin_user_id, :content_post_id, :test_id, :platform, :message_text, :attachment_path, :external_url, "pending")'
    );
    $stmt->execute([
        'lead_id' => $leadId,
        'admin_user_id' => (int)$admin['id'],
        'content_post_id' => $contentId,
        'test_id' => $testId,
        'platform' => $platform,
        'message_text' => $text,
        'attachment_path' => $attachmentValue,
        'external_url' => $externalUrl !== '' ? $externalUrl : null,
    ]);
    $responseId = (int)db()->lastInsertId();

    $status = 'sent';
    $deliveryErrors = [];
    if ($platform === 'telegram') {
        $telegramChatId = lead_platform_account_id($lead, 'telegram');
        if ($telegramChatId) {
            $result = send_telegram_response($telegramChatId, $text, $content, $test, $attachmentPaths, $externalUrl, $referralCode);
            if (!$result['ok']) {
                $deliveryErrors[] = 'telegram: ' . $result['error'];
                $status = 'failed';
            }
        } else {
            $deliveryErrors[] = app_text('lead_response.platform_not_connected', ['platform' => 'telegram']);
            $status = 'failed';
        }
    } elseif (in_array($platform, ['VK', 'OK'], true)) {
        $platformUserId = lead_platform_account_id($lead, $platform);
        if ($platformUserId) {
            $result = send_social_platform_message(
                $platform,
                $platformUserId,
                $lead + ['end_user_id' => (int)$lead['end_user_id']],
                $text,
                lead_response_social_buttons($content, $test, $externalUrl, $platform, $referralCode, (int)$lead['end_user_id']),
                lead_response_media_urls($content, $attachmentPaths, false)
            );
            if (!$result['ok']) {
                $deliveryErrors[] = $platform . ': ' . $result['error'];
                $status = 'failed';
            }
        } else {
            $deliveryErrors[] = app_text('lead_response.platform_not_connected', ['platform' => $platform]);
            $status = 'failed';
        }
    } elseif (!in_array($platform, ['MAX', 'web'], true)) {
        $deliveryErrors[] = app_text('lead_response.platform_not_connected', ['platform' => $platform]);
        $status = 'failed';
    }
    $error = $deliveryErrors ? implode('; ', array_filter($deliveryErrors)) : null;

    $stmt = db()->prepare(
        'UPDATE lead_responses
         SET status = :status, error_message = :error_message, sent_at = :sent_at
         WHERE id = :id'
    );
    $stmt->execute([
        'status' => $status,
        'error_message' => $error,
        'sent_at' => $status === 'sent' ? date('Y-m-d H:i:s') : null,
        'id' => $responseId,
    ]);

    if ($status === 'sent') {
        $config = app_config();
        $miniAppUrl = rtrim((string)($config['integrations']['mini_app_url'] ?? ''), '/');
        create_user_notification(
            (int)$lead['end_user_id'],
            'lead_response',
            'Ответ консультанта',
            $message !== '' ? $message : 'Консультант отправил вам материалы.',
            'Открыть ответ',
            mini_app_url(null, $platform, $referralCode, null, (int)$lead['end_user_id'], 'contact')
        );
        $stmt = db()->prepare('UPDATE leads SET status = "contacted" WHERE id = :id AND status = "new"');
        $stmt->execute(['id' => $leadId]);
        $stageStmt = db()->prepare('SELECT client_stage FROM end_users WHERE id = :id LIMIT 1');
        $stageStmt->execute(['id' => $lead['end_user_id']]);
        $currentStage = (string)($stageStmt->fetchColumn() ?: 'new');
        if (!in_array($currentStage, ['client', 'partner', 'unsubscribed'], true)) {
            $source = match ((string)$admin['role']) {
                'manager' => 'consultant',
                'reseller' => 'leader',
                default => 'admin',
            };
            update_client_stage(
                (int)$lead['end_user_id'],
                'in_progress',
                $source,
                (int)$admin['id'],
                'Консультант отправил ответ на обращение'
            );
        }
    } elseif ($status === 'failed') {
        $errors[] = app_text('lead_response.response_saved_not_sent', ['error' => $error]);
    } elseif ($status === 'pending' && $error) {
        $errors[] = $error;
    }

    log_activity('admin', (int)$admin['id'], 'send_lead_response', 'lead_responses', $responseId, [
        'lead_id' => $leadId,
        'status' => $status,
    ]);

    return $responseId;
}

function lead_response_history(int $leadId): array
{
    $stmt = db()->prepare(
        'SELECT lr.*, au.name AS admin_name, cp.title AS content_title, t.title AS test_title
         FROM lead_responses lr
         LEFT JOIN admin_users au ON au.id = lr.admin_user_id
         LEFT JOIN content_posts cp ON cp.id = lr.content_post_id
         LEFT JOIN tests t ON t.id = lr.test_id
         WHERE lr.lead_id = :lead_id
         ORDER BY lr.id DESC'
    );
    $stmt->execute(['lead_id' => $leadId]);
    return $stmt->fetchAll();
}

function lead_response_platform(int $responseId): string
{
    if ($responseId <= 0) {
        return '';
    }

    $stmt = db()->prepare('SELECT platform FROM lead_responses WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $responseId]);

    return normalize_platform((string)($stmt->fetchColumn() ?: ''));
}
