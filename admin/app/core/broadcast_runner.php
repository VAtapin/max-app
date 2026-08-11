<?php

require_once __DIR__ . '/lead_responses.php';
require_once __DIR__ . '/team_tree.php';

function broadcast_recipients(array $broadcast): array
{
    $broadcast = broadcast_apply_owner_scope($broadcast);

    if (($broadcast['audience_type'] ?? 'clients') === 'consultants') {
        return broadcast_consultant_recipients($broadcast);
    }

    $where = ['eu.merged_into_user_id IS NULL', 'eu.status = "active"'];
    $where[] = 'eu.onboarding_completed_at IS NOT NULL';
    $where[] = '(eu.platform <> "web" OR EXISTS (SELECT 1 FROM platform_accounts pav WHERE pav.end_user_id = eu.id AND pav.platform <> "web"))';
    $where[] = 'NOT EXISTS (SELECT 1 FROM resellers rs WHERE rs.source_end_user_id = eu.id)';
    $where[] = 'NOT EXISTS (SELECT 1 FROM managers ms WHERE ms.source_end_user_id = eu.id)';
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

    $targetType = (string)($broadcast['target_type'] ?? 'all');
    $targetResellerId = broadcast_target_reseller_id($broadcast);
    if (in_array($targetType, ['reseller', 'own_clients'], true) && $targetResellerId) {
        $where[] = 'eu.reseller_id = :reseller_id';
        $params['reseller_id'] = $targetResellerId;
    } elseif (in_array($targetType, ['branch_clients', 'whole_branch'], true) && $targetResellerId) {
        broadcast_add_user_branch_filter($where, $params, $targetResellerId);
    }

    if ($targetType === 'manager' && !empty($broadcast['target_manager_id'])) {
        $where[] = 'eu.manager_id = :manager_id';
        $params['manager_id'] = (int)$broadcast['target_manager_id'];
    }

    broadcast_add_segment_filters($where, $params, $broadcast);

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

function broadcast_apply_owner_scope(array $broadcast): array
{
    $ownerType = (string)($broadcast['owner_type'] ?? '');
    $ownerId = (int)($broadcast['owner_id'] ?? 0);

    if ($ownerType === 'manager' && $ownerId > 0) {
        $broadcast['audience_type'] = 'clients';
        $broadcast['target_type'] = 'manager';
        $broadcast['target_manager_id'] = $ownerId;
        $broadcast['target_reseller_id'] = team_manager_reseller_id($ownerId);
        return $broadcast;
    }

    if ($ownerType === 'reseller' && $ownerId > 0) {
        $allowedTargets = [
            'own_clients',
            'branch_clients',
            'direct_consultants',
            'branch_consultants',
            'direct_leaders',
            'branch_leaders',
        ];
        $targetType = (string)($broadcast['target_type'] ?? '');
        $broadcast['target_type'] = in_array($targetType, $allowedTargets, true)
            ? $targetType
            : 'own_clients';
        $broadcast['audience_type'] = in_array($broadcast['target_type'], ['own_clients', 'branch_clients'], true)
            ? 'clients'
            : 'consultants';
        $broadcast['target_reseller_id'] = $ownerId;
        $broadcast['target_manager_id'] = null;
    }

    return $broadcast;
}

function broadcast_target_reseller_id(array $broadcast): ?int
{
    if (!empty($broadcast['target_reseller_id'])) {
        return (int)$broadcast['target_reseller_id'];
    }

    if (($broadcast['owner_type'] ?? null) === 'reseller' && !empty($broadcast['owner_id'])) {
        return (int)$broadcast['owner_id'];
    }

    if (($broadcast['owner_type'] ?? null) === 'manager' && !empty($broadcast['owner_id'])) {
        return team_manager_reseller_id((int)$broadcast['owner_id']);
    }

    return null;
}

function broadcast_add_in_filter(array &$where, array &$params, string $column, array $ids, string $prefix): bool
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
    if (!$ids) {
        $where[] = '1 = 0';
        return false;
    }

    $placeholders = [];
    foreach ($ids as $index => $id) {
        $key = $prefix . '_' . $index;
        $placeholders[] = ':' . $key;
        $params[$key] = $id;
    }

    $where[] = $column . ' IN (' . implode(',', $placeholders) . ')';
    return true;
}

function broadcast_add_user_branch_filter(array &$where, array &$params, int $resellerId): void
{
    $branchIds = team_reseller_branch_ids($resellerId, true);
    $managerIds = team_manager_ids_for_resellers($branchIds);
    $branchConditions = [];

    if ($branchIds) {
        $localWhere = [];
        broadcast_add_in_filter($localWhere, $params, 'eu.reseller_id', $branchIds, 'branch_reseller');
        $branchConditions[] = $localWhere[0];
    }

    if ($managerIds) {
        $localWhere = [];
        broadcast_add_in_filter($localWhere, $params, 'eu.manager_id', $managerIds, 'branch_manager');
        $branchConditions[] = $localWhere[0];
    }

    $where[] = $branchConditions ? '(' . implode(' OR ', $branchConditions) . ')' : '1 = 0';
}

