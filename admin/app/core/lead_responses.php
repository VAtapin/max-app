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

function save_response_attachment(array &$errors): ?string
{
    $file = $_FILES['response_attachment'] ?? null;
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Не удалось загрузить вложение для ответа.';
        return null;
    }

    $config = app_config();
    $maxBytes = (int)($config['security']['upload_max_bytes'] ?? 5242880);
    $allowedTypes = $config['security']['allowed_attachment_types'] ?? [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
        'video/mp4',
    ];

    if ($maxBytes > 0 && (int)$file['size'] > $maxBytes) {
        $errors[] = 'Вложение слишком большое. Максимум: ' . round($maxBytes / 1024 / 1024, 1) . ' МБ.';
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowedTypes, true)) {
        $errors[] = 'Можно отправлять JPG, PNG, WebP, PDF или MP4.';
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
        $errors[] = 'Не удалось определить тип вложения.';
        return null;
    }

    $directory = lead_response_upload_dir();
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        $errors[] = 'Не удалось создать папку для ответов.';
        return null;
    }

    $filename = date('YmdHis') . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
    $target = $directory . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        $errors[] = 'Не удалось сохранить вложение ответа.';
        return null;
    }

    return '/admin/uploads/responses/' . $filename;
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
        $parts[] = 'Материал: ' . $content['title'];
        $contentText = trim((string)($content['short_text'] ?: $content['full_text'] ?: ''));
        if ($contentText !== '') {
            $parts[] = $contentText;
        }
    }

    if ($test) {
        $parts[] = 'Рекомендуем пройти тест: ' . $test['title'];
    }

    return trim(implode("\n\n", array_filter($parts)));
}

function telegram_api_request(string $method, array $payload): array
{
    $token = app_config()['integrations']['telegram_bot_token'] ?? '';
    if ($token === '') {
        return ['ok' => false, 'error' => 'TELEGRAM_BOT_TOKEN is not configured.'];
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
            'text' => 'Пройти тест',
            'web_app' => ['url' => mini_app_url((int)$test['id'])],
        ]];
    }

    $videoUrl = trim((string)($content['video_url'] ?? ''));
    if ($videoUrl !== '') {
        $buttons[] = [[
            'text' => 'Открыть видео',
            'url' => $videoUrl,
        ]];
    }

    $buttonUrl = trim((string)($content['button_url'] ?? ''));
    if ($buttonUrl !== '') {
        $buttons[] = [[
            'text' => trim((string)($content['button_text'] ?? 'Открыть материал')) ?: 'Открыть материал',
            'url' => $buttonUrl,
        ]];
    }

    if (trim((string)$externalUrl) !== '') {
        $buttons[] = [[
            'text' => 'Открыть ссылку',
            'url' => trim((string)$externalUrl),
        ]];
    }

    return $buttons;
}

function send_telegram_text(string $chatId, string $text, array $buttons = []): array
{
    $payload = [
        'chat_id' => $chatId,
        'text' => $text !== '' ? $text : 'Материалы по вашей заявке.',
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

function send_telegram_response(string $chatId, string $text, ?array $content, ?array $test, ?string $attachmentPath, ?string $externalUrl): array
{
    $errors = [];
    $messageResult = send_telegram_text($chatId, $text, telegram_buttons($content, $test, $externalUrl));
    if (!$messageResult['ok']) {
        $errors[] = $messageResult['error'];
    }

    foreach ([
        ['path' => $content['image_path'] ?? null, 'caption' => $content ? (string)$content['title'] : ''],
        ['path' => $content['attachment_path'] ?? null, 'caption' => $content ? (string)$content['title'] : ''],
        ['path' => $attachmentPath, 'caption' => 'Файл по заявке'],
    ] as $item) {
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
        $errors[] = 'Заявка не найдена.';
        return null;
    }

    $message = trim((string)($_POST['response_text'] ?? ''));
    $contentId = isset($_POST['response_content_id']) && $_POST['response_content_id'] !== '' ? (int)$_POST['response_content_id'] : null;
    $testId = isset($_POST['response_test_id']) && $_POST['response_test_id'] !== '' ? (int)$_POST['response_test_id'] : null;
    $externalUrl = trim((string)($_POST['response_external_url'] ?? ''));
    $attachmentPath = save_response_attachment($errors);

    $content = content_snippet($contentId);
    $test = test_snippet($testId);
    $text = build_lead_response_text($message, $content, $test);
    if ($text === '') {
        $errors[] = 'Добавьте текст, материал, тест, файл или ссылку для ответа.';
        return null;
    }

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
        'attachment_path' => $attachmentPath,
        'external_url' => $externalUrl !== '' ? $externalUrl : null,
    ]);
    $responseId = (int)db()->lastInsertId();

    $status = 'pending';
    $error = null;
    if ($platform === 'telegram') {
        $result = send_telegram_response((string)$lead['platform_user_id'], $text, $content, $test, $attachmentPath, $externalUrl);
        $status = $result['ok'] ? 'sent' : 'failed';
        $error = $result['error'];
    } else {
        $error = 'Отправка для платформы ' . $platform . ' пока не подключена. Ответ сохранен.';
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
        $errors[] = 'Ответ сохранен, но не отправлен: ' . $error;
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
