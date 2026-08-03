<?php

require_once __DIR__ . '/social_messaging.php';

function vk_callback_reply(string $body, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $body;
    exit;
}

function vk_callback_group_id(array $payload): string
{
    return preg_replace('/\D+/', '', (string)($payload['group_id'] ?? '')) ?? '';
}

function vk_callback_find_integration(string $groupId): ?array
{
    if ($groupId === '') {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT *
         FROM messaging_integrations
         WHERE platform = "VK"
           AND external_id = :external_id
           AND is_active = 1
         ORDER BY FIELD(owner_type, "manager", "reseller"), id DESC
         LIMIT 1'
    );
    $stmt->execute(['external_id' => $groupId]);
    $integration = $stmt->fetch();

    return $integration ?: null;
}

function vk_callback_update_integration(int $integrationId, ?string $error = null): void
{
    $stmt = db()->prepare(
        'UPDATE messaging_integrations
         SET callback_last_event_at = NOW(),
             callback_last_error = :error
         WHERE id = :id'
    );
    $stmt->execute([
        'id' => $integrationId,
        'error' => $error,
    ]);
}

function vk_callback_event_key(array $payload): string
{
    $eventId = trim((string)($payload['event_id'] ?? ''));
    if ($eventId !== '') {
        return $eventId;
    }

    $type = (string)($payload['type'] ?? 'unknown');
    $object = is_array($payload['object'] ?? null) ? $payload['object'] : [];
    $message = is_array($object['message'] ?? null) ? $object['message'] : $object;
    $messageId = trim((string)($message['id'] ?? $message['conversation_message_id'] ?? ''));
    $fromId = trim((string)($message['from_id'] ?? $object['user_id'] ?? ''));
    $date = trim((string)($message['date'] ?? $payload['date'] ?? ''));

    return $type . ':' . ($messageId !== '' ? $messageId : ($fromId . ':' . $date . ':' . sha1(json_encode($object))));
}

