<?php

function messaging_integration_for_owner(string $platform, ?int $managerId, ?int $resellerId): ?array
{
    $platform = normalize_platform($platform);
    $candidates = [];
    if ($managerId) {
        $candidates[] = ['owner_type' => 'manager', 'owner_id' => $managerId];
    }
    if ($resellerId) {
        $candidates[] = ['owner_type' => 'reseller', 'owner_id' => $resellerId];
    }

    $stmt = db()->prepare(
        'SELECT *
         FROM messaging_integrations
         WHERE platform = :platform
           AND owner_type = :owner_type
           AND owner_id = :owner_id
           AND is_active = 1
           AND access_token IS NOT NULL
           AND access_token <> ""
         ORDER BY id DESC
         LIMIT 1'
    );

    foreach ($candidates as $candidate) {
        $stmt->execute([
            'platform' => $platform,
            'owner_type' => $candidate['owner_type'],
            'owner_id' => $candidate['owner_id'],
        ]);
        $integration = $stmt->fetch();
        if ($integration) {
            return $integration;
        }
    }

    return null;
}

function messaging_owner_context_from_user_id(?int $endUserId): array
{
    if (!$endUserId) {
        return ['manager_id' => null, 'reseller_id' => null];
    }

    $stmt = db()->prepare('SELECT manager_id, reseller_id FROM end_users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $endUserId]);
    $user = $stmt->fetch();

    return [
        'manager_id' => !empty($user['manager_id']) ? (int)$user['manager_id'] : null,
        'reseller_id' => !empty($user['reseller_id']) ? (int)$user['reseller_id'] : null,
    ];
}

function messaging_text_with_links(string $text, array $buttons = [], array $mediaUrls = []): string
{
    $parts = [trim($text)];

    foreach ($buttons as $button) {
        $url = trim((string)($button['url'] ?? ''));
        if ($url === '') {
            continue;
        }
        $label = trim((string)($button['text'] ?? 'Открыть'));
        $parts[] = $label . ': ' . $url;
    }

    foreach ($mediaUrls as $url) {
        $url = trim((string)$url);
        if ($url !== '') {
            $parts[] = 'Вложение: ' . $url;
        }
    }

    return trim(implode("\n\n", array_filter($parts)));
}

function messaging_http_json_post(string $url, array $payload): array
{
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json;charset=utf-8\r\n",
            'content' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'timeout' => 15,
            'ignore_errors' => true,
        ],
    ]);
    $response = @file_get_contents($url, false, $context);
    $decoded = $response ? json_decode($response, true) : null;

    return is_array($decoded) ? $decoded : ['error' => 'Empty API response'];
}

function messaging_http_form_post(string $url, array $payload): array
{
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($payload),
            'timeout' => 15,
            'ignore_errors' => true,
        ],
    ]);
    $response = @file_get_contents($url, false, $context);
    $decoded = $response ? json_decode($response, true) : null;

    return is_array($decoded) ? $decoded : ['error' => 'Empty API response'];
}

function messaging_http_multipart_file_post(string $url, string $fieldName, string $filename, string $mimeType, string $contents): array
{
    $boundary = '----SWProBoundary' . bin2hex(random_bytes(8));
    $body = '--' . $boundary . "\r\n"
        . 'Content-Disposition: form-data; name="' . $fieldName . '"; filename="' . addslashes($filename) . '"' . "\r\n"
        . 'Content-Type: ' . $mimeType . "\r\n\r\n"
        . $contents . "\r\n"
        . '--' . $boundary . "--\r\n";

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: multipart/form-data; boundary=' . $boundary . "\r\n",
            'content' => $body,
            'timeout' => 30,
            'ignore_errors' => true,
        ],
    ]);
    $response = @file_get_contents($url, false, $context);
    $decoded = $response ? json_decode($response, true) : null;

    return is_array($decoded) ? $decoded : ['error' => 'Empty upload response'];
}

function messaging_media_extension(string $url): string
{
    return strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: $url, PATHINFO_EXTENSION));
}

function messaging_image_mime_type(string $url): ?string
{
    return match (messaging_media_extension($url)) {
        'jpg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        default => null,
    };
}

function vk_upload_message_photo(array $integration, string $userId, string $url): ?string
{
    $mimeType = messaging_image_mime_type($url);
    if (!$mimeType) {
        return null;
    }

    $token = trim((string)($integration['access_token'] ?? ''));
    $version = (string)(app_config()['integrations']['vk_api_version'] ?? '5.199');
    $uploadServer = messaging_http_form_post('https://api.vk.com/method/photos.getMessagesUploadServer', [
        'access_token' => $token,
        'v' => $version,
        'peer_id' => $userId,
    ]);
    $uploadUrl = (string)($uploadServer['response']['upload_url'] ?? '');
    if ($uploadUrl === '') {
        return null;
    }

    $contents = @file_get_contents($url);
    if ($contents === false || $contents === '') {
        return null;
    }

    $filename = 'swpro.' . messaging_media_extension($url);
    $uploaded = messaging_http_multipart_file_post($uploadUrl, 'photo', $filename, $mimeType, $contents);
    if (empty($uploaded['photo']) || empty($uploaded['server']) || empty($uploaded['hash'])) {
        return null;
    }

    $saved = messaging_http_form_post('https://api.vk.com/method/photos.saveMessagesPhoto', [
        'access_token' => $token,
        'v' => $version,
        'photo' => (string)$uploaded['photo'],
        'server' => (string)$uploaded['server'],
        'hash' => (string)$uploaded['hash'],
    ]);
    $photo = $saved['response'][0] ?? null;
    if (!is_array($photo) || empty($photo['owner_id']) || empty($photo['id'])) {
        return null;
    }

    $attachment = 'photo' . $photo['owner_id'] . '_' . $photo['id'];
    if (!empty($photo['access_key'])) {
        $attachment .= '_' . $photo['access_key'];
    }

    return $attachment;
}