function broadcast_add_segment_filters(array &$where, array &$params, array $broadcast): void
{
    $stage = trim((string)($broadcast['segment_stage'] ?? ''));
    if ($stage !== '') {
        $where[] = 'eu.client_stage = :segment_stage';
        $params['segment_stage'] = $stage;
    }

    $activity = trim((string)($broadcast['segment_activity'] ?? ''));
    if ($activity === 'active_7') {
        $where[] = 'eu.last_activity_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
    } elseif ($activity === 'active_30') {
        $where[] = 'eu.last_activity_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
    } elseif ($activity === 'inactive_14') {
        $where[] = '(eu.last_activity_at IS NULL OR eu.last_activity_at < DATE_SUB(NOW(), INTERVAL 14 DAY))';
    } elseif ($activity === 'inactive_30') {
        $where[] = '(eu.last_activity_at IS NULL OR eu.last_activity_at < DATE_SUB(NOW(), INTERVAL 30 DAY))';
    }

    $checkup = trim((string)($broadcast['segment_checkup'] ?? ''));
    if ($checkup === 'not_started') {
        $where[] = 'NOT EXISTS (SELECT 1 FROM user_test_sessions uts WHERE uts.end_user_id = eu.id AND uts.is_preview = 0)';
    } elseif ($checkup === 'started') {
        $where[] = 'EXISTS (
            SELECT 1
            FROM user_test_sessions uts
            WHERE uts.end_user_id = eu.id
              AND uts.is_preview = 0
              AND uts.completed_at IS NULL
        )';
    } elseif ($checkup === 'completed') {
        $where[] = 'EXISTS (
            SELECT 1
            FROM user_test_sessions uts
            WHERE uts.end_user_id = eu.id
              AND uts.is_preview = 0
              AND uts.completed_at IS NOT NULL
        )';
    }
}

function broadcast_consultant_recipients(array $broadcast): array
{
    $targetType = (string)($broadcast['target_type'] ?? 'all');
    $targetResellerId = broadcast_target_reseller_id($broadcast);
    $managerWhere = ['m.is_active = 1', 'm.telegram_id IS NOT NULL', 'm.telegram_id <> ""'];
    $managerParams = [];

    if ($targetType === 'manager' && !empty($broadcast['target_manager_id'])) {
        $managerWhere[] = 'm.id = :manager_id';
        $managerParams['manager_id'] = (int)$broadcast['target_manager_id'];
    } elseif (in_array($targetType, ['reseller', 'own_clients', 'direct_consultants'], true) && $targetResellerId) {
        $managerWhere[] = 'm.reseller_id = :reseller_id';
        $managerParams['reseller_id'] = $targetResellerId;
    } elseif (in_array($targetType, ['branch_clients', 'branch_consultants', 'whole_branch'], true) && $targetResellerId) {
        broadcast_add_in_filter($managerWhere, $managerParams, 'm.reseller_id', team_reseller_branch_ids($targetResellerId, true), 'manager_branch');
    } elseif (in_array($targetType, ['direct_leaders', 'branch_leaders'], true)) {
        $managerWhere[] = '1 = 0';
    }

    $recipients = [];
    $stmt = db()->prepare(
        'SELECT m.id AS manager_id, "telegram" AS platform, m.telegram_id AS platform_user_id
         FROM managers m
         WHERE ' . implode(' AND ', $managerWhere) . '
         ORDER BY m.id'
    );
    $stmt->execute($managerParams);
    foreach ($stmt->fetchAll() as $row) {
        $recipients[] = [
            'end_user_id' => null,
            'manager_id' => (int)$row['manager_id'],
            'platform' => 'telegram',
            'platform_user_id' => (string)$row['platform_user_id'],
        ];
    }

    if (!in_array($targetType, ['direct_leaders', 'branch_leaders', 'whole_branch'], true) || !$targetResellerId) {
        return broadcast_unique_recipients($recipients);
    }

    $childrenMap = team_children_map(true);
    $leaderIds = $targetType === 'direct_leaders'
        ? ($childrenMap[$targetResellerId] ?? [])
        : team_reseller_branch_ids($targetResellerId, false, true);
    if (!$leaderIds) {
        return broadcast_unique_recipients($recipients);
    }

    $leaderWhere = ['au.role = "reseller"', 'au.is_active = 1', 'au.telegram_id IS NOT NULL', 'au.telegram_id <> ""'];
    $leaderParams = [];
    broadcast_add_in_filter($leaderWhere, $leaderParams, 'au.reseller_id', $leaderIds, 'leader_branch');
    $leaderStmt = db()->prepare(
        'SELECT au.id AS admin_user_id, au.reseller_id, au.telegram_id AS platform_user_id
         FROM admin_users au
         WHERE ' . implode(' AND ', $leaderWhere) . '
         ORDER BY au.id'
    );
    $leaderStmt->execute($leaderParams);
    foreach ($leaderStmt->fetchAll() as $row) {
        $recipients[] = [
            'end_user_id' => null,
            'manager_id' => null,
            'admin_user_id' => (int)$row['admin_user_id'],
            'reseller_id' => (int)$row['reseller_id'],
            'platform' => 'telegram',
            'platform_user_id' => (string)$row['platform_user_id'],
        ];
    }

    return broadcast_unique_recipients($recipients);
}

function broadcast_unique_recipients(array $recipients): array
{
    $unique = [];
    foreach ($recipients as $recipient) {
        $key = normalize_platform((string)$recipient['platform']) . '|' . (string)$recipient['platform_user_id'];
        $unique[$key] = $recipient;
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
