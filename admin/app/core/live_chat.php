<?php

require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/lead_responses.php';

function live_chat_client_row(int $endUserId): ?array
{
    $stmt = db()->prepare('SELECT * FROM end_users WHERE id = :id AND merged_into_user_id IS NULL LIMIT 1');
    $stmt->execute(['id' => $endUserId]);
    return $stmt->fetch() ?: null;
}

function live_chat_admin_can_access_client(array $admin, int $endUserId): bool
{
    if ($endUserId <= 0) return false;
    [$where, $params] = scope_where_for_users($admin);
    $where = $where !== '' ? $where . ' AND id = :chat_user_id' : 'WHERE id = :chat_user_id';
    $params['chat_user_id'] = $endUserId;
    $stmt = db()->prepare('SELECT COUNT(*) FROM end_users ' . $where);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn() > 0;
}

function live_chat_team_root(array $admin): ?int
{
    $resellerId = ($admin['role'] ?? '') === 'manager'
        ? team_manager_reseller_id((int)($admin['manager_id'] ?? 0))
        : (int)($admin['reseller_id'] ?? 0);
    if (!$resellerId) return null;
    $ancestors = team_reseller_ancestor_ids($resellerId, true);
    return $ancestors ? (int)$ancestors[0] : $resellerId;
}

function live_chat_ensure_client_thread(int $endUserId): int
{
    $stmt = db()->prepare('INSERT INTO chat_threads (thread_type, end_user_id) VALUES ("client", :user_id) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)');
    $stmt->execute(['user_id' => $endUserId]);
    return (int)db()->lastInsertId();
}

function live_chat_ensure_team_thread(array $admin): ?int
{
    $rootId = live_chat_team_root($admin);
    if (!$rootId) return null;
    $stmt = db()->prepare('INSERT INTO chat_threads (thread_type, root_reseller_id, title) VALUES ("team", :root_id, "Чат команды") ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)');
    $stmt->execute(['root_id' => $rootId]);
    return (int)db()->lastInsertId();
}