function send_vk_community_message(array $integration, string $platformUserId, string $message, array $attachments = []): array
{
    $userId = preg_replace('/\D+/', '', $platformUserId);
    if (!$userId) {
        return ['ok' => false, 'error' => 'VK user_id is empty or invalid'];
    }

    $permissionStmt = db()->prepare(
        'SELECT messages_allowed
         FROM platform_accounts
         WHERE platform = "VK"
           AND platform_user_id = :platform_user_id
         ORDER BY id DESC
         LIMIT 1'
    );
    $permissionStmt->execute(['platform_user_id' => $userId]);
    $messagesAllowed = $permissionStmt->fetchColumn();
    if ($messagesAllowed !== false && (string)$messagesAllowed === '0') {
        return ['ok' => false, 'error' => 'Клиент запретил сообщения от VK-сообщества'];
    }

    $token = trim((string)($integration['access_token'] ?? ''));
    if ($token === '') {
        return ['ok' => false, 'error' => 'VK community token is missing'];
    }

    $version = (string)(app_config()['integrations']['vk_api_version'] ?? '5.199');
    $payload = [
        'access_token' => $token,
        'v' => $version,
        'user_id' => $userId,
        'random_id' => random_int(1, 2147483647),
        'message' => $message,
    ];
    if ($attachments) {
        $payload['attachment'] = implode(',', $attachments);
    }

    $response = messaging_http_form_post('https://api.vk.com/method/messages.send', $payload);

    if (isset($response['response'])) {
        return ['ok' => true, 'error' => null, 'provider_response' => $response['response']];
    }

    $error = $response['error']['error_msg'] ?? $response['error'] ?? 'VK API request failed';
    return ['ok' => false, 'error' => is_string($error) ? $error : json_encode($error, JSON_UNESCAPED_UNICODE)];
}

function ok_user_id(string $platformUserId): string
{
    $platformUserId = trim($platformUserId);
    return str_starts_with($platformUserId, 'user:') ? $platformUserId : $platformUserId;
}

function send_ok_group_message(array $integration, string $platformUserId, string $message): array
{
    $token = trim((string)($integration['access_token'] ?? ''));
    if ($token === '') {
        return ['ok' => false, 'error' => 'OK group token is missing'];
    }

    $response = messaging_http_json_post(
        'https://api.ok.ru/graph/me/messages/?access_token=' . rawurlencode($token),
        [
            'recipient' => ['user_id' => ok_user_id($platformUserId)],
            'message' => ['text' => $message],
        ]
    );

    $success = $response['success'] ?? false;
    if ($success === true || (is_array($success) && ($success[0] ?? false) === true)) {
        return ['ok' => true, 'error' => null, 'provider_response' => $response];
    }

    $error = $response['error_msg'] ?? $response['error'] ?? 'OK API request failed';
    return ['ok' => false, 'error' => is_string($error) ? $error : json_encode($error, JSON_UNESCAPED_UNICODE)];
}

function send_social_platform_message(
    string $platform,
    string $platformUserId,
    array $ownerContext,
    string $text,
    array $buttons = [],
    array $mediaUrls = []
): array {
    $platform = normalize_platform($platform);
    if (!in_array($platform, ['VK', 'OK'], true)) {
        return ['ok' => false, 'error' => 'Unsupported social platform: ' . $platform];
    }

    $integration = messaging_integration_for_owner(
        $platform,
        !empty($ownerContext['manager_id']) ? (int)$ownerContext['manager_id'] : null,
        !empty($ownerContext['reseller_id']) ? (int)$ownerContext['reseller_id'] : null
    );
    if (!$integration) {
        return ['ok' => false, 'error' => 'Нет активной интеграции сообщества для ' . platform_label($platform)];
    }

    $attachments = [];
    $fallbackMediaUrls = $mediaUrls;
    if ($platform === 'VK') {
        $fallbackMediaUrls = [];
        foreach ($mediaUrls as $mediaUrl) {
            $mediaUrl = trim((string)$mediaUrl);
            if ($mediaUrl === '') {
                continue;
            }
            $attachment = vk_upload_message_photo($integration, preg_replace('/\D+/', '', $platformUserId) ?: $platformUserId, $mediaUrl);
            if ($attachment) {
                $attachments[] = $attachment;
            } else {
                $fallbackMediaUrls[] = $mediaUrl;
            }
        }
    }

    $message = messaging_text_with_links($text, $buttons, $fallbackMediaUrls);
    if ($message === '' && !($platform === 'VK' && $attachments)) {
        return ['ok' => false, 'error' => 'Message text is empty'];
    }

    $result = $platform === 'VK'
        ? send_vk_community_message($integration, $platformUserId, $message, $attachments)
        : send_ok_group_message($integration, $platformUserId, $message);

    $result['integration_id'] = (int)$integration['id'];
    return $result;
}
