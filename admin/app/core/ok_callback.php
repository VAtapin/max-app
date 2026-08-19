<?php

require_once __DIR__ . '/vk_callback.php';

function ok_callback_public_url(array $integration): string
{
    $publicUrl = rtrim((string)(app_config()['app']['public_url'] ?? ''), '/');
    $publicUrl = $publicUrl !== '' ? $publicUrl : 'https://swpro.ru';
    return $publicUrl . '/api/ok_callback.php?key=' . rawurlencode((string)$integration['callback_secret']);
}

function ok_callback_subscribe_integration(int $integrationId): array
{
    $stmt = db()->prepare('SELECT * FROM messaging_integrations WHERE id = :id AND platform = "OK" LIMIT 1');
    $stmt->execute(['id' => $integrationId]);
    $integration = $stmt->fetch();
    if (!$integration || !messaging_integration_is_usable($integration)) {
        return ['ok' => false, 'error' => 'Для webhook OK заполните ID группы, токен Bot API и включите подключение.'];
    }

    if (trim((string)($integration['callback_secret'] ?? '')) === '') {
        // The callback is also used outside the CRUD page. Keep its secret
        // generation local to the core instead of depending on a CRUD helper.
        $secret = bin2hex(random_bytes(24));
        db()->prepare('UPDATE messaging_integrations SET callback_secret = :secret WHERE id = :id')
            ->execute(['secret' => $secret, 'id' => $integrationId]);
        $integration['callback_secret'] = $secret;
    }

    $response = messaging_http_json_post(
        'https://api.ok.ru/graph/me/subscribe?access_token=' . rawurlencode((string)$integration['access_token']),
        ['url' => ok_callback_public_url($integration)]
    );
    $ok = !empty($response['success']);
    $error = $ok ? null : (string)($response['error_msg'] ?? $response['error'] ?? 'OK не подтвердил подписку на webhook.');
    db()->prepare(
        'UPDATE messaging_integrations
         SET callback_subscribed_at = CASE WHEN :ok = 1 THEN NOW() ELSE NULL END,
             callback_last_error = :error
         WHERE id = :id'
    )->execute(['ok' => $ok ? 1 : 0, 'error' => $error, 'id' => $integrationId]);

    return ['ok' => $ok, 'error' => $error, 'response' => $response];
}

function ok_callback_find_integration(string $secret): ?array
{
    if ($secret === '') {
        return null;
    }
    $stmt = db()->prepare(
        'SELECT * FROM messaging_integrations
         WHERE platform = "OK" AND callback_secret = :secret AND is_active = 1
         ORDER BY id DESC LIMIT 2'
    );
    $stmt->execute(['secret' => $secret]);
    $rows = $stmt->fetchAll();
    return count($rows) === 1 ? $rows[0] : null;
}

