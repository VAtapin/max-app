<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/legal_documents.php';

function client_stage_labels(): array
{
    return [
        'new' => 'Присоединился',
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
    return legal_active_documents();
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

function client_consent_granted(
    array $consents,
    string $documentType,
    ?string $requiredVersion = null,
    ?int $requiredOperatorResellerId = null
): bool
{
    $consent = $consents[$documentType] ?? null;
    if (!$consent || !empty($consent['revoked_at'])) {
        return false;
    }
    if ($requiredVersion !== null && !hash_equals($requiredVersion, (string)$consent['document_version'])) {
        return false;
    }
    if (legal_document_is_leader_scoped($documentType)
        && $requiredOperatorResellerId !== null
        && (int)($consent['operator_reseller_id'] ?? 0) !== $requiredOperatorResellerId) {
        return false;
    }
    return true;
}

function client_onboarding_status(array $user): array
{
    $documents = active_legal_documents();
    $consents = latest_user_consents((int)$user['id']);
    $operatorResellerId = legal_reseller_id_for_user($user);
    $legalReferralCode = legal_referral_code_for_user($user);
    $requiredTypes = ['personal_data_consent', 'health_data_consent', 'user_agreement'];
    $missing = [];

    foreach ($requiredTypes as $type) {
        $version = isset($documents[$type]) ? (string)$documents[$type]['version'] : null;
        if (!client_consent_granted($consents, $type, $version, $operatorResellerId)) {
            $missing[] = $type;
        }
    }

    $profileComplete = trim((string)($user['first_name'] ?? '')) !== ''
        && trim((string)($user['last_name'] ?? '')) !== ''
        && !empty($user['gender'])
        && (!empty($user['birth_date']) || !empty($user['age_years']))
        && trim((string)($user['city'] ?? '')) !== '';

    $hasConfirmedPlatform = false;
    if (!empty($user['id'])) {
        $platformStmt = db()->prepare(
            'SELECT COUNT(*)
             FROM platform_accounts
             WHERE end_user_id = :end_user_id AND platform <> "web"'
        );
        $platformStmt->execute(['end_user_id' => (int)$user['id']]);
        $hasConfirmedPlatform = (int)$platformStmt->fetchColumn() > 0
            || !in_array((string)($user['platform'] ?? 'web'), ['web', ''], true);
    }
    $currentPlatform = (string)($user['current_platform'] ?? $user['platform'] ?? 'web');
    $isWebOnly = $currentPlatform === 'web' && !$hasConfirmedPlatform;
    $agreementGrantedAt = (string)($consents['user_agreement']['granted_at'] ?? '');
    $deadlineBase = $agreementGrantedAt !== ''
        ? $agreementGrantedAt
        : (string)($user['created_at'] ?? '');
    $deadlineDays = $agreementGrantedAt !== '' ? 5 : 3;
    $webDeadlineAt = null;
    if ($isWebOnly && $deadlineBase !== '') {
        try {
            $webDeadlineAt = (new DateTimeImmutable($deadlineBase))->modify('+' . $deadlineDays . ' days')->format('Y-m-d H:i:s');
        } catch (Throwable) {
            $webDeadlineAt = null;
        }
    }

    return [
        'complete' => !$missing && $profileComplete && !empty($user['onboarding_completed_at']),
        'profile_complete' => $profileComplete,
        'missing_consents' => $missing,
        'marketing_consent' => client_consent_granted(
            $consents,
            'marketing_consent',
            isset($documents['marketing_consent']) ? (string)$documents['marketing_consent']['version'] : null,
            $operatorResellerId
        ),
        'notifications_enabled' => (int)($user['notifications_enabled'] ?? 1) === 1,
        'has_confirmed_platform' => $hasConfirmedPlatform,
        'web_merge_required' => $isWebOnly && !empty($user['onboarding_completed_at']),
        'web_cleanup_deadline_at' => $webDeadlineAt,
        'documents' => array_map(
            static fn(array $document): array => [
                'type' => (string)$document['document_type'],
                'title' => (string)$document['title'],
                'version' => (string)$document['version'],
                'is_required' => (bool)$document['is_required'],
                'url' => legal_document_url((string)$document['document_type'], $legalReferralCode),
            ],
            $documents
        ),
    ];
}

function register_completed_referral(int $endUserId): void
{
    $userStmt = db()->prepare(
        'SELECT referral_code_used, platform
         FROM end_users
         WHERE id = :id AND referral_registered_at IS NULL
         LIMIT 1'
    );
    $userStmt->execute(['id' => $endUserId]);
    $user = $userStmt->fetch();
    if (!$user) {
        return;
    }

    $mark = db()->prepare(
        'UPDATE end_users
         SET referral_registered_at = NOW()
         WHERE id = :id AND referral_registered_at IS NULL'
    );
    $mark->execute(['id' => $endUserId]);
    if ($mark->rowCount() !== 1 || empty($user['referral_code_used'])) {
        return;
    }

    $registration = db()->prepare(
        'UPDATE referral_links
         SET registrations_count = registrations_count + 1
         WHERE referral_code = :referral_code AND platform = :platform'
    );
    $registration->execute([
        'referral_code' => (string)$user['referral_code_used'],
        'platform' => (string)$user['platform'],
    ]);
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

    $userStmt = db()->prepare('SELECT * FROM end_users WHERE id = :id LIMIT 1');
    $userStmt->execute(['id' => $endUserId]);
    $user = $userStmt->fetch();
    if (!$user) {
        throw new RuntimeException('User is missing');
    }
    $operatorResellerId = legal_reseller_id_for_user($user);
    $rendered = legal_render_document($document, $operatorResellerId);
    $existing = latest_user_consents($endUserId)[$documentType] ?? null;
    if ($existing
        && empty($existing['revoked_at'])
        && hash_equals((string)$document['version'], (string)$existing['document_version'])
        && hash_equals((string)$rendered['hash'], (string)($existing['document_hash'] ?? ''))
        && (int)($rendered['operator_reseller_id'] ?? 0) === (int)($existing['operator_reseller_id'] ?? 0)) {
        return;
    }

    $stmt = db()->prepare(
        'INSERT INTO user_consents (
            end_user_id, document_type, document_version, operator_reseller_id,
            document_snapshot, document_hash, platform, metadata_json
         ) VALUES (
            :end_user_id, :document_type, :document_version, :operator_reseller_id,
            :document_snapshot, :document_hash, :platform, :metadata_json
         )'
    );
    $stmt->execute([
        'end_user_id' => $endUserId,
        'document_type' => $documentType,
        'document_version' => $document['version'],
        'operator_reseller_id' => $rendered['operator_reseller_id'],
        'document_snapshot' => $rendered['body'],
        'document_hash' => $rendered['hash'],
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
    $phone = trim((string)($profile['phone'] ?? ''));
    $email = trim((string)($profile['email'] ?? ''));
    $city = trim((string)($profile['city'] ?? ''));
    $birthDate = trim((string)($profile['birth_date'] ?? ''));
    $ageYears = isset($profile['age_years']) ? (int)$profile['age_years'] : 0;
    $timezone = trim((string)($profile['timezone'] ?? 'Europe/Moscow'));

    if ($firstName === '' || $lastName === '' || $city === '') {
        throw new InvalidArgumentException('first_name, last_name and city are required');
    }
    if (mb_strlen($phone) > 50) {
        throw new InvalidArgumentException('phone is too long');
    }
    if ($email !== '' && (mb_strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
        throw new InvalidArgumentException('email is invalid');
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
             phone = :phone,
             email = :email,
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
        'last_name' => $lastName,
        'phone' => $phone !== '' ? $phone : null,
        'email' => $email !== '' ? $email : null,
        'gender' => $gender,
        'birth_date' => $birthDate !== '' ? $birthDate : null,
        'age_years' => $ageYears > 0 ? $ageYears : null,
        'city' => $city,
        'timezone' => $timezone,
        'id' => $endUserId,
    ]);
    register_completed_referral($endUserId);
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

function lead_request_type_label(?string $requestType): string
{
    return [
        'consultation' => 'Связь с консультантом',
        'product' => 'Вопрос о продукте',
        'test_result' => 'Разбор результатов чек-апа',
        'cashback' => 'Кэшбэк и регистрация',
        'cooperation' => 'Сотрудничество',
        'other' => 'Другое обращение',
    ][$requestType ?: 'consultation'] ?? 'Связь с консультантом';
}

function consultant_telegram_recipient(array $user): ?array
{
    $managerId = (int)($user['manager_id'] ?? 0);
    if ($managerId > 0) {
        $stmt = db()->prepare(
            'SELECT COALESCE(
                NULLIF(m.telegram_id, ""),
                (
                    SELECT NULLIF(au.telegram_id, "")
                    FROM admin_users au
                    WHERE au.manager_id = m.id
                      AND au.role = "manager"
                      AND au.is_active = 1
                    ORDER BY au.id
                    LIMIT 1
                )
             ) AS telegram_id
             FROM managers m
             WHERE m.id = :id AND m.is_active = 1
             LIMIT 1'
        );
        $stmt->execute(['id' => $managerId]);
        return [
            'manager_id' => $managerId,
            'reseller_id' => null,
            'telegram_id' => trim((string)($stmt->fetchColumn() ?: '')),
        ];
    }

    $resellerId = (int)($user['reseller_id'] ?? 0);
    if ($resellerId <= 0) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT telegram_id
         FROM admin_users
         WHERE reseller_id = :id
           AND role = "reseller"
           AND is_active = 1
           AND telegram_id IS NOT NULL
           AND telegram_id <> ""
         ORDER BY id
         LIMIT 1'
    );
    $stmt->execute(['id' => $resellerId]);
    return [
        'manager_id' => null,
        'reseller_id' => $resellerId,
        'telegram_id' => trim((string)($stmt->fetchColumn() ?: '')),
    ];
}

function create_consultant_notification_record(
    array $user,
    string $notificationType,
    string $eventKey,
    string $title,
    string $message,
    ?int $leadId,
    ?string $sourcePlatform
): ?array {
    $recipient = consultant_telegram_recipient($user);
    if (!$recipient) {
        return null;
    }

    $insert = db()->prepare(
        'INSERT IGNORE INTO consultant_notifications (
            manager_id, reseller_id, end_user_id, lead_id, notification_type,
            source_platform, event_key, title, message_text
         ) VALUES (
            :manager_id, :reseller_id, :end_user_id, :lead_id, :notification_type,
            :source_platform, :event_key, :title, :message_text
         )'
    );
    $insert->execute([
        'manager_id' => $recipient['manager_id'],
        'reseller_id' => $recipient['reseller_id'],
        'end_user_id' => $user['id'],
        'lead_id' => $leadId,
        'notification_type' => $notificationType,
        'source_platform' => $sourcePlatform,
        'event_key' => $eventKey,
        'title' => $title,
        'message_text' => $message,
    ]);
    if ($insert->rowCount() === 0) {
        return null;
    }

    return $recipient + ['notification_id' => (int)db()->lastInsertId()];
}

function consultant_notification_action_url(?int $leadId = null): ?string
{
    $config = app_config();
    $baseUrl = rtrim((string)($config['app']['public_url'] ?? getenv('SWPRO_PUBLIC_URL') ?: ''), '/');
    if ($baseUrl === '') {
        return null;
    }

    return $leadId
        ? $baseUrl . '/admin/public/crud.php?module=leads&action=edit&id=' . $leadId
        : $baseUrl . '/admin/public/results.php';
}

function send_client_journey_telegram_message(
    string $chatId,
    string $text,
    ?string $actionUrl = null
): array
{
    $config = app_config();
    $token = (string)($config['integrations']['telegram_bot_token'] ?? '');
    if ($token === '' || $chatId === '') {
        return ['ok' => false, 'error' => 'Telegram is not configured'];
    }

    $payloadData = [
        'chat_id' => $chatId,
        'text' => $text,
        'disable_web_page_preview' => true,
    ];
    if ($actionUrl) {
        $payloadData['reply_markup'] = [
            'inline_keyboard' => [[
                ['text' => 'Открыть в админке', 'url' => $actionUrl],
            ]],
        ];
    }
    $payload = json_encode($payloadData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
        'chat_id' => isset($result['result']['chat']['id']) ? (string)$result['result']['chat']['id'] : $chatId,
        'message_id' => isset($result['result']['message_id']) ? (int)$result['result']['message_id'] : null,
    ];
}

function mark_consultant_notification_delivery_result(int $notificationId, array $delivery): void
{
    $stmt = db()->prepare(
        'UPDATE consultant_notifications
         SET delivery_status = :status,
             delivery_error = :error,
             telegram_chat_id = :telegram_chat_id,
             telegram_message_id = :telegram_message_id
         WHERE id = :id'
    );
    $stmt->execute([
        'status' => $delivery['ok'] ? 'sent' : 'failed',
        'error' => $delivery['ok'] ? null : $delivery['error'],
        'telegram_chat_id' => $delivery['chat_id'] ?? null,
        'telegram_message_id' => $delivery['message_id'] ?? null,
        'id' => $notificationId,
    ]);
}

function notify_consultant_about_test(array $user, int $sessionId, string $testTitle): void
{
    if (empty($user['manager_id']) && empty($user['reseller_id'])) {
        return;
    }

    $name = trim((string)($user['first_name'] ?? '') . ' ' . (string)($user['last_name'] ?? ''));
    $name = $name !== '' ? $name : 'Клиент #' . (int)$user['id'];
    $eventKey = 'test_completed:' . $sessionId;
    $title = 'Клиент завершил чек-ап';
    $sourcePlatform = normalize_platform((string)($user['current_platform'] ?? $user['platform'] ?? 'web'));
    $sessionStmt = db()->prepare('SELECT result_summary FROM user_test_sessions WHERE id = :id AND is_preview = 0 LIMIT 1');
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
    $parts = [
        "Завершён чек-ап\n",
        'Источник: ' . platform_label($sourcePlatform),
        'Клиент: ' . $name,
        'Тест: ' . $testTitle,
    ];
    if ($summary !== '') {
        $parts[] = $summary;
    }
    if ($scaleLines) {
        $parts[] = "Карта по направлениям:\n" . implode("\n", $scaleLines);
    }
    $parts[] = "Полный результат доступен в кабинете SWPro.\n\nОтветьте на это сообщение, чтобы написать клиенту.";
    $message = mb_substr(implode("\n\n", $parts), 0, 3900);

    $notification = create_consultant_notification_record(
        $user,
        'test_completed',
        $eventKey,
        $title,
        $message,
        null,
        $sourcePlatform
    );
    if (!$notification) {
        return;
    }

    $delivery = $notification['telegram_id'] !== ''
        ? send_client_journey_telegram_message(
            $notification['telegram_id'],
            $message,
            consultant_notification_action_url()
        )
        : ['ok' => false, 'error' => 'Telegram ID не указан', 'chat_id' => null, 'message_id' => null];
    mark_consultant_notification_delivery_result((int)$notification['notification_id'], $delivery);
}

function notify_consultant_about_contact(array $user, int $leadId): void
{
    if (empty($user['manager_id']) && empty($user['reseller_id'])) {
        return;
    }

    $name = trim((string)($user['first_name'] ?? '') . ' ' . (string)($user['last_name'] ?? ''));
    $name = $name !== '' ? $name : 'Клиент #' . (int)$user['id'];
    $eventKey = 'consultation_requested:' . $leadId;
    $leadStmt = db()->prepare(
        'SELECT l.message, l.request_type, l.source_platform, p.title AS product_title
         FROM leads l
         LEFT JOIN products p ON p.id = l.product_id
         WHERE l.id = :id
         LIMIT 1'
    );
    $leadStmt->execute(['id' => $leadId]);
    $lead = $leadStmt->fetch() ?: [];
    $leadMessage = trim((string)($lead['message'] ?? ''));
    $sourcePlatform = normalize_platform((string)($lead['source_platform'] ?? $user['current_platform'] ?? $user['platform'] ?? 'web'));
    $requestType = (string)($lead['request_type'] ?? 'consultation');
    $parts = [
        'Новое обращение #' . $leadId,
        'Источник: ' . platform_label($sourcePlatform),
        'Тип: ' . lead_request_type_label($requestType),
        'Клиент: ' . $name,
    ];
    if (!empty($lead['product_title'])) {
        $parts[] = 'Продукт: ' . $lead['product_title'];
    }
    if ($leadMessage !== '') {
        $parts[] = "Сообщение:\n" . $leadMessage;
    }
    $parts[] = 'Ответьте на это сообщение, чтобы отправить ответ клиенту.';
    $message = mb_substr(implode("\n\n", $parts), 0, 3900);

    $notification = create_consultant_notification_record(
        $user,
        'consultation_requested',
        $eventKey,
        'Новое обращение',
        $message,
        $leadId,
        $sourcePlatform
    );
    if (!$notification) {
        return;
    }

    $delivery = $notification['telegram_id'] !== ''
        ? send_client_journey_telegram_message(
            $notification['telegram_id'],
            $message,
            consultant_notification_action_url($leadId)
        )
        : ['ok' => false, 'error' => 'Telegram ID не указан', 'chat_id' => null, 'message_id' => null];
    mark_consultant_notification_delivery_result((int)$notification['notification_id'], $delivery);
}
