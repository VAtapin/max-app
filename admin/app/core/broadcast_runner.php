<?php

require_once __DIR__ . '/lead_responses.php';

function broadcast_recipients(array $broadcast): array
{
    if (($broadcast['audience_type'] ?? 'clients') === 'consultants') {
        $where = ['m.is_active = 1', 'm.telegram_id IS NOT NULL', 'm.telegram_id <> ""'];
        $params = [];
        if (!empty($broadcast['target_reseller_id'])) {
            $where[] = 'm.reseller_id = :reseller_id';
            $params['reseller_id'] = (int)$broadcast['target_reseller_id'];
        }
        if (!empty($broadcast['target_manager_id'])) {
            $where[] = 'm.id = :manager_id';
            $params['manager_id'] = (int)$broadcast['target_manager_id'];
        }
        $stmt = db()->prepare(
            'SELECT m.id AS manager_id, "telegram" AS platform, m.telegram_id AS platform_user_id
             FROM managers m
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY m.id'
        );
        $stmt->execute($params);
        return array_map(static fn(array $row): array => [
            'end_user_id' => null,
            'manager_id' => (int)$row['manager_id'],
            'platform' => 'telegram',
            'platform_user_id' => (string)$row['platform_user_id'],
        ], $stmt->fetchAll());
    }

    $where = ['eu.merged_into_user_id IS NULL', 'eu.status = "active"'];
    $where[] = 'eu.notifications_enabled = 1';
    $where[] = 'EXISTS (
        SELECT 1
        FROM user_consents uc
        WHERE uc.id = (
            SELECT MAX(uc2.id)
            FROM user_consents uc2
            WHERE uc2.end_user_id = eu.id AND uc2.document_type = "marketing_consent"
        )
          AND uc.revoked_at IS NULL
          AND uc.document_version = (
              SELECT ld.version
              FROM legal_documents ld
              WHERE ld.document_type = "marketing_consent" AND ld.is_active = 1
              ORDER BY ld.id DESC
              LIMIT 1
          )
    )';
    $params = [];

    if (($broadcast['target_type'] ?? '') === 'reseller' && !empty($broadcast['target_reseller_id'])) {
        $where[] = 'eu.reseller_id = :reseller_id';
        $params['reseller_id'] = (int)$broadcast['target_reseller_id'];
    }

    if (($broadcast['target_type'] ?? '') === 'manager' && !empty($broadcast['target_manager_id'])) {
        $where[] = 'eu.manager_id = :manager_id';
        $params['manager_id'] = (int)$broadcast['target_manager_id'];
    }

    $platformFilter = normalize_platform((string)($broadcast['platform'] ?? 'all'));
    if ($platformFilter !== 'all') {
        $where[] = '(pa.platform = :account_platform OR (pa.id IS NULL AND eu.platform = :legacy_platform))';
        $params['account_platform'] = $platformFilter;
        $params['legacy_platform'] = $platformFilter;
    }

    $sql = 'SELECT eu.id AS end_user_id,
                   eu.manager_id,
                   eu.reseller_id,
                   COALESCE(pa.platform, eu.platform) AS platform,
                   COALESCE(pa.platform_user_id, eu.platform_user_id) AS platform_user_id
            FROM end_users eu
            LEFT JOIN platform_accounts pa ON pa.end_user_id = eu.id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY eu.id, pa.id';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    $unique = [];
    foreach ($stmt->fetchAll() as $row) {
        $key = (int)$row['end_user_id'] . '|' . normalize_platform((string)$row['platform']) . '|' . (string)$row['platform_user_id'];
        $unique[$key] = [
            'end_user_id' => (int)$row['end_user_id'],
            'manager_id' => null,
            'recipient_manager_id' => !empty($row['manager_id']) ? (int)$row['manager_id'] : null,
            'reseller_id' => !empty($row['reseller_id']) ? (int)$row['reseller_id'] : null,
            'platform' => normalize_platform((string)$row['platform']),
            'platform_user_id' => (string)$row['platform_user_id'],
        ];
    }

    return array_values($unique);
}

function broadcast_message_text(array $broadcast): string
{
    $parts = [trim((string)$broadcast['message_text'])];
    if (!empty($broadcast['button_url'])) {
        $parts[] = trim((string)$broadcast['button_text']) . ': ' . trim((string)$broadcast['button_url']);
    }

    return trim(implode("\n\n", array_filter($parts)));
}

