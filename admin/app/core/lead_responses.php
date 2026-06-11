<?php

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
        'SELECT l.*, eu.platform AS user_platform, eu.platform_user_id, eu.username,
                eu.first_name, eu.last_name
         FROM leads l
         INNER JOIN end_users eu ON eu.id = l.end_user_id
         WHERE l.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $leadId]);
    $lead = $stmt->fetch();

    return $lead ?: null;
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

function mini_app_url(?int $testId = null): string
{
    $configured = app_config()['integrations']['mini_app_url'] ?? '';
    $url = $configured !== '' ? $configured : (absolute_public_url('/mini-app/index.html') ?: '/mini-app/index.html');
    if ($testId) {
        $separator = str_contains($url, '?') ? '&' : '?';
        $url .= $separator . 'page=tests&test_id=' . $testId;
    }

    return $url;
}

function build_lead_response_text(string $message, ?array $content, ?array $test): string
{
    $parts = [];
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

function telegram_buttons(?array $content, ?array $test, ?string $externalUrl): array
{
    $buttons = [];
    if ($test) {
        $buttons[] = [[
            'text' => app_text('lead_response.pass_test'),
            'web_app' => ['url' => mini_app_url((int)$test['id'])],
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
    $extension = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION));
    return match ($extension) {
        'jpg', 'jpeg', 'png', 'webp' => ['method' => 'sendPhoto', 'field' => 'photo'],
        'mp4' => ['method' => 'sendVideo', 'field' => 'video'],
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

function send_telegram_response(string $chatId, string $text, ?array $content, ?array $test, array $attachmentPaths, ?string $externalUrl): array
{
    $errors = [];
    $messageResult = send_telegram_text($chatId, $text, telegram_buttons($content, $test, $externalUrl));
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


function vk_api_request(string $method, array $params): array
{
    $token = app_config()['integrations']['vk_group_token'] ?? '';
    if ($token === '') {
        return ['ok' => false, 'error' => app_text('auto.vk_group_token_missing')];
    }

    $params['access_token'] = $token;
    $params['v'] = '5.199';

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($params),
            'timeout' => 15,
            'ignore_errors' => true,
        ],
    ]);

    $response = @file_get_contents('https://api.vk.com/method/' . $method, false, $context);
    $decoded = $response ? json_decode($response, true) : null;
    if (is_array($decoded) && isset($decoded['response'])) {
        return ['ok' => true, 'error' => null];
    }

    $error = is_array($decoded) ? ($decoded['error']['error_msg'] ?? 'VK API error') : 'VK API request failed';
    return ['ok' => false, 'error' => $error];
}

function build_vk_response_text(string $text, ?array $content, ?array $test, array $attachmentPaths, ?string $externalUrl): string
{
    $parts = [];
    if (trim($text) !== '') {
        $parts[] = trim($text);
    }

    if ($test) {
        $parts[] = app_text('lead_response.pass_test') . ': ' . mini_app_url((int)$test['id']);
    }

    $links = [];
    foreach ([$content['image_path'] ?? null, $content['attachment_path'] ?? null] as $path) {
        $url = absolute_public_url($path);
        if ($url) {
            $links[] = $url;
        }
    }
    foreach ($attachmentPaths as $path) {
        $url = absolute_public_url($path);
        if ($url) {
            $links[] = $url;
        }
    }
    if (trim((string)$externalUrl) !== '') {
        $links[] = trim((string)$externalUrl);
    }

    if ($links) {
        $parts[] = app_text('lead_response.materials_title') . ":\n" . implode("\n", array_unique($links));
    }

    return trim(implode("\n\n", array_filter($parts))) ?: app_text('lead_response.default_response_text');
}

function send_vk_response(string $userId, string $text, ?array $content, ?array $test, array $attachmentPaths, ?string $externalUrl): array
{
    return vk_api_request('messages.send', [
        'user_id' => $userId,
        'random_id' => random_int(1, PHP_INT_MAX),
        'message' => build_vk_response_text($text, $content, $test, $attachmentPaths, $externalUrl),
    ]);
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
    $platform = (string)$lead['source_platform'];
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

    $status = 'pending';
    $error = null;
    if ($platform === 'telegram') {
        $result = send_telegram_response((string)$lead['platform_user_id'], $text, $content, $test, $attachmentPaths, $externalUrl);
        $status = $result['ok'] ? 'sent' : 'failed';
        $error = $result['error'];
    } elseif ($platform === 'vk') {
        $result = send_vk_response((string)$lead['platform_user_id'], $text, $content, $test, $attachmentPaths, $externalUrl);
        $status = $result['ok'] ? 'sent' : 'failed';
        $error = $result['error'];
    } else {
        $error = app_text('lead_response.platform_not_connected', ['platform' => $platform]);
    }

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
        $stmt = db()->prepare('UPDATE leads SET status = "contacted" WHERE id = :id AND status = "new"');
        $stmt->execute(['id' => $leadId]);
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