function vk_callback_register_event(array $payload, string $groupId): bool
{
    $stmt = db()->prepare(
        'INSERT IGNORE INTO social_callback_events (
            platform, external_id, event_id, event_type, payload_json
         ) VALUES (
            "VK", :external_id, :event_id, :event_type, :payload_json
         )'
    );
    $stmt->execute([
        'external_id' => $groupId,
        'event_id' => vk_callback_event_key($payload),
        'event_type' => (string)($payload['type'] ?? 'unknown'),
        'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    return $stmt->rowCount() > 0;
}

function vk_callback_mark_processed(array $payload, string $groupId): void
{
    $stmt = db()->prepare(
        'UPDATE social_callback_events
         SET processed_at = NOW()
         WHERE platform = "VK"
           AND external_id = :external_id
           AND event_id = :event_id'
    );
    $stmt->execute([
        'external_id' => $groupId,
        'event_id' => vk_callback_event_key($payload),
    ]);
}

function vk_callback_owner_context(array $integration): array
{
    $ownerType = (string)($integration['owner_type'] ?? '');
    $ownerId = (int)($integration['owner_id'] ?? 0);
    if ($ownerType === 'manager' && $ownerId > 0) {
        $stmt = db()->prepare('SELECT id, reseller_id FROM managers WHERE id = :id AND is_active = 1 LIMIT 1');
        $stmt->execute(['id' => $ownerId]);
        $manager = $stmt->fetch();
        if ($manager) {
            return [
                'manager_id' => (int)$manager['id'],
                'reseller_id' => !empty($manager['reseller_id']) ? (int)$manager['reseller_id'] : null,
            ];
        }
    }

    if ($ownerType === 'reseller' && $ownerId > 0) {
        $stmt = db()->prepare('SELECT id FROM resellers WHERE id = :id AND is_active = 1 LIMIT 1');
        $stmt->execute(['id' => $ownerId]);
        if ($stmt->fetch()) {
            return ['manager_id' => null, 'reseller_id' => $ownerId];
        }
    }

    return ['manager_id' => null, 'reseller_id' => null];
}

function vk_callback_owner_referral_code(array $integration): ?string
{
    $ownerType = (string)($integration['owner_type'] ?? '');
    $ownerId = (int)($integration['owner_id'] ?? 0);
    if ($ownerId <= 0) {
        return null;
    }

    if ($ownerType === 'manager') {
        $stmt = db()->prepare('SELECT referral_code FROM managers WHERE id = :id AND is_active = 1 LIMIT 1');
        $stmt->execute(['id' => $ownerId]);
        $code = trim((string)($stmt->fetchColumn() ?: ''));
        return $code !== '' ? $code : null;
    }

    if ($ownerType === 'reseller') {
        $stmt = db()->prepare('SELECT referral_code FROM resellers WHERE id = :id AND is_active = 1 LIMIT 1');
        $stmt->execute(['id' => $ownerId]);
        $code = trim((string)($stmt->fetchColumn() ?: ''));
        return $code !== '' ? $code : null;
    }

    return null;
}

function vk_callback_mini_app_url(array $integration): string
{
    $config = app_config();
    $code = vk_callback_owner_referral_code($integration);
    $encodedCode = $code !== null ? rawurlencode($code) : '';
    $vkAppId = preg_replace('/\D+/', '', (string)($config['integrations']['vk_app_id'] ?? '')) ?: '';
    if ($vkAppId !== '') {
        return 'https://vk.com/app' . $vkAppId . ($encodedCode !== '' ? '#ref=' . $encodedCode : '');
    }

    $miniAppUrl = trim((string)($config['integrations']['mini_app_url'] ?? ''));
    if ($miniAppUrl === '') {
        $publicUrl = rtrim((string)($config['app']['public_url'] ?? ''), '/');
        $miniAppUrl = $publicUrl !== '' ? $publicUrl . '/vk-mini-app/' : 'https://swpro.ru/vk-mini-app/';
    }

    if ($encodedCode === '') {
        return $miniAppUrl;
    }

    $separator = str_contains($miniAppUrl, '?') ? '&' : '?';
    return $miniAppUrl . $separator . 'ref=' . $encodedCode;
}

function vk_callback_is_start_command(array $message): bool
{
    $text = trim((string)($message['text'] ?? ''));
    $normalized = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
    $normalized = trim($normalized);

    if (in_array($normalized, ['начать', 'старт', 'start', '/start', 'menu', 'меню'], true)) {
        return true;
    }

    $payload = $message['payload'] ?? null;
    if (is_string($payload) && $payload !== '') {
        $decoded = json_decode($payload, true);
        $payload = is_array($decoded) ? $decoded : $payload;
    }
    if (is_array($payload)) {
        $command = function_exists('mb_strtolower')
            ? mb_strtolower(trim((string)($payload['command'] ?? $payload['cmd'] ?? $payload['type'] ?? '')), 'UTF-8')
            : strtolower(trim((string)($payload['command'] ?? $payload['cmd'] ?? $payload['type'] ?? '')));
        return in_array($command, ['start', 'начать', 'menu'], true);
    }

    return false;
}

function vk_callback_send_start_response(array $integration, string $platformUserId, array $user): void
{
    $miniAppUrl = vk_callback_mini_app_url($integration);
    $text = "Здравствуйте! Здесь можно пройти чек-ап и получить ответ консультанта. Нажмите «Начать» или откройте приложение SWPro.\n\n"
        . "Открыть SWPro:\n" . $miniAppUrl;

    $result = send_vk_community_message($integration, $platformUserId, $text);
    if (empty($result['ok'])) {
        throw new RuntimeException((string)($result['error'] ?? 'VK start response failed'));
    }
}

function vk_callback_http_form_get(string $url, array $payload): array
{
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($payload),
            'timeout' => 4,
            'ignore_errors' => true,
        ],
    ]);
    $response = @file_get_contents($url, false, $context);
    $decoded = $response ? json_decode($response, true) : null;

    return is_array($decoded) ? $decoded : [];
}

function vk_callback_fetch_user_profile(array $integration, string $userId): array
{
    $token = trim((string)($integration['access_token'] ?? ''));
    if ($token === '' || $userId === '') {
        return [];
    }

    $version = (string)(app_config()['integrations']['vk_api_version'] ?? '5.199');
    $response = vk_callback_http_form_get('https://api.vk.com/method/users.get', [
        'access_token' => $token,
        'v' => $version,
        'user_ids' => $userId,
        'fields' => 'screen_name,city,bdate,sex',
    ]);

    $profile = is_array($response['response'][0] ?? null) ? $response['response'][0] : [];
    if (!$profile) {
        return [];
    }

    $birthDate = null;
    $bdate = trim((string)($profile['bdate'] ?? ''));
    if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $bdate, $matches)) {
        $birthDate = sprintf('%04d-%02d-%02d', (int)$matches[3], (int)$matches[2], (int)$matches[1]);
    }

    return [
        'username' => $profile['screen_name'] ?? null,
        'first_name' => $profile['first_name'] ?? null,
        'last_name' => $profile['last_name'] ?? null,
        'display_name' => trim((string)($profile['first_name'] ?? '') . ' ' . (string)($profile['last_name'] ?? '')) ?: null,
        'gender' => match ((int)($profile['sex'] ?? 0)) {
            1 => 'female',
            2 => 'male',
            default => null,
        },
        'birth_date' => $birthDate,
        'city' => is_array($profile['city'] ?? null) ? ($profile['city']['title'] ?? null) : null,
    ];
}

