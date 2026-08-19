<?php

function messaging_integration_is_usable(array $integration): bool
{
    $baseReady = (int)($integration['is_active'] ?? 0) === 1
        && trim((string)($integration['external_id'] ?? '')) !== ''
        && trim((string)($integration['access_token'] ?? '')) !== '';
    if (!$baseReady || normalize_platform((string)($integration['platform'] ?? '')) !== 'VK') {
        return $baseReady;
    }
    return trim((string)($integration['callback_confirmation_code'] ?? '')) !== ''
        && !empty($integration['callback_last_event_at'])
        && trim((string)($integration['callback_last_error'] ?? '')) === '';
}

function messaging_default_integration(string $platform): ?array
{
    $stmt = db()->prepare(
        'SELECT *
         FROM messaging_integrations
         WHERE platform = :platform
           AND is_default = 1
           AND is_active = 1
         ORDER BY id DESC
         LIMIT 1'
    );
    $stmt->execute(['platform' => normalize_platform($platform)]);
    $integration = $stmt->fetch();

    return $integration && messaging_integration_is_usable($integration) ? $integration : null;
}

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
           AND external_id IS NOT NULL
           AND external_id <> ""
           AND access_token IS NOT NULL
           AND access_token <> ""
         ORDER BY id DESC
         LIMIT 20'
    );

    foreach ($candidates as $candidate) {
        $stmt->execute([
            'platform' => $platform,
            'owner_type' => $candidate['owner_type'],
            'owner_id' => $candidate['owner_id'],
        ]);
        foreach ($stmt->fetchAll() as $integration) {
            if (messaging_integration_is_usable($integration)) {
                return $integration;
            }
        }
    }

    return messaging_default_integration($platform);
}

function messaging_vk_platform_account(int $endUserId, ?string $platformUserId = null): ?array
{
    $sql = 'SELECT * FROM platform_accounts WHERE end_user_id = :end_user_id AND platform = "VK"';
    $params = ['end_user_id' => $endUserId];
    if ($platformUserId !== null && $platformUserId !== '') {
        $sql .= ' AND platform_user_id = :platform_user_id';
        $params['platform_user_id'] = preg_replace('/\D+/', '', $platformUserId) ?: $platformUserId;
    }
    $sql .= ' ORDER BY id DESC LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $account = $stmt->fetch();
    return $account ?: null;
}

function messaging_vk_permission_for_user(int $endUserId, string $platformUserId, ?string $groupId = null): ?array
{
    $groupFilter = $groupId !== null && $groupId !== '' ? ' AND p.group_id = :group_id' : '';
    $stmt = db()->prepare(
        'SELECT p.*, pa.platform AS account_platform, pa.platform_user_id,
                p.id AS permission_id, p.status AS permission_status
         FROM vk_message_permissions p
         INNER JOIN platform_accounts pa ON pa.id = p.platform_account_id
         WHERE p.end_user_id = :end_user_id
           AND pa.platform = "VK"
           AND pa.platform_user_id = :platform_user_id
           AND p.status = "allowed"
           ' . $groupFilter . '
         ORDER BY p.allowed_at DESC, p.id DESC
         LIMIT 1'
    );
    $params = [
        'end_user_id' => $endUserId,
        'platform_user_id' => preg_replace('/\D+/', '', $platformUserId) ?: $platformUserId,
    ];
    if ($groupId !== null && $groupId !== '') {
        $params['group_id'] = $groupId;
    }
    $stmt->execute($params);
    $permission = $stmt->fetch();
    // The permission belongs to the VK community, not to an editable integration row.
    // A consultant can save the same community again and receive a new integration ID.
    // In that case the client's existing VK permission must remain valid.
    return $permission ?: null;
}

