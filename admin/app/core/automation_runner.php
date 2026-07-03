<?php

require_once __DIR__ . '/client_journey.php';
require_once __DIR__ . '/lead_responses.php';

function automation_is_daytime(?string $timezone): bool
{
    try {
        $zone = new DateTimeZone($timezone ?: 'Europe/Moscow');
    } catch (Throwable) {
        $zone = new DateTimeZone('Europe/Moscow');
    }
    $hour = (int)(new DateTimeImmutable('now', $zone))->format('G');
    return $hour >= 10 && $hour < 20;
}

function automation_recently_contacted(int $endUserId): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*)
         FROM automation_logs
         WHERE end_user_id = :end_user_id
           AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
           AND status IN ("sent", "queued")'
    );
    $stmt->execute(['end_user_id' => $endUserId]);
    return (int)$stmt->fetchColumn() > 0;
}

function automation_log_exists(int $endUserId, string $type, string $contextKey, string $platform): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*)
         FROM automation_logs
         WHERE end_user_id = :end_user_id
           AND automation_type = :automation_type
           AND context_key = :context_key
           AND platform = :platform
           AND status IN ("sent", "queued")'
    );
    $stmt->execute([
        'end_user_id' => $endUserId,
        'automation_type' => $type,
        'context_key' => $contextKey,
        'platform' => normalize_platform($platform),
    ]);
    return (int)$stmt->fetchColumn() > 0;
}

function save_automation_log(
    int $endUserId,
    string $type,
    string $contextKey,
    string $platform,
    string $status,
    ?string $error = null
): void {
    $stmt = db()->prepare(
        'INSERT INTO automation_logs (
            end_user_id, automation_type, context_key, platform, status, error_message, sent_at
         ) VALUES (
            :end_user_id, :automation_type, :context_key, :platform, :status, :error_message, :sent_at
         )
         ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            error_message = VALUES(error_message),
            sent_at = VALUES(sent_at)'
    );
    $stmt->execute([
        'end_user_id' => $endUserId,
        'automation_type' => $type,
        'context_key' => $contextKey,
        'platform' => normalize_platform($platform),
        'status' => $status,
        'error_message' => $error,
        'sent_at' => in_array($status, ['sent', 'queued'], true) ? date('Y-m-d H:i:s') : null,
    ]);
}

function automation_delivery_account(array $user): array
{
    $stmt = db()->prepare(
        'SELECT platform, platform_user_id
         FROM platform_accounts
         WHERE end_user_id = :end_user_id
         ORDER BY FIELD(platform, "telegram", "VK", "OK", "MAX", "web"), id
         LIMIT 1'
    );
    $stmt->execute(['end_user_id' => $user['id']]);
    $account = $stmt->fetch();
    return $account ?: [
        'platform' => $user['platform'],
        'platform_user_id' => $user['platform_user_id'],
    ];
}

function automation_action_url(array $user, string $page): string
{
    $config = app_config();
    $url = trim((string)($config['integrations']['mini_app_url'] ?? ''));
    if ($url === '') {
        $url = rtrim((string)($config['app']['public_url'] ?? 'https://swpro.ru'), '/') . '/vk-mini-app/';
    }
    $params = ['page' => $page];
    if (!empty($user['referral_code_used'])) {
        $params['ref'] = $user['referral_code_used'];
    }
    return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
}

function deliver_automation_message(
    array $user,
    string $type,
    string $contextKey,
    string $title,
    string $message,
    string $actionText,
    string $page
): ?bool {
    $account = automation_delivery_account($user);
    $platform = normalize_platform((string)$account['platform']);
    if (automation_log_exists((int)$user['id'], $type, $contextKey, $platform)) {
        return null;
    }

    $actionUrl = automation_action_url($user, $page);
    if ($platform === 'telegram') {
        $result = send_telegram_text(
            (string)$account['platform_user_id'],
            $message,
            [[['text' => $actionText, 'web_app' => ['url' => $actionUrl]]]]
        );
        save_automation_log(
            (int)$user['id'],
            $type,
            $contextKey,
            $platform,
            $result['ok'] ? 'sent' : 'failed',
            $result['ok'] ? null : (string)$result['error']
        );
        return (bool)$result['ok'];
    }

    create_user_notification((int)$user['id'], $type, $title, $message, $actionText, $actionUrl);
    save_automation_log((int)$user['id'], $type, $contextKey, $platform, 'queued');
    return true;
}