function vk_callback_fill_profile_if_missing(array $user, array $profile): array
{
    $assignments = [];
    $params = ['id' => (int)$user['id']];
    foreach (['username', 'first_name', 'last_name', 'gender', 'birth_date', 'city'] as $field) {
        $value = trim((string)($profile[$field] ?? ''));
        if ($value === '') {
            continue;
        }
        if (trim((string)($user[$field] ?? '')) === '') {
            $assignments[] = "$field = :$field";
            $params[$field] = $value;
        }
    }
    if (!$assignments) {
        return $user;
    }

    $stmt = db()->prepare('UPDATE end_users SET ' . implode(', ', $assignments) . ' WHERE id = :id');
    $stmt->execute($params);
    $updated = db()->prepare('SELECT * FROM end_users WHERE id = :id LIMIT 1');
    $updated->execute(['id' => $user['id']]);

    return $updated->fetch() ?: $user;
}

function vk_callback_user(array $integration, string $userId, array $profile = []): array
{
    $owner = vk_callback_owner_context($integration);
    $stmt = db()->prepare(
        'SELECT u.*
         FROM platform_accounts pa
         INNER JOIN end_users u ON u.id = pa.end_user_id
         WHERE pa.platform = "VK" AND pa.platform_user_id = :platform_user_id
         LIMIT 1'
    );
    $stmt->execute(['platform_user_id' => $userId]);
    $user = $stmt->fetch();

    if (!$user) {
        $legacy = db()->prepare('SELECT * FROM end_users WHERE platform = "VK" AND platform_user_id = :platform_user_id LIMIT 1');
        $legacy->execute(['platform_user_id' => $userId]);
        $user = $legacy->fetch();
    }

    if ($user) {
        ensure_platform_account(
            (int)$user['id'],
            'VK',
            $userId,
            $profile['username'] ?? null,
            $profile['first_name'] ?? null,
            $profile['last_name'] ?? null,
            $profile['display_name'] ?? null
        );
        if (empty($user['manager_id']) && empty($user['reseller_id']) && ($owner['manager_id'] || $owner['reseller_id'])) {
            $attach = db()->prepare(
                'UPDATE end_users
                 SET manager_id = :manager_id, reseller_id = :reseller_id
                 WHERE id = :id AND manager_id IS NULL AND reseller_id IS NULL'
            );
            $attach->execute([
                'manager_id' => $owner['manager_id'],
                'reseller_id' => $owner['reseller_id'],
                'id' => (int)$user['id'],
            ]);
            $user['manager_id'] = $owner['manager_id'];
            $user['reseller_id'] = $owner['reseller_id'];
        }
        $touch = db()->prepare('UPDATE end_users SET last_activity_at = NOW() WHERE id = :id');
        $touch->execute(['id' => (int)$user['id']]);

        return vk_callback_fill_profile_if_missing($user, $profile);
    }

    $insert = db()->prepare(
        'INSERT INTO end_users (
            reseller_id, manager_id, platform, platform_user_id,
            username, first_name, last_name, gender, birth_date, city, last_activity_at
         ) VALUES (
            :reseller_id, :manager_id, "VK", :platform_user_id,
            :username, :first_name, :last_name, :gender, :birth_date, :city, NOW()
         )'
    );
    $insert->execute([
        'reseller_id' => $owner['reseller_id'],
        'manager_id' => $owner['manager_id'],
        'platform_user_id' => $userId,
        'username' => $profile['username'] ?? null,
        'first_name' => $profile['first_name'] ?? null,
        'last_name' => $profile['last_name'] ?? null,
        'gender' => $profile['gender'] ?? null,
        'birth_date' => $profile['birth_date'] ?? null,
        'city' => $profile['city'] ?? null,
    ]);
    $endUserId = (int)db()->lastInsertId();
    ensure_platform_account(
        $endUserId,
        'VK',
        $userId,
        $profile['username'] ?? null,
        $profile['first_name'] ?? null,
        $profile['last_name'] ?? null,
        $profile['display_name'] ?? null
    );

    $stmt = db()->prepare('SELECT * FROM end_users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $endUserId]);

    return $stmt->fetch() ?: [];
}