function messaging_upsert_vk_permission(
    int $endUserId,
    int $platformAccountId,
    array $integration,
    string $status,
    ?string $requestKeyHash = null,
    ?string $requestExpiresAt = null
): void {
    $allowedAt = $status === 'allowed' ? date('Y-m-d H:i:s') : null;
    $deniedAt = $status === 'denied' ? date('Y-m-d H:i:s') : null;
    $stmt = db()->prepare(
        'INSERT INTO vk_message_permissions (
            end_user_id, platform_account_id, integration_id, group_id, status,
            request_key_hash, request_expires_at, requested_at, allowed_at, denied_at
         ) VALUES (
            :end_user_id, :platform_account_id, :integration_id, :group_id, :status,
            :request_key_hash, :request_expires_at, NOW(), :allowed_at, :denied_at
         )
         ON DUPLICATE KEY UPDATE
            platform_account_id = VALUES(platform_account_id),
            integration_id = VALUES(integration_id),
            status = VALUES(status),
            request_key_hash = VALUES(request_key_hash),
            request_expires_at = VALUES(request_expires_at),
            requested_at = NOW(),
            allowed_at = VALUES(allowed_at),
            denied_at = VALUES(denied_at)'
    );
    $stmt->execute([
        'end_user_id' => $endUserId,
        'platform_account_id' => $platformAccountId,
        'integration_id' => (int)$integration['id'],
        'group_id' => (string)$integration['external_id'],
        'status' => $status,
        'request_key_hash' => $requestKeyHash,
        'request_expires_at' => $requestExpiresAt,
        'allowed_at' => $allowedAt,
        'denied_at' => $deniedAt,
    ]);
}

function messaging_mark_vk_permission_allowed(int $endUserId, string $platformUserId, array $integration): void
{
    $account = messaging_vk_platform_account($endUserId, $platformUserId);
    if (!$account) {
        return;
    }

    messaging_upsert_vk_permission($endUserId, (int)$account['id'], $integration, 'allowed');
    messaging_sync_vk_account_allowed((int)$account['id']);
}

