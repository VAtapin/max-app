<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function client_stage_labels(): array
{
    return [
        'new' => 'Новый',
        'profile_completed' => 'Анкета заполнена',
        'test_started' => 'Чек-ап начат',
        'test_completed' => 'Чек-ап завершён',
        'consultation_requested' => 'Запросил консультацию',
        'in_progress' => 'В работе',
        'client' => 'Клиент',
        'partner' => 'Партнёр',
        'inactive' => 'Неактивен',
        'unsubscribed' => 'Отказ / отписка',
    ];
}

function client_gender_labels(): array
{
    return [
        'female' => 'Женщина',
        'male' => 'Мужчина',
        'prefer_not_to_say' => 'Не хочу указывать',
    ];
}

function active_legal_documents(): array
{
    $stmt = db()->query(
        'SELECT ld.*
         FROM legal_documents ld
         INNER JOIN (
             SELECT document_type, MAX(id) AS max_id
             FROM legal_documents
             WHERE is_active = 1
             GROUP BY document_type
         ) latest ON latest.max_id = ld.id
         ORDER BY FIELD(
             ld.document_type,
             "privacy_policy",
             "personal_data_consent",
             "health_data_consent",
             "marketing_consent",
             "user_agreement",
             "leader_offer"
         )'
    );

    $documents = [];
    foreach ($stmt->fetchAll() as $row) {
        $documents[(string)$row['document_type']] = $row;
    }
    return $documents;
}

function latest_user_consents(int $endUserId): array
{
    $stmt = db()->prepare(
        'SELECT uc.*
         FROM user_consents uc
         INNER JOIN (
             SELECT document_type, MAX(id) AS max_id
             FROM user_consents
             WHERE end_user_id = :end_user_id
             GROUP BY document_type
         ) latest ON latest.max_id = uc.id'
    );
    $stmt->execute(['end_user_id' => $endUserId]);

    $consents = [];
    foreach ($stmt->fetchAll() as $row) {
        $consents[(string)$row['document_type']] = $row;
    }
    return $consents;
}

function client_consent_granted(array $consents, string $documentType, ?string $requiredVersion = null): bool
{
    $consent = $consents[$documentType] ?? null;
    if (!$consent || !empty($consent['revoked_at'])) {
        return false;
    }
    return $requiredVersion === null || hash_equals($requiredVersion, (string)$consent['document_version']);
}

function client_onboarding_status(array $user): array
{
    $documents = active_legal_documents();
    $consents = latest_user_consents((int)$user['id']);
    $requiredTypes = ['personal_data_consent', 'health_data_consent', 'user_agreement'];
    $missing = [];

    foreach ($requiredTypes as $type) {
        $version = isset($documents[$type]) ? (string)$documents[$type]['version'] : null;
        if (!client_consent_granted($consents, $type, $version)) {
            $missing[] = $type;
        }
    }

    $profileComplete = trim((string)($user['first_name'] ?? '')) !== ''
        && !empty($user['gender'])
        && (!empty($user['birth_date']) || !empty($user['age_years']))
        && trim((string)($user['city'] ?? '')) !== '';

    return [
        'complete' => !$missing && $profileComplete && !empty($user['onboarding_completed_at']),
        'profile_complete' => $profileComplete,
        'missing_consents' => $missing,
        'marketing_consent' => client_consent_granted(
            $consents,
            'marketing_consent',
            isset($documents['marketing_consent']) ? (string)$documents['marketing_consent']['version'] : null
        ),
        'notifications_enabled' => (int)($user['notifications_enabled'] ?? 1) === 1,
        'documents' => array_map(
            static fn(array $document): array => [
                'type' => (string)$document['document_type'],
                'title' => (string)$document['title'],
                'version' => (string)$document['version'],
                'is_required' => (bool)$document['is_required'],
                'url' => '/legal.php?type=' . rawurlencode((string)$document['document_type']),
            ],
            $documents
        ),
    ];
}