function send_broadcast_to_recipient(array $broadcast, array $recipient): array
{
    $platform = normalize_platform((string)$recipient['platform']);

    if ($platform === 'telegram') {
        $buttons = [];
        if (!empty($broadcast['button_url'])) {
            $buttons[] = [[
                'text' => trim((string)($broadcast['button_text'] ?? 'Открыть')) ?: 'Открыть',
                'url' => (string)$broadcast['button_url'],
            ]];
        }
        $errors = [];
        $messageText = trim((string)$broadcast['message_text']);
        if ($messageText !== '' || $buttons) {
            $textResult = send_telegram_text(
                (string)$recipient['platform_user_id'],
                $messageText !== '' ? $messageText : (string)$broadcast['title'],
                $buttons
            );
            if (!$textResult['ok']) {
                $errors[] = $textResult['error'];
            }
        }
        foreach (['image_path', 'video_path'] as $field) {
            $mediaResult = send_telegram_media(
                (string)$recipient['platform_user_id'],
                $broadcast[$field] ?? null,
                (string)$broadcast['title']
            );
            if (!$mediaResult['ok']) {
                $errors[] = $mediaResult['error'];
            }
        }
        return ['ok' => !$errors, 'error' => $errors ? implode('; ', $errors) : null];
    }

    if (in_array($platform, ['VK', 'OK'], true)) {
        $buttons = [];
        if (!empty($broadcast['button_url'])) {
            $buttons[] = [
                'text' => trim((string)($broadcast['button_text'] ?? 'Открыть')) ?: 'Открыть',
                'url' => (string)$broadcast['button_url'],
            ];
        }

        $mediaUrls = [];
        foreach (['image_path', 'video_path'] as $field) {
            $url = absolute_public_url($broadcast[$field] ?? null);
            if ($url) {
                $mediaUrls[] = $url;
            }
        }

        $messageText = trim((string)$broadcast['message_text']);
        $result = send_social_platform_message(
            $platform,
            (string)$recipient['platform_user_id'],
            [
                'manager_id' => $recipient['recipient_manager_id'] ?? null,
                'reseller_id' => $recipient['reseller_id'] ?? null,
            ],
            $messageText !== '' ? $messageText : (string)$broadcast['title'],
            $buttons,
            $mediaUrls
        );
        if (!$result['ok']) {
            return $result;
        }
    }

    if (!empty($recipient['end_user_id'])) {
        $stmt = db()->prepare(
            'INSERT INTO user_notifications (
                end_user_id, notification_type, title, message_text,
                image_path, video_path, action_text, action_url
             ) VALUES (
                :end_user_id, "broadcast", :title, :message_text,
                :image_path, :video_path, :action_text, :action_url
             )'
        );
        $stmt->execute([
            'end_user_id' => $recipient['end_user_id'],
            'title' => $broadcast['title'],
            'message_text' => trim((string)$broadcast['message_text']) ?: (string)$broadcast['title'],
            'image_path' => $broadcast['image_path'] ?: null,
            'video_path' => $broadcast['video_path'] ?: null,
            'action_text' => $broadcast['button_text'] ?: null,
            'action_url' => $broadcast['button_url'] ?: null,
        ]);
    }

    return ['ok' => true, 'error' => null];
}

function next_broadcast_time(array $broadcast): ?string
{
    $scheduledAt = trim((string)($broadcast['scheduled_at'] ?? ''));
    $base = $scheduledAt !== '' ? strtotime($scheduledAt) : time();
    if (!$base) {
        $base = time();
    }

    return match ((string)($broadcast['schedule_type'] ?? 'once')) {
        'daily' => date('Y-m-d H:i:s', strtotime('+1 day', $base)),
        'weekly' => date('Y-m-d H:i:s', strtotime('+1 week', $base)),
        'monthly' => date('Y-m-d H:i:s', strtotime('+1 month', $base)),
        default => null,
    };
}

function run_broadcast(int $broadcastId): array
{
    $stmt = db()->prepare('SELECT * FROM broadcasts WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $broadcastId]);
    $broadcast = $stmt->fetch();
    if (!$broadcast) {
        throw new RuntimeException('Broadcast not found');
    }

    $recipients = broadcast_recipients($broadcast);
    $insertLog = db()->prepare(
        'INSERT INTO broadcast_logs (broadcast_id, end_user_id, manager_id, platform, status, error_message, sent_at)
         VALUES (:broadcast_id, :end_user_id, :manager_id, :platform, :status, :error_message, :sent_at)'
    );

    $sent = 0;
    $failed = 0;
    foreach ($recipients as $recipient) {
        $result = send_broadcast_to_recipient($broadcast, $recipient);
        $ok = (bool)($result['ok'] ?? false);
        $insertLog->execute([
            'broadcast_id' => $broadcastId,
            'end_user_id' => $recipient['end_user_id'],
            'manager_id' => $recipient['manager_id'],
            'platform' => $recipient['platform'],
            'status' => $ok ? 'sent' : 'failed',
            'error_message' => $ok ? null : (string)($result['error'] ?? 'Delivery failed'),
            'sent_at' => $ok ? date('Y-m-d H:i:s') : null,
        ]);
        $ok ? $sent++ : $failed++;
    }

    $nextTime = next_broadcast_time($broadcast);
    if ($nextTime) {
        $update = db()->prepare('UPDATE broadcasts SET status = "scheduled", scheduled_at = :scheduled_at WHERE id = :id');
        $update->execute(['id' => $broadcastId, 'scheduled_at' => $nextTime]);
    } else {
        $update = db()->prepare('UPDATE broadcasts SET status = "sent" WHERE id = :id');
        $update->execute(['id' => $broadcastId]);
    }

    return [
        'recipients' => count($recipients),
        'sent' => $sent,
        'failed' => $failed,
    ];
}

function run_due_broadcasts(): array
{
    $stmt = db()->query(
        'SELECT id
         FROM broadcasts
         WHERE status = "scheduled"
           AND scheduled_at IS NOT NULL
           AND scheduled_at <= NOW()
         ORDER BY scheduled_at, id'
    );

    $results = [];
    foreach ($stmt->fetchAll() as $row) {
        $results[(int)$row['id']] = run_broadcast((int)$row['id']);
    }

    return $results;
}