function live_chat_thread(int $threadId): ?array
{
    $stmt = db()->prepare('SELECT * FROM chat_threads WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $threadId]);
    return $stmt->fetch() ?: null;
}

function live_chat_admin_can_access_thread(array $admin, array $thread): bool
{
    if (($admin['role'] ?? '') === 'superadmin') return true;
    if (($thread['thread_type'] ?? '') === 'client') {
        return live_chat_admin_can_access_client($admin, (int)($thread['end_user_id'] ?? 0));
    }
    return (int)($thread['root_reseller_id'] ?? 0) > 0
        && live_chat_team_root($admin) === (int)$thread['root_reseller_id'];
}

function live_chat_insert_message(int $threadId, string $senderType, ?int $adminId, ?int $endUserId, string $channel, string $text, array $attachments = [], string $status = 'sent', ?string $dedupeKey = null): int
{
    $text = mb_substr(trim($text), 0, 8000, 'UTF-8');
    $stmt = db()->prepare('INSERT INTO chat_messages (thread_id, sender_type, sender_admin_user_id, sender_end_user_id, channel, message_text, attachments_json, status, dedupe_key) VALUES (:thread_id, :sender_type, :admin_id, :user_id, :channel, :message_text, :attachments, :status, :dedupe_key) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)');
    $stmt->execute([
        'thread_id' => $threadId,
        'sender_type' => $senderType,
        'admin_id' => $adminId,
        'user_id' => $endUserId,
        'channel' => in_array($channel, ['internal','web','telegram','VK','OK','MAX'], true) ? $channel : 'internal',
        'message_text' => $text,
        'attachments' => $attachments ? json_encode(array_values($attachments), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        'status' => $status,
        'dedupe_key' => $dedupeKey,
    ]);
    $messageId = (int)db()->lastInsertId();
    db()->prepare('UPDATE chat_threads SET last_message_at = NOW() WHERE id = :id')->execute(['id' => $threadId]);
    return $messageId;
}

function live_chat_record_client_message(int $endUserId, string $channel, string $text, array $attachments = [], ?string $dedupeKey = null): int
{
    $threadId = live_chat_ensure_client_thread($endUserId);
    return live_chat_insert_message($threadId, 'client', null, $endUserId, normalize_platform($channel) ?: 'web', $text, $attachments, 'delivered', $dedupeKey);
}

function live_chat_backfill_client(int $endUserId): int
{
    $threadId = live_chat_ensure_client_thread($endUserId);
    $leadStmt = db()->prepare('SELECT id, source_platform, message, attachments_json, created_at FROM leads WHERE end_user_id = :user_id ORDER BY id');
    $leadStmt->execute(['user_id' => $endUserId]);
    $insert = db()->prepare('INSERT IGNORE INTO chat_messages (thread_id, sender_type, sender_end_user_id, channel, message_text, attachments_json, status, dedupe_key, created_at) VALUES (:thread_id, "client", :user_id, :channel, :message, :attachments, "delivered", :dedupe, :created_at)');
    foreach ($leadStmt->fetchAll() as $row) {
        $insert->execute(['thread_id' => $threadId, 'user_id' => $endUserId, 'channel' => normalize_platform((string)$row['source_platform']) ?: 'web', 'message' => (string)$row['message'], 'attachments' => $row['attachments_json'], 'dedupe' => 'legacy-lead:' . (int)$row['id'], 'created_at' => $row['created_at']]);
    }
    $responseStmt = db()->prepare('SELECT lr.id, lr.admin_user_id, lr.platform, lr.message_text, lr.attachment_path, lr.status, lr.created_at FROM lead_responses lr INNER JOIN leads l ON l.id = lr.lead_id WHERE l.end_user_id = :user_id ORDER BY lr.id');
    $responseStmt->execute(['user_id' => $endUserId]);
    $responseInsert = db()->prepare('INSERT IGNORE INTO chat_messages (thread_id, sender_type, sender_admin_user_id, channel, message_text, attachments_json, status, dedupe_key, created_at) VALUES (:thread_id, "admin", :admin_id, :channel, :message, :attachments, :status, :dedupe, :created_at)');
    foreach ($responseStmt->fetchAll() as $row) {
        $status = in_array((string)$row['status'], ['pending','sent','delivered','read','failed'], true) ? (string)$row['status'] : 'sent';
        $legacyAttachments = trim((string)($row['attachment_path'] ?? ''));
        $responseInsert->execute(['thread_id' => $threadId, 'admin_id' => $row['admin_user_id'], 'channel' => normalize_platform((string)$row['platform']) ?: 'web', 'message' => (string)$row['message_text'], 'attachments' => $legacyAttachments !== '' ? json_encode([$legacyAttachments], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null, 'status' => $status, 'dedupe' => 'legacy-response:' . (int)$row['id'], 'created_at' => $row['created_at']]);
    }
    db()->prepare('UPDATE chat_threads SET last_message_at = (SELECT MAX(created_at) FROM chat_messages WHERE thread_id = :message_thread) WHERE id = :thread_id')->execute(['message_thread' => $threadId, 'thread_id' => $threadId]);
    return $threadId;
}

function live_chat_client_channel(array $user, ?string $requested = null): string
{
    $requested = normalize_platform($requested);
    $allowed = ['telegram','VK','OK','MAX','web'];
    if (in_array($requested, $allowed, true)) return $requested;
    $stmt = db()->prepare('SELECT platform FROM platform_accounts WHERE end_user_id = :user_id ORDER BY FIELD(platform, "VK", "telegram", "MAX", "OK", "web"), id DESC LIMIT 1');
    $stmt->execute(['user_id' => (int)$user['id']]);
    $platform = normalize_platform((string)($stmt->fetchColumn() ?: $user['platform'] ?? 'web'));
    return in_array($platform, $allowed, true) ? $platform : 'web';
}

function live_chat_platform_user_id(int $endUserId, string $platform): ?string
{
    $stmt = db()->prepare('SELECT platform_user_id FROM platform_accounts WHERE end_user_id = :user_id AND platform = :platform ORDER BY id DESC LIMIT 1');
    $stmt->execute(['user_id' => $endUserId, 'platform' => normalize_platform($platform)]);
    $value = trim((string)($stmt->fetchColumn() ?: ''));
    if ($value !== '') return $value;
    $stmt = db()->prepare('SELECT platform_user_id FROM end_users WHERE id = :id AND platform = :platform LIMIT 1');
    $stmt->execute(['id' => $endUserId, 'platform' => normalize_platform($platform)]);
    return trim((string)($stmt->fetchColumn() ?: '')) ?: null;
}

function live_chat_send_client(array $admin, int $endUserId, string $text, ?string $requestedChannel = null, array $attachments = []): array
{
    if (!live_chat_admin_can_access_client($admin, $endUserId)) return ['ok' => false, 'error' => 'Клиент недоступен.'];
    $user = live_chat_client_row($endUserId);
    $text = mb_substr(trim($text), 0, 8000, 'UTF-8');
    if ($text === '' && $attachments) $text = 'Вложение';
    if (!$user || $text === '') return ['ok' => false, 'error' => 'Введите сообщение.'];
    $threadId = live_chat_backfill_client($endUserId);
    $channel = live_chat_client_channel($user, $requestedChannel);
    $messageId = live_chat_insert_message($threadId, 'admin', (int)$admin['id'], null, $channel, $text, $attachments, 'pending');
    $result = ['ok' => true, 'error' => null];
    $platformUserId = live_chat_platform_user_id($endUserId, $channel);
    if ($channel === 'telegram') {
        $result = $platformUserId ? send_telegram_response($platformUserId, $text, null, null, $attachments, null, lead_response_referral_code($user)) : ['ok' => false, 'error' => 'Telegram клиента не подключён.'];
    } elseif (in_array($channel, ['VK','OK'], true)) {
        $result = $platformUserId ? send_social_platform_message($channel, $platformUserId, $user + ['end_user_id' => $endUserId], $text, [], lead_response_media_urls(null, $attachments, false)) : ['ok' => false, 'error' => $channel . ' клиента не подключён.'];
    }
    $status = !empty($result['ok']) ? 'sent' : 'failed';
    db()->prepare('UPDATE chat_messages SET status = :status, error_text = :error, delivered_at = :delivered WHERE id = :id')->execute(['status' => $status, 'error' => $result['error'] ?? null, 'delivered' => $status === 'sent' ? date('Y-m-d H:i:s') : null, 'id' => $messageId]);
    if ($status === 'sent') {
        create_user_notification($endUserId, 'chat_message', 'Новое сообщение', mb_substr(trim($text), 0, 190), 'Открыть чат', mini_app_url(null, $channel, lead_response_referral_code($user), null, $endUserId, 'contact'));
    }
    return ['ok' => $status === 'sent', 'error' => $result['error'] ?? null, 'thread_id' => $threadId, 'message_id' => $messageId, 'channel' => $channel];
}

function live_chat_send_team(array $admin, string $text, array $attachments = []): array
{
    $threadId = live_chat_ensure_team_thread($admin);
    $text = mb_substr(trim($text), 0, 8000, 'UTF-8');
    if ($text === '' && $attachments) $text = 'Вложение';
    if (!$threadId || $text === '') return ['ok' => false, 'error' => 'Введите сообщение.'];
    $messageId = live_chat_insert_message($threadId, 'admin', (int)$admin['id'], null, 'internal', $text, $attachments, 'sent');
    return ['ok' => true, 'thread_id' => $threadId, 'message_id' => $messageId, 'channel' => 'internal'];
}

function live_chat_mark_read(int $threadId, int $adminId): void
{
    $stmt = db()->prepare('SELECT MAX(id) FROM chat_messages WHERE thread_id = :thread_id');
    $stmt->execute(['thread_id' => $threadId]);
    $lastId = (int)$stmt->fetchColumn();
    db()->prepare('INSERT INTO chat_reads (thread_id, admin_user_id, last_message_id, read_at) VALUES (:thread_id, :admin_id, :message_id, NOW()) ON DUPLICATE KEY UPDATE last_message_id = VALUES(last_message_id), read_at = NOW()')->execute(['thread_id' => $threadId, 'admin_id' => $adminId, 'message_id' => $lastId ?: null]);
}

function live_chat_message_rows(int $threadId, int $afterId = 0): array
{
    $direction = $afterId > 0 ? 'ASC' : 'DESC';
    $stmt = db()->prepare('SELECT cm.*, au.name AS admin_name, eu.first_name, eu.last_name FROM chat_messages cm LEFT JOIN admin_users au ON au.id = cm.sender_admin_user_id LEFT JOIN end_users eu ON eu.id = cm.sender_end_user_id WHERE cm.thread_id = :thread_id AND cm.id > :after_id ORDER BY cm.id ' . $direction . ' LIMIT 200');
    $stmt->execute(['thread_id' => $threadId, 'after_id' => max(0, $afterId)]);
    $rows = $stmt->fetchAll();
    if ($afterId <= 0) $rows = array_reverse($rows);
    foreach ($rows as &$row) {
        $row['attachments'] = $row['attachments_json'] ? (json_decode((string)$row['attachments_json'], true) ?: []) : [];
        unset($row['attachments_json'], $row['error_text'], $row['thread_id'], $row['sender_admin_user_id'], $row['sender_end_user_id'], $row['dedupe_key']);
        $row['sender_name'] = $row['sender_type'] === 'client' ? trim((string)$row['first_name'] . ' ' . (string)$row['last_name']) : (string)($row['admin_name'] ?: 'SWPro');
    }
    unset($row);
    return $rows;
}

function live_chat_client_threads(array $admin): array
{
    [$where, $params] = scope_where_for_users($admin);
    $where = $where !== '' ? $where . ' AND eu.merged_into_user_id IS NULL' : 'WHERE eu.merged_into_user_id IS NULL';
    $where = str_replace(['WHERE reseller_id','WHERE manager_id','AND reseller_id','AND manager_id'], ['WHERE eu.reseller_id','WHERE eu.manager_id','AND eu.reseller_id','AND eu.manager_id'], $where);
    $params['reader_id'] = (int)$admin['id'];
    $stmt = db()->prepare('SELECT eu.id end_user_id, CONCAT_WS(" ", NULLIF(eu.first_name,""), NULLIF(eu.last_name,"")) client_name, eu.platform, ct.id thread_id, ct.last_message_at, (SELECT message_text FROM chat_messages WHERE thread_id = ct.id ORDER BY id DESC LIMIT 1) last_message, (SELECT COUNT(*) FROM chat_messages cm WHERE cm.thread_id = ct.id AND cm.sender_type = "client" AND cm.id > COALESCE((SELECT cr.last_message_id FROM chat_reads cr WHERE cr.thread_id = ct.id AND cr.admin_user_id = :reader_id), 0)) unread_count FROM end_users eu LEFT JOIN chat_threads ct ON ct.end_user_id = eu.id AND ct.thread_type = "client" ' . $where . ' ORDER BY COALESCE(ct.last_message_at, eu.last_activity_at, eu.created_at) DESC LIMIT 150');
    $stmt->execute($params);
    return $stmt->fetchAll();
}