function messaging_sync_vk_account_allowed(int $platformAccountId): void
{
    $stmt = db()->prepare(
        'UPDATE platform_accounts pa
         SET pa.messages_allowed = CASE
                 WHEN EXISTS (
                     SELECT 1 FROM vk_message_permissions p
                     WHERE p.platform_account_id = pa.id AND p.status = "allowed"
                 ) THEN 1 ELSE 0 END,
             pa.messages_allowed_at = CASE
                 WHEN EXISTS (
                     SELECT 1 FROM vk_message_permissions p
                     WHERE p.platform_account_id = pa.id AND p.status = "allowed"
                 ) THEN COALESCE(pa.messages_allowed_at, NOW()) ELSE pa.messages_allowed_at END,
             pa.messages_denied_at = CASE
                 WHEN EXISTS (
                     SELECT 1 FROM vk_message_permissions p
                     WHERE p.platform_account_id = pa.id AND p.status = "allowed"
                 ) THEN NULL ELSE NOW() END
         WHERE pa.id = :id'
    );
    $stmt->execute(['id' => $platformAccountId]);
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

function messaging_audio_mime_type(string $url): ?string
{
    $path = (string)(parse_url($url, PHP_URL_PATH) ?: $url);
    $extension = strtolower((string)pathinfo($path, PATHINFO_EXTENSION));
    if ($extension === '' && str_contains($path, '/api/voice_media.php')) {
        return 'audio/ogg';
    }
    return match ($extension) {
        'ogg', 'oga' => 'audio/ogg',
        'mp3' => 'audio/mpeg',
        'm4a' => 'audio/mp4',
        default => null,
    };
}

function messaging_local_upload_path_from_url(string $url): ?string
{
    $path = (string)(parse_url($url, PHP_URL_PATH) ?: $url);
    $path = '/' . ltrim($path, '/');
    $prefix = '/admin/uploads/';
    if (!str_starts_with($path, $prefix)) {
        return null;
    }

    $relative = substr($path, strlen($prefix));
    $local = dirname(__DIR__, 2) . '/uploads/' . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);

    return is_file($local) ? $local : null;
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

    $localPath = messaging_local_upload_path_from_url($url);
    $contents = $localPath ? @file_get_contents($localPath) : @file_get_contents($url);
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

function vk_upload_message_audio(array $integration, string $userId, string $url): ?string
{
    $mimeType = messaging_audio_mime_type($url);
    if (!$mimeType) {
        return null;
    }
    $token = trim((string)($integration['access_token'] ?? ''));
    $version = (string)(app_config()['integrations']['vk_api_version'] ?? '5.199');
    $uploadServer = messaging_http_form_post('https://api.vk.com/method/docs.getMessagesUploadServer', [
        'access_token' => $token,
        'v' => $version,
        'peer_id' => $userId,
        'type' => 'audio_message',
    ]);
    $uploadUrl = (string)($uploadServer['response']['upload_url'] ?? '');
    if ($uploadUrl === '') {
        return null;
    }
    $contents = @file_get_contents($url);
    if ($contents === false || $contents === '') {
        return null;
    }
    $uploaded = messaging_http_multipart_file_post($uploadUrl, 'file', 'swpro-voice.ogg', $mimeType, $contents);
    $file = trim((string)($uploaded['file'] ?? ''));
    if ($file === '') {
        return null;
    }
    $saved = messaging_http_form_post('https://api.vk.com/method/docs.save', [
        'access_token' => $token,
        'v' => $version,
        'file' => $file,
        'title' => 'Голосовое сообщение SWPro',
    ]);
    $response = (array)($saved['response'] ?? []);
    $audio = $response['audio_message'] ?? $response['doc'] ?? ($response[0] ?? null);
    if (!is_array($audio) || empty($audio['owner_id']) || empty($audio['id'])) {
        return null;
    }
    $prefix = isset($response['audio_message']) ? 'audio_message' : 'doc';
    $attachment = $prefix . $audio['owner_id'] . '_' . $audio['id'];
    if (!empty($audio['access_key'])) {
        $attachment .= '_' . $audio['access_key'];
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

    $endUserId = !empty($ownerContext['end_user_id']) ? (int)$ownerContext['end_user_id'] : null;
    $integration = messaging_integration_for_owner(
        $platform,
        !empty($ownerContext['manager_id']) ? (int)$ownerContext['manager_id'] : null,
        !empty($ownerContext['reseller_id']) ? (int)$ownerContext['reseller_id'] : null
    );
    if (!$integration) {
        return ['ok' => false, 'error' => 'Нет активной интеграции сообщества для ' . platform_label($platform)];
    }

    // VK is the final authority for this permission. Do not block a message only
    // because Callback API has not yet delivered message_allow or an integration
    // record was recreated. A successful messages.send below repairs the local state.

    $attachments = [];
    $fallbackMediaUrls = $mediaUrls;
    if ($platform === 'VK') {
        $fallbackMediaUrls = [];
        foreach ($mediaUrls as $mediaUrl) {
            $mediaUrl = trim((string)$mediaUrl);
            if ($mediaUrl === '') {
                continue;
            }
            $vkUserId = preg_replace('/\D+/', '', $platformUserId) ?: $platformUserId;
            $attachment = messaging_audio_mime_type($mediaUrl)
                ? vk_upload_message_audio($integration, $vkUserId, $mediaUrl)
                : vk_upload_message_photo($integration, $vkUserId, $mediaUrl);
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

    if ($platform === 'VK' && !empty($result['ok']) && $endUserId) {
        try {
            messaging_mark_vk_permission_allowed($endUserId, $platformUserId, $integration);
        } catch (Throwable $error) {
            error_log('SWPro VK permission state repair failed: ' . $error->getMessage());
        }
    }

    $result['integration_id'] = (int)$integration['id'];
    return $result;
}