function ok_callback_event_key(array $payload): string
{
    $message = is_array($payload['message'] ?? null) ? $payload['message'] : [];
    $mid = trim((string)($message['mid'] ?? ''));
    if ($mid !== '') {
        return $mid;
    }
    return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function ok_callback_register_event(array $payload, array $integration): bool
{
    $stmt = db()->prepare(
        'INSERT IGNORE INTO social_callback_events
            (platform, external_id, event_id, event_type, payload_json)
         VALUES ("OK", :external_id, :event_id, :event_type, :payload)'
    );
    $stmt->execute([
        'external_id' => (string)$integration['external_id'],
        'event_id' => ok_callback_event_key($payload),
        'event_type' => (string)($payload['webhookType'] ?? 'UNKNOWN'),
        'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    return $stmt->rowCount() === 1;
}

function ok_callback_mark_processed(array $payload, array $integration): void
{
    db()->prepare(
        'UPDATE social_callback_events
         SET processed_at = NOW()
         WHERE platform = "OK" AND external_id = :external_id AND event_id = :event_id'
    )->execute([
        'external_id' => (string)$integration['external_id'],
        'event_id' => ok_callback_event_key($payload),
    ]);
}

function ok_callback_update_integration(int $integrationId, ?string $error = null): void
{
    db()->prepare(
        'UPDATE messaging_integrations
         SET callback_last_event_at = NOW(), callback_last_error = :error
         WHERE id = :id'
    )->execute(['id' => $integrationId, 'error' => $error]);
}

function ok_callback_user(array $integration, string $platformUserId): array
{
    $owner = vk_callback_owner_context($integration);
    $stmt = db()->prepare(
        'SELECT u.* FROM platform_accounts pa
         INNER JOIN end_users u ON u.id = pa.end_user_id
         WHERE pa.platform = "OK" AND pa.platform_user_id = :platform_user_id
         LIMIT 1'
    );
    $stmt->execute(['platform_user_id' => $platformUserId]);
    $user = $stmt->fetch() ?: null;
    if (!$user) {
        $legacy = db()->prepare('SELECT * FROM end_users WHERE platform = "OK" AND platform_user_id = :platform_user_id LIMIT 1');
        $legacy->execute(['platform_user_id' => $platformUserId]);
        $user = $legacy->fetch() ?: null;
    }

    if (!$user) {
        $insert = db()->prepare(
            'INSERT INTO end_users (reseller_id, manager_id, platform, platform_user_id, last_activity_at)
             VALUES (:reseller_id, :manager_id, "OK", :platform_user_id, NOW())'
        );
        $insert->execute([
            'reseller_id' => $owner['reseller_id'],
            'manager_id' => $owner['manager_id'],
            'platform_user_id' => $platformUserId,
        ]);
        $userId = (int)db()->lastInsertId();
        ensure_platform_account($userId, 'OK', $platformUserId);
        $find = db()->prepare('SELECT * FROM end_users WHERE id = :id LIMIT 1');
        $find->execute(['id' => $userId]);
        return $find->fetch() ?: [];
    }

    ensure_platform_account((int)$user['id'], 'OK', $platformUserId);
    if (empty($user['manager_id']) && empty($user['reseller_id']) && ($owner['manager_id'] || $owner['reseller_id'])) {
        db()->prepare(
            'UPDATE end_users SET manager_id = :manager_id, reseller_id = :reseller_id
             WHERE id = :id AND manager_id IS NULL AND reseller_id IS NULL'
        )->execute([
            'manager_id' => $owner['manager_id'],
            'reseller_id' => $owner['reseller_id'],
            'id' => (int)$user['id'],
        ]);
        $user['manager_id'] = $owner['manager_id'];
        $user['reseller_id'] = $owner['reseller_id'];
    }
    db()->prepare('UPDATE end_users SET last_activity_at = NOW() WHERE id = :id')->execute(['id' => (int)$user['id']]);
    return $user;
}

function ok_callback_attachments(array $attachments): array
{
    $items = [];
    foreach ($attachments as $attachment) {
        if (!is_array($attachment)) {
            continue;
        }
        $type = strtolower(trim((string)($attachment['type'] ?? 'Вложение')));
        $payload = is_array($attachment['payload'] ?? null) ? $attachment['payload'] : $attachment;
        $url = trim((string)($payload['url'] ?? $payload['photoUrl'] ?? $payload['link'] ?? ''));
        $items[] = [
            'type' => $type,
            'title' => $type !== '' ? ucfirst($type) : 'Вложение',
            'url' => $url,
        ];
    }
    return $items;
}

function ok_callback_handle_message(array $payload, array $integration): void
{
    $sender = is_array($payload['sender'] ?? null) ? $payload['sender'] : [];
    $platformUserId = trim((string)($sender['user_id'] ?? ''));
    $platformUserId = preg_replace('/^user:/i', '', $platformUserId) ?? '';
    if ($platformUserId === '') {
        return;
    }

    $user = ok_callback_user($integration, $platformUserId);
    if (empty($user['id'])) {
        throw new RuntimeException('Не удалось определить клиента OK.');
    }

    $account = messaging_ok_platform_account((int)$user['id'], $platformUserId);
    if ($account) {
        messaging_upsert_ok_permission((int)$user['id'], (int)$account['id'], $integration, 'allowed');
        messaging_sync_ok_account_allowed((int)$account['id']);
        db()->prepare(
            'UPDATE platform_accounts SET last_inbound_message_at = NOW()
             WHERE id = :id'
        )->execute(['id' => (int)$account['id']]);
    }

    $message = is_array($payload['message'] ?? null) ? $payload['message'] : [];
    $attachments = ok_callback_attachments(is_array($message['attachments'] ?? null) ? $message['attachments'] : []);
    $text = trim((string)($message['text'] ?? ''));
    $leadText = $text !== '' ? $text : 'Клиент отправил вложение без текста.';
    $leadId = create_lead_for_user($user, ['platform' => 'OK', 'request_type' => 'consultation', 'message' => $leadText]);
    $dedupe = 'ok:' . ok_callback_event_key($payload);
    live_chat_record_client_message((int)$user['id'], 'OK', $leadText, $attachments, $dedupe);
    db()->prepare('UPDATE leads SET source_message_id = :source_message_id, attachments_json = :attachments WHERE id = :id')
        ->execute([
            'source_message_id' => (string)($message['mid'] ?? $dedupe),
            'attachments' => $attachments ? json_encode($attachments, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'id' => $leadId,
        ]);
}

function ok_callback_handle(array $payload, string $secret): void
{
    $integration = ok_callback_find_integration($secret);
    if (!$integration) {
        http_response_code(403);
        echo json_encode(['ok' => false]);
        return;
    }
    if (!ok_callback_register_event($payload, $integration)) {
        echo json_encode(['ok' => true]);
        return;
    }

    try {
        if ((string)($payload['webhookType'] ?? '') === 'MESSAGE_CREATED') {
            ok_callback_handle_message($payload, $integration);
        }
        ok_callback_mark_processed($payload, $integration);
        ok_callback_update_integration((int)$integration['id']);
    } catch (Throwable $error) {
        ok_callback_update_integration((int)$integration['id'], mb_substr($error->getMessage(), 0, 1000, 'UTF-8'));
    }

    echo json_encode(['ok' => true]);
}
