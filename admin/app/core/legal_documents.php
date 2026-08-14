<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function legal_document_types(): array
{
    return [
        'privacy_policy',
        'leader_privacy_policy',
        'personal_data_consent',
        'health_data_consent',
        'marketing_consent',
        'user_agreement',
        'leader_offer',
    ];
}

function legal_document_type_labels(): array
{
    return [
        'privacy_policy' => 'Политика SWPro',
        'leader_privacy_policy' => 'Политика лидера',
        'personal_data_consent' => 'Согласие на обработку данных',
        'health_data_consent' => 'Согласие на ответы чек-апа',
        'marketing_consent' => 'Согласие на рассылку',
        'user_agreement' => 'Пользовательское соглашение',
        'leader_offer' => 'Оферта для лидеров',
    ];
}

function legal_document_is_leader_scoped(string $type): bool
{
    return in_array($type, [
        'leader_privacy_policy',
        'personal_data_consent',
        'health_data_consent',
        'marketing_consent',
    ], true);
}

function legal_active_documents(): array
{
    $types = legal_document_types();
    $fieldOrder = implode(', ', array_map(static fn(string $type): string => db()->quote($type), $types));
    $stmt = db()->query(
        'SELECT ld.*
         FROM legal_documents ld
         INNER JOIN (
             SELECT document_type, MAX(id) AS max_id
             FROM legal_documents
             WHERE is_active = 1
             GROUP BY document_type
         ) latest ON latest.max_id = ld.id
         ORDER BY FIELD(ld.document_type, ' . $fieldOrder . ')'
    );

    $documents = [];
    foreach ($stmt->fetchAll() as $row) {
        $documents[(string)$row['document_type']] = $row;
    }
    return $documents;
}

function legal_settings(): array
{
    static $settings = null;
    if (is_array($settings)) {
        return $settings;
    }

    $settings = [];
    foreach (db()->query('SELECT setting_key, setting_value FROM settings')->fetchAll() as $row) {
        $settings[(string)$row['setting_key']] = (string)($row['setting_value'] ?? '');
    }
    return $settings;
}

function legal_swpro_party(): array
{
    $settings = legal_settings();
    $config = app_config();
    return [
        'name' => trim((string)($settings['legal_operator_name'] ?? '')),
        'status' => trim((string)($settings['legal_operator_status'] ?? '')),
        'inn' => trim((string)($settings['legal_operator_inn'] ?? '')),
        'address' => trim((string)($settings['legal_operator_address'] ?? '')),
        'email' => trim((string)($settings['legal_operator_email'] ?? '')),
        'phone' => trim((string)($settings['legal_operator_phone'] ?? '')),
        'site' => (string)($config['app']['public_url'] ?? 'https://swpro.ru'),
    ];
}

function legal_reseller_id_from_referral(?string $referralCode): ?int
{
    $referralCode = trim((string)$referralCode);
    if ($referralCode === '') {
        return null;
    }
    if (str_starts_with(strtolower($referralCode), 'ref_')) {
        $referralCode = substr($referralCode, 4);
    }

    $stmt = db()->prepare('SELECT id FROM resellers WHERE referral_code = :code AND is_active = 1 LIMIT 1');
    $stmt->execute(['code' => $referralCode]);
    $resellerId = (int)$stmt->fetchColumn();
    if ($resellerId > 0) {
        return $resellerId;
    }

    $stmt = db()->prepare('SELECT reseller_id FROM managers WHERE referral_code = :code AND is_active = 1 LIMIT 1');
    $stmt->execute(['code' => $referralCode]);
    $resellerId = (int)$stmt->fetchColumn();
    return $resellerId > 0 ? $resellerId : null;
}

function legal_reseller_id_for_user(array $user): ?int
{
    $resellerId = (int)($user['reseller_id'] ?? 0);
    if ($resellerId > 0) {
        return $resellerId;
    }

    $managerId = (int)($user['manager_id'] ?? 0);
    if ($managerId > 0) {
        $stmt = db()->prepare('SELECT reseller_id FROM managers WHERE id = :id AND is_active = 1 LIMIT 1');
        $stmt->execute(['id' => $managerId]);
        $resellerId = (int)$stmt->fetchColumn();
        if ($resellerId > 0) {
            return $resellerId;
        }
    }

    return legal_reseller_id_from_referral((string)($user['referral_code_used'] ?? ''));
}

function legal_referral_code_for_user(array $user): ?string
{
    $managerId = (int)($user['manager_id'] ?? 0);
    if ($managerId > 0) {
        $stmt = db()->prepare('SELECT referral_code FROM managers WHERE id = :id AND is_active = 1 LIMIT 1');
        $stmt->execute(['id' => $managerId]);
        $code = trim((string)$stmt->fetchColumn());
        if ($code !== '') {
            return $code;
        }
    }

    $resellerId = (int)($user['reseller_id'] ?? 0);
    if ($resellerId > 0) {
        $stmt = db()->prepare('SELECT referral_code FROM resellers WHERE id = :id AND is_active = 1 LIMIT 1');
        $stmt->execute(['id' => $resellerId]);
        $code = trim((string)$stmt->fetchColumn());
        if ($code !== '') {
            return $code;
        }
    }

    $code = trim((string)($user['referral_code_used'] ?? ''));
    return $code !== '' ? $code : null;
}

