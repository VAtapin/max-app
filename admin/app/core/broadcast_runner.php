<?php

require_once __DIR__ . '/lead_responses.php';

function broadcast_recipients(array $broadcast): array
{
    $where = ['eu.merged_into_user_id IS NULL', 'eu.status = "active"'];
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
        $where[] = '(pa.platform = :platform OR (pa.id IS NULL AND eu.platform = :platform))';
        $params['platform'] = $platformFilter;
    }

    $sql = 'SELECT eu.id AS end_user_id,
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
        return send_telegram_text((string)$recipient['platform_user_id'], broadcast_message_text($broadcast));
    }

    return [
        'ok' => true,
        'error' => null,
    ];
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
        'INSERT INTO broadcast_logs (broadcast_id, end_user_id, platform, status, error_message, sent_at)
         VALUES (:broadcast_id, :end_user_id, :platform, :status, :error_message, :sent_at)'
    );

    $sent = 0;
    $failed = 0;
    foreach ($recipients as $recipient) {
        $result = send_broadcast_to_recipient($broadcast, $recipient);
        $ok = (bool)($result['ok'] ?? false);
        $insertLog->execute([
            'broadcast_id' => $broadcastId,
            'end_user_id' => $recipient['end_user_id'],
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
