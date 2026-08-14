<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function legal_document_types(): array
{
    return [
        'privacy_policy',
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
        'leader_privacy_policy' => 'Архив: политика лидера (не используется)',
        'personal_data_consent' => 'Согласие на обработку данных',
        'health_data_consent' => 'Согласие на ответы чек-апа',
        'marketing_consent' => 'Согласие на рассылку',
        'user_agreement' => 'Пользовательское соглашение',
        'leader_offer' => 'Оферта для лидеров',
    ];
}

function legal_active_documents(): array
{
    $types = legal_document_types();
    $fieldOrder = implode(', ', array_map(static fn(string $type): string => db()->quote($type), $types));
    $typeFilter = $fieldOrder;
    $stmt = db()->query(
        'SELECT ld.*
         FROM legal_documents ld
         INNER JOIN (
             SELECT document_type, MAX(id) AS max_id
             FROM legal_documents
             WHERE is_active = 1
               AND document_type IN (' . $typeFilter . ')
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

function legal_party_placeholder(string $value, string $placeholder): string
{
    return $value !== '' ? $value : $placeholder;
}

function legal_optional_party_value(string $value): string
{
    return trim($value);
}

function legal_remove_empty_requisite_lines(string $body, array $emptyTokens): string
{
    if (!$emptyTokens) {
        return $body;
    }

    $lines = preg_split('/\R/u', $body) ?: [$body];
    $lines = array_values(array_filter($lines, static function (string $line) use ($emptyTokens): bool {
        foreach ($emptyTokens as $token) {
            if (preg_match('/^\s*[^:\[\]]{1,50}:\s*' . preg_quote($token, '/') . '\s*$/u', $line)) {
                return false;
            }
        }
        return true;
    }));

    return preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)) ?? implode("\n", $lines);
}

function legal_document_replacements(): array
{
    $swpro = legal_swpro_party();
    $settings = legal_settings();

    return [
        '[SWPRO_NAME]' => legal_optional_party_value($swpro['name']),
        '[SWPRO_STATUS]' => legal_optional_party_value($swpro['status']),
        '[SWPRO_INN]' => legal_optional_party_value($swpro['inn']),
        '[SWPRO_ADDRESS]' => legal_optional_party_value($swpro['address']),
        '[SWPRO_EMAIL]' => legal_optional_party_value($swpro['email']),
        '[SWPRO_PHONE]' => legal_optional_party_value($swpro['phone']),
        '[SWPRO_SITE]' => legal_optional_party_value($swpro['site']),
        '[OPERATOR_NAME]' => legal_optional_party_value($swpro['name']),
        '[OPERATOR_STATUS]' => legal_optional_party_value($swpro['status']),
        '[OPERATOR_INN]' => legal_optional_party_value($swpro['inn']),
        '[OPERATOR_ADDRESS]' => legal_optional_party_value($swpro['address']),
        '[OPERATOR_EMAIL]' => legal_optional_party_value($swpro['email']),
        '[OPERATOR_PHONE]' => legal_optional_party_value($swpro['phone']),
        '[OPERATOR_SITE]' => legal_optional_party_value($swpro['site']),
        '[ОПЕРАТОР]' => legal_party_placeholder($swpro['name'], '[ОПЕРАТОР]'),
        '[УКАЖИТЕ НАИМЕНОВАНИЕ ИЛИ ФИО ОПЕРАТОРА]' => legal_party_placeholder($swpro['name'], '[ОПЕРАТОР]'),
        '[ИНН]' => legal_party_placeholder($swpro['inn'], '[ИНН]'),
        '[АДРЕС]' => legal_party_placeholder($swpro['address'], '[АДРЕС]'),
        '[EMAIL]' => legal_party_placeholder($swpro['email'], '[EMAIL]'),
        '[ИСПОЛНИТЕЛЬ]' => legal_party_placeholder($swpro['name'], '[ИСПОЛНИТЕЛЬ]'),
        '[СТОИМОСТЬ]' => legal_party_placeholder(trim((string)($settings['leader_monthly_price'] ?? '')), '[СТОИМОСТЬ]'),
    ];
}

function legal_render_document(array $document): array
{
    $replacements = legal_document_replacements();
    $emptyTokens = array_keys(array_filter(
        $replacements,
        static fn(string $value, string $token): bool => $value === '' && preg_match('/^\[(?:SWPRO|OPERATOR)_[A-Z_]+\]$/', $token) === 1,
        ARRAY_FILTER_USE_BOTH
    ));
    $body = legal_remove_empty_requisite_lines((string)$document['body'], $emptyTokens);
    $body = strtr($body, $replacements);
    return [
        'title' => (string)$document['title'],
        'body' => $body,
        'hash' => hash('sha256', $body),
    ];
}

function legal_document_url(string $type): string
{
    return '/legal.php?' . http_build_query(['type' => $type]);
}

function legal_date_ru(?string $value, bool $withTime = false): string
{
    return app_date_ru($value, $withTime, '');
}