function vk_callback_attachment_url(array $attachment): string
{
    $type = (string)($attachment['type'] ?? '');
    $payload = is_array($attachment[$type] ?? null) ? $attachment[$type] : [];
    if ($type === 'photo') {
        $sizes = is_array($payload['sizes'] ?? null) ? $payload['sizes'] : [];
        usort($sizes, static fn(array $a, array $b): int => (((int)($b['width'] ?? 0) * (int)($b['height'] ?? 0)) <=> ((int)($a['width'] ?? 0) * (int)($a['height'] ?? 0))));
        return (string)($sizes[0]['url'] ?? '');
    }
    if ($type === 'sticker') {
        $images = is_array($payload['images'] ?? null) ? $payload['images'] : [];
        if (!$images && is_array($payload['images_with_background'] ?? null)) {
            $images = $payload['images_with_background'];
        }
        usort($images, static fn(array $a, array $b): int => (((int)($b['width'] ?? 0) * (int)($b['height'] ?? 0)) <=> ((int)($a['width'] ?? 0) * (int)($a['height'] ?? 0))));
        return (string)($images[0]['url'] ?? '');
    }
    if ($type === 'video' && !empty($payload['owner_id']) && !empty($payload['id'])) {
        $url = 'https://vk.com/video' . $payload['owner_id'] . '_' . $payload['id'];
        return !empty($payload['access_key']) ? $url . '_' . $payload['access_key'] : $url;
    }
    if ($type === 'audio') {
        return (string)($payload['url'] ?? '');
    }
    if ($type === 'audio_message') {
        return (string)($payload['link_mp3'] ?? $payload['link_ogg'] ?? '');
    }
    if ($type === 'doc') {
        return (string)($payload['url'] ?? '');
    }
    if ($type === 'link') {
        return (string)($payload['url'] ?? '');
    }

    return '';
}

function vk_callback_attachment_title(array $attachment): string
{
    $type = (string)($attachment['type'] ?? '');
    $payload = is_array($attachment[$type] ?? null) ? $attachment[$type] : [];

    return match ($type) {
        'photo' => 'Фото',
        'video' => 'Видео' . (!empty($payload['title']) ? ': ' . $payload['title'] : ''),
        'audio_message' => 'Голосовое сообщение',
        'doc' => 'Документ' . (!empty($payload['title']) ? ': ' . $payload['title'] : ''),
        'audio' => 'Аудио' . (!empty($payload['artist']) || !empty($payload['title'])
            ? ': ' . trim((string)($payload['artist'] ?? '') . ' - ' . (string)($payload['title'] ?? ''), ' -')
            : ''),
        'link' => 'Ссылка' . (!empty($payload['title']) ? ': ' . $payload['title'] : ''),
        'sticker' => 'Стикер',
        default => $type !== '' ? $type : 'Вложение',
    };
}

function vk_callback_normalize_attachments(array $attachments): array
{
    $items = [];
    foreach ($attachments as $attachment) {
        if (!is_array($attachment)) {
            continue;
        }
        $items[] = [
            'type' => (string)($attachment['type'] ?? 'unknown'),
            'title' => vk_callback_attachment_title($attachment),
            'url' => vk_callback_attachment_url($attachment),
            'raw' => $attachment,
        ];
    }

    return $items;
}

function vk_callback_attachment_text(array $items): string
{
    if (!$items) {
        return '';
    }

    $lines = [];
    foreach ($items as $item) {
        $line = '• ' . (string)$item['title'];
        if (!empty($item['url'])) {
            $line .= ': ' . $item['url'];
        }
        $lines[] = $line;
    }

    return implode("\n", $lines);
}

function vk_callback_mark_messages_allowed(int $endUserId, string $platformUserId, ?bool $allowed): void
{
    $fields = ['last_inbound_message_at = NOW()'];
    if ($allowed === true) {
        $fields[] = 'messages_allowed = 1';
        $fields[] = 'messages_allowed_at = NOW()';
        $fields[] = 'messages_denied_at = NULL';
    } elseif ($allowed === false) {
        $fields[] = 'messages_allowed = 0';
        $fields[] = 'messages_denied_at = NOW()';
    }

    $stmt = db()->prepare(
        'UPDATE platform_accounts
         SET ' . implode(', ', $fields) . '
         WHERE end_user_id = :end_user_id
           AND platform = "VK"
           AND platform_user_id = :platform_user_id'
    );
    $stmt->execute([
        'end_user_id' => $endUserId,
        'platform_user_id' => $platformUserId,
    ]);
}