function legal_leader_party(?int $resellerId): ?array
{
    if (!$resellerId) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM resellers WHERE id = :id AND is_active = 1 LIMIT 1');
    $stmt->execute(['id' => $resellerId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $config = app_config();
    $publicUrl = rtrim((string)($config['app']['public_url'] ?? 'https://swpro.ru'), '/');
    return [
        'id' => (int)$row['id'],
        'name' => trim((string)($row['legal_name'] ?: $row['billing_name'] ?: $row['name'])),
        'status' => trim((string)($row['legal_status'] ?? '')),
        'inn' => trim((string)($row['legal_inn'] ?: $row['billing_inn'])),
        'address' => trim((string)($row['legal_address'] ?? '')),
        'email' => trim((string)($row['legal_email'] ?: $row['billing_email'] ?: $row['email'])),
        'phone' => trim((string)($row['legal_phone'] ?: $row['phone'])),
        'site' => $publicUrl . '/?ref=' . rawurlencode((string)$row['referral_code']),
    ];
}

function legal_party_placeholder(string $value, string $placeholder): string
{
    return $value !== '' ? $value : $placeholder;
}

function legal_document_replacements(?int $resellerId = null): array
{
    $swpro = legal_swpro_party();
    $leader = legal_leader_party($resellerId) ?: $swpro;
    $settings = legal_settings();

    return [
        '[SWPRO_NAME]' => legal_party_placeholder($swpro['name'], '[SWPRO_NAME]'),
        '[SWPRO_STATUS]' => legal_party_placeholder($swpro['status'], '[SWPRO_STATUS]'),
        '[SWPRO_INN]' => legal_party_placeholder($swpro['inn'], '[SWPRO_INN]'),
        '[SWPRO_ADDRESS]' => legal_party_placeholder($swpro['address'], '[SWPRO_ADDRESS]'),
        '[SWPRO_EMAIL]' => legal_party_placeholder($swpro['email'], '[SWPRO_EMAIL]'),
        '[SWPRO_PHONE]' => legal_party_placeholder($swpro['phone'], '[SWPRO_PHONE]'),
        '[SWPRO_SITE]' => legal_party_placeholder($swpro['site'], '[SWPRO_SITE]'),
        '[OPERATOR_NAME]' => legal_party_placeholder($leader['name'], '[OPERATOR_NAME]'),
        '[OPERATOR_STATUS]' => legal_party_placeholder($leader['status'], '[OPERATOR_STATUS]'),
        '[OPERATOR_INN]' => legal_party_placeholder($leader['inn'], '[OPERATOR_INN]'),
        '[OPERATOR_ADDRESS]' => legal_party_placeholder($leader['address'], '[OPERATOR_ADDRESS]'),
        '[OPERATOR_EMAIL]' => legal_party_placeholder($leader['email'], '[OPERATOR_EMAIL]'),
        '[OPERATOR_PHONE]' => legal_party_placeholder($leader['phone'], '[OPERATOR_PHONE]'),
        '[OPERATOR_SITE]' => legal_party_placeholder($leader['site'], '[OPERATOR_SITE]'),
        '[ОПЕРАТОР]' => legal_party_placeholder($swpro['name'], '[ОПЕРАТОР]'),
        '[УКАЖИТЕ НАИМЕНОВАНИЕ ИЛИ ФИО ОПЕРАТОРА]' => legal_party_placeholder($swpro['name'], '[ОПЕРАТОР]'),
        '[ИНН]' => legal_party_placeholder($swpro['inn'], '[ИНН]'),
        '[АДРЕС]' => legal_party_placeholder($swpro['address'], '[АДРЕС]'),
        '[EMAIL]' => legal_party_placeholder($swpro['email'], '[EMAIL]'),
        '[ИСПОЛНИТЕЛЬ]' => legal_party_placeholder($swpro['name'], '[ИСПОЛНИТЕЛЬ]'),
        '[СТОИМОСТЬ]' => legal_party_placeholder(trim((string)($settings['leader_monthly_price'] ?? '')), '[СТОИМОСТЬ]'),
    ];
}

function legal_render_document(array $document, ?int $resellerId = null): array
{
    $type = (string)$document['document_type'];
    $effectiveResellerId = legal_document_is_leader_scoped($type) ? $resellerId : null;
    $body = strtr((string)$document['body'], legal_document_replacements($effectiveResellerId));
    return [
        'title' => (string)$document['title'],
        'body' => $body,
        'hash' => hash('sha256', $body),
        'operator_reseller_id' => $effectiveResellerId,
    ];
}

function legal_document_url(string $type, ?string $referralCode = null): string
{
    $params = ['type' => $type];
    if (legal_document_is_leader_scoped($type) && trim((string)$referralCode) !== '') {
        $params['ref'] = trim((string)$referralCode);
    }
    return '/legal.php?' . http_build_query($params);
}

function legal_date_ru(?string $value, bool $withTime = false): string
{
    return app_date_ru($value, $withTime, '');
}