function grant_user_consent(
    int $endUserId,
    string $documentType,
    string $platform,
    array $metadata = []
): void {
    $allowed = ['personal_data_consent', 'health_data_consent', 'marketing_consent', 'user_agreement'];
    if (!in_array($documentType, $allowed, true)) {
        throw new InvalidArgumentException('Unknown consent type');
    }

    $documents = active_legal_documents();
    $document = $documents[$documentType] ?? null;
    if (!$document) {
        throw new RuntimeException('Active legal document is missing');
    }

    $existing = latest_user_consents($endUserId)[$documentType] ?? null;
    if ($existing
        && empty($existing['revoked_at'])
        && hash_equals((string)$document['version'], (string)$existing['document_version'])) {
        return;
    }

    $stmt = db()->prepare(
        'INSERT INTO user_consents (
            end_user_id, document_type, document_version, platform, metadata_json
         ) VALUES (
            :end_user_id, :document_type, :document_version, :platform, :metadata_json
         )'
    );
    $stmt->execute([
        'end_user_id' => $endUserId,
        'document_type' => $documentType,
        'document_version' => $document['version'],
        'platform' => normalize_platform($platform),
        'metadata_json' => $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
    ]);

    if ($documentType === 'marketing_consent') {
        $update = db()->prepare('UPDATE end_users SET notifications_enabled = 1 WHERE id = :id');
        $update->execute(['id' => $endUserId]);
    }
}

function revoke_user_consents(int $endUserId, ?string $documentType = null): void
{
    $types = $documentType ? [$documentType] : [
        'personal_data_consent',
        'health_data_consent',
        'marketing_consent',
        'user_agreement',
    ];
    $placeholders = implode(',', array_fill(0, count($types), '?'));
    $params = array_merge([date('Y-m-d H:i:s'), $endUserId], $types);
    $stmt = db()->prepare(
        "UPDATE user_consents
         SET revoked_at = ?
         WHERE end_user_id = ?
           AND document_type IN ($placeholders)
           AND revoked_at IS NULL"
    );
    $stmt->execute($params);

    if ($documentType === 'marketing_consent') {
        return;
    }

    update_client_stage($endUserId, 'unsubscribed', 'client');
    $update = db()->prepare(
        'UPDATE end_users
         SET notifications_enabled = 0,
             status = "unsubscribed"
         WHERE id = :id'
    );
    $update->execute(['id' => $endUserId]);
}