function vk_callback_handle_message_new(array $payload, array $integration): void
{
    $object = is_array($payload['object'] ?? null) ? $payload['object'] : [];
    $message = is_array($object['message'] ?? null) ? $object['message'] : $object;
    if ((int)($message['out'] ?? 0) === 1) {
        return;
    }

    $fromId = preg_replace('/\D+/', '', (string)($message['from_id'] ?? $message['user_id'] ?? '')) ?? '';
    if ($fromId === '') {
        return;
    }

    $profile = vk_callback_fetch_user_profile($integration, $fromId);
    $user = vk_callback_user($integration, $fromId, $profile);
    if (!$user) {
        throw new RuntimeException('VK user could not be created');
    }

    vk_callback_mark_messages_allowed((int)$user['id'], $fromId, true);

    $text = trim((string)($message['text'] ?? ''));
    if (vk_callback_is_start_command($message)) {
        vk_callback_send_start_response($integration, $fromId, $user);
        return;
    }

    $attachments = vk_callback_normalize_attachments(is_array($message['attachments'] ?? null) ? $message['attachments'] : []);
    $leadMessage = $text !== '' ? $text : 'Клиент отправил вложение без текста.';

    $leadId = create_lead_for_user($user, [
        'platform' => 'VK',
        'request_type' => 'consultation',
        'message' => $leadMessage,
    ]);

    $sourceMessageId = trim((string)($message['id'] ?? $message['conversation_message_id'] ?? vk_callback_event_key($payload)));
    $update = db()->prepare(
        'UPDATE leads
         SET source_message_id = :source_message_id,
             attachments_json = :attachments_json
         WHERE id = :id'
    );
    $update->execute([
        'source_message_id' => $sourceMessageId,
        'attachments_json' => $attachments ? json_encode($attachments, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        'id' => $leadId,
    ]);
}

function vk_callback_handle_message_permission(array $payload, array $integration, bool $allowed): void
{
    $object = is_array($payload['object'] ?? null) ? $payload['object'] : [];
    $userId = preg_replace('/\D+/', '', (string)($object['user_id'] ?? $object['from_id'] ?? '')) ?? '';
    if ($userId === '') {
        return;
    }

    $profile = vk_callback_fetch_user_profile($integration, $userId);
    $user = vk_callback_user($integration, $userId, $profile);
    if ($user) {
        vk_callback_mark_messages_allowed((int)$user['id'], $userId, $allowed);
    }
}

function vk_callback_handle(array $payload): void
{
    $type = (string)($payload['type'] ?? '');
    $groupId = vk_callback_group_id($payload);
    $integration = vk_callback_find_integration($groupId);
    if (!$integration) {
        vk_callback_reply('integration not found', 404);
    }

    if ($type === 'confirmation') {
        $code = trim((string)($integration['callback_confirmation_code'] ?? ''));
        vk_callback_reply($code !== '' ? $code : 'confirmation code is not configured', $code !== '' ? 200 : 500);
    }

    $expectedSecret = trim((string)($integration['callback_secret'] ?? ''));
    $actualSecret = trim((string)($payload['secret'] ?? ''));
    if ($expectedSecret !== '' && !hash_equals($expectedSecret, $actualSecret)) {
        vk_callback_update_integration((int)$integration['id'], 'Callback secret mismatch');
        vk_callback_reply('bad secret', 403);
    }

    if (!vk_callback_register_event($payload, $groupId)) {
        vk_callback_reply('ok');
    }

    try {
        if ($type === 'message_new') {
            vk_callback_handle_message_new($payload, $integration);
        } elseif ($type === 'message_allow') {
            vk_callback_handle_message_permission($payload, $integration, true);
        } elseif ($type === 'message_deny') {
            vk_callback_handle_message_permission($payload, $integration, false);
        }
        vk_callback_mark_processed($payload, $groupId);
        vk_callback_update_integration((int)$integration['id']);
    } catch (Throwable $e) {
        vk_callback_update_integration((int)$integration['id'], $e->getMessage());
    }

    vk_callback_reply('ok');
}