function test_reminder_users(): array
{
    return db()->query(
        'SELECT eu.*, uts.id AS session_id, uts.started_at, uts.last_answered_at, t.title AS test_title,
                TIMESTAMPDIFF(HOUR, COALESCE(uts.last_answered_at, uts.started_at), NOW()) AS inactive_hours
         FROM user_test_sessions uts
         INNER JOIN end_users eu ON eu.id = uts.end_user_id
         INNER JOIN tests t ON t.id = uts.test_id
         WHERE uts.completed_at IS NULL
           AND eu.status = "active"
           AND eu.notifications_enabled = 1
           AND eu.onboarding_completed_at IS NOT NULL
           AND TIMESTAMPDIFF(HOUR, COALESCE(uts.last_answered_at, uts.started_at), NOW()) >= 24
           AND EXISTS (
               SELECT 1 FROM user_consents uc
               WHERE uc.id = (
                   SELECT MAX(uc2.id) FROM user_consents uc2
                   WHERE uc2.end_user_id = eu.id AND uc2.document_type = "health_data_consent"
               )
                 AND uc.revoked_at IS NULL
                 AND uc.document_version = (
                     SELECT ld.version
                     FROM legal_documents ld
                     WHERE ld.document_type = "health_data_consent" AND ld.is_active = 1
                     ORDER BY ld.id DESC
                     LIMIT 1
                 )
           )
         ORDER BY inactive_hours DESC
         LIMIT 200'
    )->fetchAll();
}

function process_test_reminders(): array
{
    $sent = 0;
    $failed = 0;
    foreach (test_reminder_users() as $user) {
        if (!automation_is_daytime($user['timezone'] ?? null) || automation_recently_contacted((int)$user['id'])) {
            continue;
        }
        $hours = (int)$user['inactive_hours'];
        $step = $hours >= 168 ? '7d' : ($hours >= 72 ? '3d' : '24h');
        $message = match ($step) {
            '7d' => 'Вы начали чек-ап неделю назад. Ответы сохранены. Можно продолжить с того же вопроса или связаться с консультантом.',
            '3d' => 'Ваш незавершённый чек-ап сохранён. Продолжите, когда будет удобно: результат поможет подготовиться к разговору с консультантом.',
            default => 'Вы начали чек-ап, но ещё не завершили его. Ответы сохранены, продолжить можно с того же вопроса.',
        };
        $ok = deliver_automation_message(
            $user,
            'test_reminder',
            'session:' . (int)$user['session_id'] . ':' . $step,
            'Продолжите чек-ап',
            $message,
            'Продолжить',
            'tests'
        );
        if ($ok === true) {
            $sent++;
        } elseif ($ok === false) {
            $failed++;
        }
    }
    return ['sent' => $sent, 'failed' => $failed];
}

function inactivity_users(): array
{
    return db()->query(
        'SELECT eu.*, TIMESTAMPDIFF(DAY, eu.last_activity_at, NOW()) AS inactive_days
         FROM end_users eu
         WHERE eu.status = "active"
           AND eu.notifications_enabled = 1
           AND eu.onboarding_completed_at IS NOT NULL
           AND eu.last_activity_at IS NOT NULL
           AND eu.last_activity_at < DATE_SUB(NOW(), INTERVAL 14 DAY)
           AND EXISTS (
               SELECT 1 FROM user_consents uc
               WHERE uc.id = (
                   SELECT MAX(uc2.id) FROM user_consents uc2
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
           )
         ORDER BY eu.last_activity_at
         LIMIT 200'
    )->fetchAll();
}

function process_inactivity_messages(): array
{
    $sent = 0;
    $failed = 0;
    foreach (inactivity_users() as $user) {
        if (!automation_is_daytime($user['timezone'] ?? null) || automation_recently_contacted((int)$user['id'])) {
            continue;
        }
        $days = (int)$user['inactive_days'];
        $step = $days >= 30 ? '30d' : '14d';
        $message = $step === '30d'
            ? 'Если у вас остались вопросы по самочувствию, кэшбэку или программам, консультант готов помочь.'
            : 'Давно не виделись. Вы можете задать консультанту вопрос или вернуться к чек-апу в любое удобное время.';
        $ok = deliver_automation_message(
            $user,
            'inactive_user',
            'inactive:' . $step,
            'Консультант на связи',
            $message,
            'Связаться с консультантом',
            'contact'
        );
        if ($ok && !in_array($user['client_stage'], ['client', 'partner', 'unsubscribed'], true)) {
            update_client_stage((int)$user['id'], 'inactive');
        }
        if ($ok === true) {
            $sent++;
        } elseif ($ok === false) {
            $failed++;
        }
    }
    return ['sent' => $sent, 'failed' => $failed];
}

function run_client_automations(): array
{
    return [
        'test_reminders' => process_test_reminders(),
        'inactivity' => process_inactivity_messages(),
    ];
}