function update_client_stage(
    int $endUserId,
    string $newStage,
    string $source = 'system',
    ?int $actorId = null,
    ?string $note = null
): void {
    $labels = client_stage_labels();
    if (!isset($labels[$newStage])) {
        throw new InvalidArgumentException('Unknown client stage');
    }

    $stmt = db()->prepare('SELECT client_stage FROM end_users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $endUserId]);
    $previous = $stmt->fetchColumn();
    if ($previous === false || $previous === $newStage) {
        return;
    }

    $automaticOrder = [
        'new' => 0,
        'profile_completed' => 10,
        'test_started' => 20,
        'test_completed' => 30,
        'consultation_requested' => 40,
    ];
    if ($source === 'system'
        && isset($automaticOrder[(string)$previous], $automaticOrder[$newStage])
        && $automaticOrder[$newStage] < $automaticOrder[(string)$previous]) {
        return;
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare(
            'UPDATE end_users
             SET client_stage = :new_stage, stage_updated_at = NOW()
             WHERE id = :id'
        );
        $update->execute(['new_stage' => $newStage, 'id' => $endUserId]);

        $history = $pdo->prepare(
            'INSERT INTO client_stage_history (
                end_user_id, previous_stage, new_stage, source, actor_id, note
             ) VALUES (
                :end_user_id, :previous_stage, :new_stage, :source, :actor_id, :note
             )'
        );
        $history->execute([
            'end_user_id' => $endUserId,
            'previous_stage' => $previous,
            'new_stage' => $newStage,
            'source' => $source,
            'actor_id' => $actorId,
            'note' => $note,
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function complete_client_onboarding(int $endUserId, array $profile): array
{
    $gender = (string)($profile['gender'] ?? '');
    if (!isset(client_gender_labels()[$gender])) {
        throw new InvalidArgumentException('gender is invalid');
    }

    $firstName = trim((string)($profile['first_name'] ?? ''));
    $lastName = trim((string)($profile['last_name'] ?? ''));
    $city = trim((string)($profile['city'] ?? ''));
    $birthDate = trim((string)($profile['birth_date'] ?? ''));
    $ageYears = isset($profile['age_years']) ? (int)$profile['age_years'] : 0;
    $timezone = trim((string)($profile['timezone'] ?? 'Europe/Moscow'));

    if ($firstName === '' || $city === '') {
        throw new InvalidArgumentException('first_name and city are required');
    }
    if ($ageYears > 0 && ($ageYears < 14 || $ageYears > 100)) {
        throw new InvalidArgumentException('age is invalid');
    }
    if ($birthDate !== '') {
        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $birthDate);
        if (!$parsed || $parsed->format('Y-m-d') !== $birthDate) {
            throw new InvalidArgumentException('birth_date is invalid');
        }
        $today = new DateTimeImmutable('today');
        $calculatedAge = $parsed->diff($today)->y;
        if ($parsed > $today || $calculatedAge < 14 || $calculatedAge > 100) {
            throw new InvalidArgumentException('birth_date is invalid');
        }
        $ageYears = 0;
    }
    if ($birthDate === '' && $ageYears === 0) {
        throw new InvalidArgumentException('birth_date or age_years is required');
    }
    if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
        $timezone = 'Europe/Moscow';
    }

    $stmt = db()->prepare(
        'UPDATE end_users
         SET first_name = :first_name,
             last_name = :last_name,
             gender = :gender,
             birth_date = :birth_date,
             age_years = :age_years,
             city = :city,
             timezone = :timezone,
             onboarding_completed_at = NOW(),
             notifications_enabled = 1,
             status = "active",
             last_activity_at = NOW()
         WHERE id = :id'
    );
    $stmt->execute([
        'first_name' => $firstName,
        'last_name' => $lastName !== '' ? $lastName : null,
        'gender' => $gender,
        'birth_date' => $birthDate !== '' ? $birthDate : null,
        'age_years' => $ageYears > 0 ? $ageYears : null,
        'city' => $city,
        'timezone' => $timezone,
        'id' => $endUserId,
    ]);
    update_client_stage($endUserId, 'profile_completed', 'client');

    $userStmt = db()->prepare('SELECT * FROM end_users WHERE id = :id LIMIT 1');
    $userStmt->execute(['id' => $endUserId]);
    return $userStmt->fetch() ?: [];
}

function create_user_notification(
    int $endUserId,
    string $type,
    string $title,
    string $message,
    ?string $actionText = null,
    ?string $actionUrl = null,
    ?string $imagePath = null,
    ?string $videoPath = null
): void {
    $stmt = db()->prepare(
        'INSERT INTO user_notifications (
            end_user_id, notification_type, title, message_text,
            image_path, video_path, action_text, action_url
         ) VALUES (
            :end_user_id, :notification_type, :title, :message_text,
            :image_path, :video_path, :action_text, :action_url
         )'
    );
    $stmt->execute([
        'end_user_id' => $endUserId,
        'notification_type' => $type,
        'title' => $title,
        'message_text' => $message,
        'image_path' => $imagePath,
        'video_path' => $videoPath,
        'action_text' => $actionText,
        'action_url' => $actionUrl,
    ]);
}

function send_client_journey_telegram_message(string $chatId, string $text): array
{
    $config = app_config();
    $token = (string)($config['integrations']['telegram_bot_token'] ?? '');
    if ($token === '' || $chatId === '') {
        return ['ok' => false, 'error' => 'Telegram is not configured'];
    }

    $payload = json_encode([
        'chat_id' => $chatId,
        'text' => $text,
        'disable_web_page_preview' => true,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $payload,
            'timeout' => 8,
            'ignore_errors' => true,
        ],
    ]);
    $raw = @file_get_contents('https://api.telegram.org/bot' . $token . '/sendMessage', false, $context);
    $result = $raw ? json_decode($raw, true) : null;
    return [
        'ok' => (bool)($result['ok'] ?? false),
        'error' => (string)($result['description'] ?? ($raw === false ? 'Telegram request failed' : '')),
    ];
}

