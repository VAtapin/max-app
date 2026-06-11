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

function build_lead_response_text(string $message, ?array $content, ?array $test, ?string $attachmentPath, ?string $externalUrl): string
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
        foreach (['image_path', 'attachment_path', 'video_url', 'button_url'] as $field) {
            $url = absolute_public_url($content[$field] ?? null);
            if ($url) {
                $parts[] = $url;
            }
        }
    }

    if ($test) {
        $parts[] = 'Рекомендуем пройти тест: ' . $test['title'];
        $parts[] = absolute_public_url('/mini-app/index.html') ?: '/mini-app/index.html';
    }

    $attachmentUrl = absolute_public_url($attachmentPath);
    if ($attachmentUrl) {
        $parts[] = 'Файл: ' . $attachmentUrl;
    }

    if (trim((string)$externalUrl) !== '') {
        $parts[] = trim((string)$externalUrl);
    }

    return trim(implode("\n\n", array_filter($parts)));
}

function send_telegram_text(string $chatId, string $text): array
{
    $token = app_config()['integrations']['telegram_bot_token'] ?? '';
    if ($token === '') {
        return ['ok' => false, 'error' => 'TELEGRAM_BOT_TOKEN is not configured.'];
    }

    $payload = json_encode([
        'chat_id' => $chatId,
        'text' => $text,
        'disable_web_page_preview' => false,
    ], JSON_UNESCAPED_UNICODE);

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $payload,
            'timeout' => 15,
            'ignore_errors' => true,
        ],
    ]);

    $response = @file_get_contents('https://api.telegram.org/bot' . $token . '/sendMessage', false, $context);
    $decoded = $response ? json_decode($response, true) : null;
    if (is_array($decoded) && ($decoded['ok'] ?? false)) {
        return ['ok' => true, 'error' => null];
    }

    return [
        'ok' => false,
        'error' => is_array($decoded) ? ($decoded['description'] ?? 'Telegram API error') : 'Telegram API request failed',
    ];
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
    $text = build_lead_response_text($message, $content, $test, $attachmentPath, $externalUrl);
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
        $result = send_telegram_text((string)$lead['platform_user_id'], $text);
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