function notify_consultant_about_test(array $user, int $sessionId, string $testTitle): void
{
    $managerId = (int)($user['manager_id'] ?? 0);
    if ($managerId <= 0) {
        return;
    }

    $name = trim((string)($user['first_name'] ?? '') . ' ' . (string)($user['last_name'] ?? ''));
    $name = $name !== '' ? $name : 'Клиент #' . (int)$user['id'];
    $eventKey = 'test_completed:' . $sessionId;
    $title = 'Клиент завершил чек-ап';
    $sessionStmt = db()->prepare('SELECT result_summary FROM user_test_sessions WHERE id = :id LIMIT 1');
    $sessionStmt->execute(['id' => $sessionId]);
    $summary = trim((string)($sessionStmt->fetchColumn() ?: ''));
    $scaleStmt = db()->prepare(
        'SELECT ts.title, uts.score, tsr.title AS result_title
         FROM user_test_scale_scores uts
         INNER JOIN test_scales ts ON ts.id = uts.scale_id
         LEFT JOIN test_scale_results tsr ON tsr.id = uts.result_id
         WHERE uts.session_id = :session_id
         ORDER BY ts.sort_order, ts.id'
    );
    $scaleStmt->execute(['session_id' => $sessionId]);
    $scaleLines = [];
    foreach ($scaleStmt->fetchAll() as $scale) {
        $scaleLines[] = '• ' . $scale['title'] . ': '
            . ($scale['result_title'] ?: 'результат не задан')
            . ' (' . (int)$scale['score'] . ')';
    }
    $parts = [$name . ' завершил «' . $testTitle . '».'];
    if ($summary !== '') {
        $parts[] = $summary;
    }
    if ($scaleLines) {
        $parts[] = "Карта по направлениям:\n" . implode("\n", $scaleLines);
    }
    $parts[] = 'Полный результат доступен в кабинете SWPro.';
    $message = mb_substr(implode("\n\n", $parts), 0, 3900);

    $insert = db()->prepare(
        'INSERT IGNORE INTO consultant_notifications (
            manager_id, end_user_id, notification_type, event_key, title, message_text
         ) VALUES (
            :manager_id, :end_user_id, "test_completed", :event_key, :title, :message_text
         )'
    );
    $insert->execute([
        'manager_id' => $managerId,
        'end_user_id' => $user['id'],
        'event_key' => $eventKey,
        'title' => $title,
        'message_text' => $message,
    ]);
    if ($insert->rowCount() === 0) {
        return;
    }

    $manager = db()->prepare('SELECT telegram_id FROM managers WHERE id = :id LIMIT 1');
    $manager->execute(['id' => $managerId]);
    $telegramId = trim((string)$manager->fetchColumn());
    $delivery = send_client_journey_telegram_message($telegramId, $message);

    $update = db()->prepare(
        'UPDATE consultant_notifications
         SET delivery_status = :status, delivery_error = :error
         WHERE manager_id = :manager_id AND event_key = :event_key'
    );
    $update->execute([
        'status' => $delivery['ok'] ? 'sent' : 'failed',
        'error' => $delivery['ok'] ? null : $delivery['error'],
        'manager_id' => $managerId,
        'event_key' => $eventKey,
    ]);
}

function notify_consultant_about_contact(array $user, int $leadId): void
{
    $managerId = (int)($user['manager_id'] ?? 0);
    if ($managerId <= 0) {
        return;
    }

    $name = trim((string)($user['first_name'] ?? '') . ' ' . (string)($user['last_name'] ?? ''));
    $name = $name !== '' ? $name : 'Клиент #' . (int)$user['id'];
    $eventKey = 'consultation_requested:' . $leadId;
    $leadStmt = db()->prepare('SELECT message FROM leads WHERE id = :id LIMIT 1');
    $leadStmt->execute(['id' => $leadId]);
    $leadMessage = trim((string)($leadStmt->fetchColumn() ?: ''));
    $message = $name . ' запросил связь с консультантом.';
    if ($leadMessage !== '') {
        $message .= "\n\nСообщение:\n" . $leadMessage;
    }
    $message .= "\n\nОбращение #" . $leadId . ' доступно в кабинете SWPro.';
    $message = mb_substr($message, 0, 3900);

    $insert = db()->prepare(
        'INSERT IGNORE INTO consultant_notifications (
            manager_id, end_user_id, notification_type, event_key, title, message_text
         ) VALUES (
            :manager_id, :end_user_id, "consultation_requested", :event_key,
            "Запрос на консультацию", :message_text
         )'
    );
    $insert->execute([
        'manager_id' => $managerId,
        'end_user_id' => $user['id'],
        'event_key' => $eventKey,
        'message_text' => $message,
    ]);
    if ($insert->rowCount() === 0) {
        return;
    }

    $manager = db()->prepare('SELECT telegram_id FROM managers WHERE id = :id LIMIT 1');
    $manager->execute(['id' => $managerId]);
    $telegramId = trim((string)$manager->fetchColumn());
    $delivery = send_client_journey_telegram_message($telegramId, $message);

    $update = db()->prepare(
        'UPDATE consultant_notifications
         SET delivery_status = :status, delivery_error = :error
         WHERE manager_id = :manager_id AND event_key = :event_key'
    );
    $update->execute([
        'status' => $delivery['ok'] ? 'sent' : 'failed',
        'error' => $delivery['ok'] ? null : $delivery['error'],
        'manager_id' => $managerId,
        'event_key' => $eventKey,
    ]);
}
