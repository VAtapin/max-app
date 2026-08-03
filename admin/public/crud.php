<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/permissions.php';
require_once __DIR__ . '/../app/core/crud_views.php';
require_once __DIR__ . '/../app/core/lead_responses.php';
require_once __DIR__ . '/../app/core/test_admin.php';
require_once __DIR__ . '/../app/core/broadcast_runner.php';
require_once __DIR__ . '/../app/core/client_journey.php';
require_once __DIR__ . '/../app/core/content_ownership.php';

$admin = require_auth();

$modules = [
    'resellers' => [
        'title' => app_text('auto.k_32cea47742bf'),
        'table' => 'resellers',
        'columns' => ['id', 'name', 'email', 'phone', 'billing_name', 'billing_inn', 'billing_email', 'billing_comment', 'referral_code', 'manager_limit', 'is_active'],
        'fields' => [
            'name' => ['label' => app_text('auto.k_3de49828e86a'), 'required' => true],
            'email' => ['label' => 'Email', 'type' => 'email'],
            'phone' => ['label' => app_text('auto.k_87ec4b495b56')],
            'billing_name' => ['label' => 'Плательщик / юр. лицо'],
            'billing_inn' => ['label' => 'ИНН плательщика'],
            'billing_email' => ['label' => 'Email для счетов', 'type' => 'email'],
            'billing_comment' => ['label' => 'Комментарий для оплаты', 'type' => 'textarea'],
            'referral_code' => ['label' => app_text('auto.k_a9d3a61b02f2'), 'required' => true],
            'manager_limit' => ['label' => 'Лимит консультантов', 'type' => 'number', 'min' => 0, 'nullable' => true],
            'is_active' => ['label' => app_text('auto.k_667904ef22a4'), 'type' => 'checkbox', 'default' => 1],
        ],
    ],
    'managers' => [
        'title' => app_text('auto.k_6756aa53b5b5'),
        'table' => 'managers',
        'columns' => ['id', 'reseller_id', 'name', 'email', 'phone', 'referral_code', 'is_active'],
        'fields' => [
            'reseller_id' => ['label' => app_text('auto.k_86469fea3a4a'), 'type' => 'select', 'source' => 'resellers', 'nullable' => true],
            'name' => ['label' => app_text('auto.k_aee78fe86022'), 'required' => true],
            'email' => ['label' => 'Email', 'type' => 'email'],
            'phone' => ['label' => app_text('auto.k_87ec4b495b56')],
            'telegram_id' => ['label' => 'Telegram ID'],
            'max_id' => ['label' => 'MAX ID'],
            'vk_id' => ['label' => 'VK ID'],
            'referral_code' => ['label' => app_text('auto.k_a9d3a61b02f2'), 'required' => true],
            'is_active' => ['label' => app_text('auto.k_667904ef22a4'), 'type' => 'checkbox', 'default' => 1],
        ],
    ],
    'users' => [
        'title' => 'Клиенты',
        'table' => 'end_users',
        'columns' => ['id', 'platform', 'platform_user_id', 'username', 'reseller_id', 'manager_id', 'status'],
        'fields' => [
            'reseller_id' => ['label' => app_text('auto.k_86469fea3a4a'), 'type' => 'select', 'source' => 'resellers', 'nullable' => true],
            'manager_id' => ['label' => app_text('auto.k_8d98911527e4'), 'type' => 'select', 'source' => 'managers', 'nullable' => true],
            'platform' => ['label' => app_text('auto.k_89009febe5c6'), 'type' => 'select', 'options' => ['telegram', 'VK', 'OK', 'MAX', 'web'], 'required' => true],
            'platform_user_id' => ['label' => app_text('auto.k_c7f40b63aad7'), 'required' => true],
            'username' => ['label' => 'Username'],
            'first_name' => ['label' => app_text('auto.k_aee78fe86022')],
            'last_name' => ['label' => app_text('auto.k_5aa7f892d573')],
            'gender' => ['label' => 'Пол', 'type' => 'select', 'options' => ['female', 'male', 'prefer_not_to_say'], 'nullable' => true],
            'birth_date' => ['label' => 'Дата рождения', 'type' => 'date', 'nullable' => true],
            'age_years' => ['label' => 'Возраст', 'type' => 'number', 'nullable' => true],
            'city' => ['label' => 'Город'],
            'timezone' => ['label' => 'Часовой пояс'],
            'phone' => ['label' => app_text('auto.k_87ec4b495b56')],
            'email' => ['label' => 'Email', 'type' => 'email'],
            'referral_code_used' => ['label' => app_text('auto.k_23f8d055a5d6')],
            'client_stage' => ['label' => 'Этап клиента', 'type' => 'select', 'options' => array_keys(client_stage_labels()), 'required' => true],
            'notifications_enabled' => ['label' => 'Разрешены сообщения', 'type' => 'checkbox', 'default' => 1],
            'status' => ['label' => app_text('auto.k_f7f293b5c58c'), 'type' => 'select', 'options' => ['active', 'blocked', 'unsubscribed'], 'required' => true],
        ],
    ],
    'platform_accounts' => [
        'title' => app_text('auto.k_68a410fd6049'),
        'table' => 'platform_accounts',
        'columns' => ['id', 'end_user_id', 'platform', 'platform_user_id', 'username'],
        'fields' => [
            'end_user_id' => ['label' => app_text('auto.k_51aff1853949'), 'type' => 'select', 'source' => 'end_users', 'required' => true],
            'platform' => ['label' => app_text('auto.k_89009febe5c6'), 'type' => 'select', 'options' => ['telegram', 'VK', 'OK', 'MAX', 'web'], 'required' => true],
            'platform_user_id' => ['label' => app_text('auto.k_c7f40b63aad7'), 'required' => true],
            'username' => ['label' => 'Username'],
        ],
    ],
    'leads' => [
        'title' => app_text('auto.k_be11d71726a6'),
        'table' => 'leads',
        'columns' => ['id', 'end_user_id', 'manager_id', 'reseller_id', 'product_id', 'request_type', 'source_platform', 'status', 'created_at'],
        'fields' => [
            'end_user_id' => ['label' => app_text('auto.k_51aff1853949'), 'type' => 'select', 'source' => 'end_users', 'required' => true],
            'manager_id' => ['label' => app_text('auto.k_8d98911527e4'), 'type' => 'select', 'source' => 'managers', 'nullable' => true],
            'reseller_id' => ['label' => app_text('auto.k_86469fea3a4a'), 'type' => 'select', 'source' => 'resellers', 'nullable' => true],
            'product_id' => ['label' => app_text('auto.k_82a9ca014bb8'), 'type' => 'select', 'source' => 'products', 'nullable' => true],
            'request_type' => [
                'label' => 'Тип обращения',
                'type' => 'select',
                'options' => ['consultation', 'product', 'test_result', 'cashback', 'cooperation', 'other'],
                'required' => true,
            ],
            'source_platform' => ['label' => app_text('auto.k_89009febe5c6'), 'type' => 'select', 'options' => ['telegram', 'VK', 'OK', 'MAX', 'web'], 'required' => true],
            'message' => ['label' => app_text('auto.k_dc72346ac447'), 'type' => 'textarea'],
            'status' => ['label' => app_text('auto.k_f7f293b5c58c'), 'type' => 'select', 'options' => ['new', 'contacted', 'interested', 'closed', 'lost'], 'required' => true],
        ],
    ],
    'categories' => [
        'title' => app_text('auto.k_f7d9b1c868fa'),
        'table' => 'product_categories',
        'columns' => ['id', 'title', 'slug', 'sort_order', 'is_active'],
        'fields' => [
            'title' => ['label' => app_text('auto.k_3de49828e86a'), 'required' => true],
            'slug' => ['label' => 'Slug', 'required' => true],
            'description' => ['label' => app_text('auto.k_f5441f6aee76'), 'type' => 'textarea'],
            'sort_order' => ['label' => app_text('auto.k_ed030118aad8'), 'type' => 'number', 'default' => 100],
            'is_active' => ['label' => app_text('auto.k_c1ae516375c4'), 'type' => 'checkbox', 'default' => 1],
        ],
    ],
    'products' => [
        'title' => app_text('auto.k_c85756a1ae45'),
        'table' => 'products',
        'columns' => ['id', 'category_id', 'title', 'slug', 'price', 'is_active'],
        'fields' => [
            'category_id' => ['label' => app_text('auto.k_1cf49d95b0ed'), 'type' => 'select', 'source' => 'product_categories', 'nullable' => true],
            'title' => ['label' => app_text('auto.k_3de49828e86a'), 'required' => true],
            'slug' => ['label' => 'Slug', 'required' => true],
            'short_description' => ['label' => app_text('auto.k_d1b43352dd0b'), 'type' => 'textarea'],
            'full_description' => ['label' => app_text('auto.k_a6c29f1af453'), 'type' => 'textarea'],
            'composition' => ['label' => app_text('auto.k_c37407200657'), 'type' => 'textarea'],
            'usage_text' => ['label' => app_text('auto.k_1f14ddbb7157'), 'type' => 'textarea'],
            'warning_text' => ['label' => app_text('auto.k_e48b13edc15f'), 'type' => 'textarea'],
            'contraindications' => ['label' => app_text('auto.k_b4307011a15a'), 'type' => 'textarea'],
            'image_path' => ['label' => app_text('auto.k_56a1fd52891d'), 'type' => 'file', 'accept' => 'image/*'],
            'document_path' => ['label' => app_text('auto.k_2f76dff0da9f'), 'type' => 'file', 'accept' => 'application/pdf'],
            'video_url' => ['label' => app_text('auto.k_54fbfaf96a2d')],
            'price' => ['label' => app_text('auto.k_367e2792c179'), 'type' => 'number', 'step' => '0.01', 'nullable' => true],
            'purchase_url' => ['label' => app_text('auto.k_ab281ec27935')],
            'sort_order' => ['label' => app_text('auto.k_ed030118aad8'), 'type' => 'number', 'default' => 100],
            'is_active' => ['label' => app_text('auto.k_667904ef22a4'), 'type' => 'checkbox', 'default' => 1],
        ],
    ],
    'tests' => [
        'title' => app_text('auto.k_663c94d30018'),
        'table' => 'tests',
        'columns' => ['id', 'title', 'category_id', 'scoring_type', 'is_active', 'sort_order'],
        'fields' => [
            'title' => ['label' => app_text('auto.k_3de49828e86a'), 'required' => true],
            'description' => ['label' => app_text('auto.k_f5441f6aee76'), 'type' => 'textarea'],
            'category_id' => ['label' => app_text('auto.k_19c85838e63f'), 'type' => 'select', 'source' => 'product_categories', 'nullable' => true],
            'scoring_type' => ['label' => 'Тип теста', 'type' => 'select', 'options' => ['single', 'multiscale'], 'required' => true],
            'emoji' => ['label' => 'Emoji'],
            'intro_text' => ['label' => 'Текст перед стартом', 'type' => 'textarea'],
            'intro_image_path' => ['label' => 'Картинка intro', 'type' => 'file', 'accept' => 'image/*'],
            'intro_video_url' => ['label' => 'Видео intro'],
            'sort_order' => ['label' => app_text('auto.k_ed030118aad8'), 'type' => 'number', 'default' => 100],
            'is_active' => ['label' => app_text('auto.k_667904ef22a4'), 'type' => 'checkbox', 'default' => 1],
        ],
    ],
    'broadcasts' => [
        'title' => app_text('auto.k_08a679f215bd'),
        'table' => 'broadcasts',
        'columns' => ['id', 'title', 'platform', 'target_type', 'scheduled_at', 'status'],
        'fields' => [
            'title' => ['label' => app_text('auto.k_3de49828e86a'), 'required' => true],
            'message_text' => ['label' => app_text('auto.k_1ba376a71bcf'), 'type' => 'textarea'],
            'audience_type' => ['label' => 'Получатели', 'type' => 'select', 'options' => ['clients', 'consultants'], 'required' => true],
            'image_path' => ['label' => app_text('auto.k_56a1fd52891d'), 'type' => 'file', 'accept' => 'image/*'],
            'video_path' => ['label' => 'Видео MP4', 'type' => 'file', 'accept' => 'video/mp4'],
            'button_text' => ['label' => app_text('auto.k_f9fd27363780')],
            'button_url' => ['label' => app_text('auto.k_668acad1ed4c')],
            'target_type' => ['label' => app_text('auto.k_e9476ab1820b'), 'type' => 'select', 'options' => ['all', 'reseller', 'manager', 'segment'], 'required' => true],
            'target_reseller_id' => ['label' => app_text('auto.k_86469fea3a4a'), 'type' => 'select', 'source' => 'resellers', 'nullable' => true],
            'target_manager_id' => ['label' => app_text('auto.k_8d98911527e4'), 'type' => 'select', 'source' => 'managers', 'nullable' => true],
            'platform' => ['label' => app_text('auto.k_89009febe5c6'), 'type' => 'select', 'options' => ['all', 'telegram', 'VK', 'OK', 'MAX'], 'required' => true],
            'schedule_type' => ['label' => app_text('auto.k_f04bd0a06491'), 'type' => 'select', 'options' => ['once', 'daily', 'weekly', 'monthly'], 'required' => true],
            'scheduled_at' => ['label' => app_text('auto.k_854ba1dc86aa'), 'type' => 'datetime-local', 'nullable' => true],
            'status' => ['label' => app_text('auto.k_f7f293b5c58c'), 'type' => 'select', 'options' => ['draft', 'scheduled', 'sent', 'cancelled'], 'required' => true],
        ],
    ],
    'content' => [
        'title' => app_text('auto.k_5e30f01694b5'),
        'table' => 'content_posts',
        'columns' => ['id', 'title', 'owner_type', 'owner_id', 'status', 'publish_at', 'created_by'],
        'fields' => [
            'owner_type' => ['label' => app_text('integrations.owner_type'), 'type' => 'select', 'options' => ['reseller', 'manager'], 'nullable' => true],
            'owner_id' => ['label' => app_text('integrations.owner_id'), 'type' => 'number', 'nullable' => true],
            'content_type' => ['label' => app_text('auto.k_ef19578bced0'), 'type' => 'select', 'options' => ['article', 'image', 'pdf', 'video', 'link'], 'required' => true],
            'section_type' => ['label' => 'Раздел мини-сайта', 'type' => 'select', 'options' => ['general', 'story', 'result', 'promotion', 'giveaway', 'program', 'marathon'], 'required' => true],
            'title' => ['label' => app_text('auto.k_a8504d513adf'), 'required' => true],
            'short_text' => ['label' => app_text('auto.k_45cab8e7b9f1'), 'type' => 'textarea'],
            'full_text' => ['label' => app_text('auto.k_88a3ec931c4d'), 'type' => 'textarea'],
            'image_path' => ['label' => app_text('auto.k_56a1fd52891d'), 'type' => 'file', 'accept' => 'image/*'],
            'attachment_path' => ['label' => app_text('auto.k_1e51e67e49b3'), 'type' => 'file', 'accept' => 'application/pdf,video/mp4,image/*'],
            'video_url' => ['label' => app_text('auto.k_54fbfaf96a2d')],
            'button_text' => ['label' => app_text('auto.k_f9fd27363780')],
            'button_url' => ['label' => app_text('auto.k_668acad1ed4c')],
            'category_id' => ['label' => app_text('auto.k_1cf49d95b0ed'), 'type' => 'select', 'source' => 'product_categories', 'nullable' => true],
            'status' => ['label' => app_text('auto.k_f7f293b5c58c'), 'type' => 'select', 'options' => ['draft', 'published', 'hidden'], 'required' => true],
            'publish_at' => ['label' => app_text('auto.k_8ad0765b3c02'), 'type' => 'datetime-local', 'nullable' => true],
        ],
    ],
    'integrations' => [
        'title' => app_text('integrations.title'),
        'table' => 'messaging_integrations',
        'columns' => ['id', 'owner_type', 'owner_id', 'platform', 'title', 'external_id', 'callback_last_event_at', 'is_active'],
        'fields' => [
            'owner_type' => ['label' => app_text('integrations.owner_type'), 'type' => 'select', 'options' => ['reseller', 'manager'], 'required' => true],
            'owner_id' => ['label' => app_text('integrations.owner_id'), 'type' => 'number', 'required' => true],
            'platform' => ['label' => app_text('auto.k_89009febe5c6'), 'type' => 'select', 'options' => ['VK', 'OK', 'telegram', 'MAX'], 'required' => true],
            'title' => ['label' => app_text('auto.k_3de49828e86a'), 'required' => true],
            'external_id' => ['label' => app_text('integrations.external_id')],
            'access_token' => ['label' => app_text('integrations.access_token'), 'type' => 'textarea'],
            'callback_confirmation_code' => ['label' => app_text('integrations.callback_confirmation_code')],
            'callback_secret' => ['label' => app_text('integrations.callback_secret')],
            'callback_last_event_at' => ['label' => app_text('integrations.callback_last_event_at'), 'readonly' => true],
            'callback_last_error' => ['label' => app_text('integrations.callback_last_error'), 'type' => 'textarea', 'readonly' => true],
            'is_active' => ['label' => app_text('auto.k_667904ef22a4'), 'type' => 'checkbox', 'default' => 1],
        ],
    ],
    'legal_documents' => [
        'title' => 'Юридические документы',
        'table' => 'legal_documents',
        'columns' => ['id', 'document_type', 'title', 'version', 'is_required', 'is_active', 'updated_at'],
        'fields' => [
            'document_type' => [
                'label' => 'Тип документа',
                'type' => 'select',
                'options' => ['privacy_policy', 'personal_data_consent', 'health_data_consent', 'marketing_consent', 'user_agreement', 'leader_offer'],
                'required' => true,
            ],
            'title' => ['label' => 'Название', 'required' => true],
            'version' => ['label' => 'Версия', 'required' => true],
            'body' => ['label' => 'Текст документа', 'type' => 'textarea', 'required' => true],
            'is_required' => ['label' => 'Обязательный', 'type' => 'checkbox', 'default' => 1],
            'is_active' => ['label' => 'Активен', 'type' => 'checkbox', 'default' => 1],
        ],
    ],
];

$moduleKey = $_GET['module'] ?? 'users';
if (!isset($modules[$moduleKey]) || !can_manage($moduleKey, $admin)) {
    http_response_code(403);
    exit('Access denied');
}

$module = $modules[$moduleKey];
$title = $module['title'];

function scope_where_for_module(string $moduleKey, array $admin): array
{
    if ($moduleKey === 'users') {
        return scope_where_for_users($admin);
    }

    if ($moduleKey === 'platform_accounts') {
        [$userWhere, $userParams] = scope_where_for_users($admin);
        if (!$userWhere) {
            return ['', []];
        }
        return [
            'WHERE end_user_id IN (SELECT id FROM end_users ' . $userWhere . ')',
            $userParams,
        ];
    }

    if ($moduleKey === 'leads') {
        return scope_where_for_leads($admin);
    }

    if ($moduleKey === 'managers' && $admin['role'] === 'reseller') {
        return ['WHERE reseller_id = :scope_reseller_id', ['scope_reseller_id' => $admin['reseller_id']]];
    }

    if (in_array($moduleKey, owned_modules(), true)) {
        return owner_scope_condition($admin, '', $moduleKey);
    }

    if ($moduleKey === 'broadcasts') {
        return owned_content_scope_condition('broadcasts', $admin);
    }

    if ($moduleKey === 'integrations') {
        return integration_scope_condition($admin);
    }

    return ['', []];
}

function scoped_row_exists(string $moduleKey, array $module, int $id, array $admin): bool
{
    [$where, $params] = scope_where_for_module($moduleKey, $admin);
    $sql = "SELECT COUNT(*) FROM {$module['table']} WHERE id = :id";
    if ($where) {
        $sql .= ' AND ' . preg_replace('/^WHERE\s+/i', '', $where);
    }
    $params['id'] = $id;
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn() > 0;
}


function owned_modules(): array
{
    return ['categories', 'products', 'tests', 'content'];
}

function owner_scope_condition(array $admin, string $alias = '', string $moduleKey = ''): array
{
    if ($moduleKey !== '' && owned_content_config($moduleKey)) {
        return owned_content_scope_condition($moduleKey, $admin, $alias);
    }

    $prefix = $alias !== '' ? $alias . '.' : '';
    if ($admin['role'] === 'superadmin') {
        return ['', []];
    }

    if ($admin['role'] === 'reseller') {
        return [
            'WHERE (' . $prefix . 'owner_type IS NULL OR (' . $prefix . 'owner_type = "reseller" AND ' . $prefix . 'owner_id = :owner_reseller_id))',
            ['owner_reseller_id' => $admin['reseller_id']],
        ];
    }

    $parts = [$prefix . 'owner_type IS NULL'];
    $params = [];
    if (!empty($admin['reseller_id'])) {
        $parts[] = '(' . $prefix . 'owner_type = "reseller" AND ' . $prefix . 'owner_id = :owner_reseller_id)';
        $params['owner_reseller_id'] = $admin['reseller_id'];
    }
    $parts[] = '(' . $prefix . 'owner_type = "manager" AND ' . $prefix . 'owner_id = :owner_manager_id)';
    $params['owner_manager_id'] = $admin['manager_id'];

    return [
        'WHERE (' . implode(' OR ', $parts) . ')',
        $params,
    ];
}

function integration_scope_condition(array $admin): array
{
    if ($admin['role'] === 'superadmin') {
        return ['', []];
    }

    if ($admin['role'] === 'reseller') {
        return [
            'WHERE ((owner_type = "reseller" AND owner_id = :scope_reseller_id) OR (owner_type = "manager" AND owner_id IN (SELECT id FROM managers WHERE reseller_id = :scope_reseller_id_sub)))',
            ['scope_reseller_id' => $admin['reseller_id'], 'scope_reseller_id_sub' => $admin['reseller_id']],
        ];
    }

    return ['WHERE owner_type = "manager" AND owner_id = :scope_manager_id', ['scope_manager_id' => $admin['manager_id']]];
}

function scoped_end_user_exists(int $endUserId, array $admin): bool
{
    [$where, $params] = scope_where_for_users($admin);
    $sql = 'SELECT COUNT(*) FROM end_users WHERE id = :id';
    if ($where) {
        $sql .= ' AND ' . preg_replace('/^WHERE\s+/i', '', $where);
    }
    $params['id'] = $endUserId;
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn() > 0;
}

function user_display_label(array $row): string
{
    $name = trim((string)($row['full_name'] ?? ''));
    if (is_technical_client_name($name)) {
        $name = '';
    }
    if ($name === '') {
        $name = trim((string)($row['username'] ?? ''));
    }
    if ($name === '') {
        $platform = trim((string)($row['platform'] ?? ''));
        $name = ($platform ? platform_label($platform) . ' клиент ' : 'Клиент ') . '#' . (int)$row['id'];
    }

    $platform = trim((string)($row['platform'] ?? ''));
    $platformUserId = trim((string)($row['platform_user_id'] ?? ''));
    return '#' . (int)$row['id'] . ' ' . $name . ($platform ? ' (' . platform_label($platform) . ' ' . $platformUserId . ')' : '');
}

function merge_user_base_select(): string
{
    return "SELECT eu.id, eu.first_name, eu.last_name,
                CONCAT_WS(' ', NULLIF(eu.first_name, ''), NULLIF(eu.last_name, '')) AS full_name,
                eu.username, eu.platform, eu.platform_user_id, eu.gender, eu.birth_date,
                eu.age_years, eu.city, eu.phone, eu.email, eu.reseller_id, eu.manager_id,
                (SELECT GROUP_CONCAT(CONCAT(pa.platform, ':', pa.platform_user_id) ORDER BY FIELD(pa.platform, 'telegram', 'VK', 'OK', 'MAX', 'web'), pa.id SEPARATOR ', ')
                 FROM platform_accounts pa
                 WHERE pa.end_user_id = eu.id) AS platform_accounts_summary
            FROM end_users eu";
}

function merge_user_row(int $userId, array $admin): ?array
{
    [$where, $params] = scoped_where_with_alias(scope_where_for_users($admin), 'eu');
    $where = $where
        ? $where . ' AND eu.id = :user_id AND eu.merged_into_user_id IS NULL'
        : 'WHERE eu.id = :user_id AND eu.merged_into_user_id IS NULL';
    $params['user_id'] = $userId;

    $stmt = db()->prepare(merge_user_base_select() . " $where LIMIT 1");
    $stmt->execute($params);
    $row = $stmt->fetch();

    return $row ?: null;
}

function normalize_merge_text(?string $value): string
{
    $value = trim((string)$value);
    $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    $value = strtr($value, [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
        'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'i', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch',
        'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        'А' => 'a', 'Б' => 'b', 'В' => 'v', 'Г' => 'g', 'Д' => 'd', 'Е' => 'e', 'Ё' => 'e',
        'Ж' => 'zh', 'З' => 'z', 'И' => 'i', 'Й' => 'i', 'К' => 'k', 'Л' => 'l', 'М' => 'm',
        'Н' => 'n', 'О' => 'o', 'П' => 'p', 'Р' => 'r', 'С' => 's', 'Т' => 't', 'У' => 'u',
        'Ф' => 'f', 'Х' => 'h', 'Ц' => 'c', 'Ч' => 'ch', 'Ш' => 'sh', 'Щ' => 'sch',
        'Ъ' => '', 'Ы' => 'y', 'Ь' => '', 'Э' => 'e', 'Ю' => 'yu', 'Я' => 'ya',
    ]);

    return preg_replace('/[^a-z0-9]+/u', '', $value) ?? '';
}

function merge_user_name_variants(array $row): array
{
    $first = trim((string)($row['first_name'] ?? ''));
    $last = trim((string)($row['last_name'] ?? ''));
    $full = trim((string)($row['full_name'] ?? ''));
    $username = trim((string)($row['username'] ?? ''));
    $variants = [
        $full,
        trim($last . ' ' . $first),
        $username,
        trim((string)($row['email'] ?? '')),
        trim((string)($row['phone'] ?? '')),
    ];

    return array_values(array_filter(array_unique(array_map('normalize_merge_text', $variants))));
}

function merge_user_similarity_score(array $target, array $candidate, string $query): array
{
    $score = 0.0;
    $queryRank = 0;
    $reasons = [];
    $queryNorm = normalize_merge_text($query);
    $targetNames = merge_user_name_variants($target);
    $candidateNames = merge_user_name_variants($candidate);

    if ($queryNorm !== '') {
        $haystack = implode(' ', $candidateNames) . ' ' . normalize_merge_text((string)($candidate['platform_user_id'] ?? ''))
            . ' ' . normalize_merge_text((string)($candidate['platform_accounts_summary'] ?? ''))
            . ' ' . normalize_merge_text((string)($candidate['city'] ?? ''));
        if (str_contains($haystack, $queryNorm)) {
            $score += 55;
            $queryRank = 2;
            $reasons[] = 'найдено по запросу';
        } elseif (strlen($queryNorm) >= 3) {
            foreach ($candidateNames as $candidateName) {
                similar_text($queryNorm, $candidateName, $percent);
                if ($percent >= 55) {
                    $score += min(40, $percent * 0.45);
                    $queryRank = 1;
                    $reasons[] = 'похожее написание запроса';
                    break;
                }
            }
        }
    }

    foreach ($targetNames as $targetName) {
        if ($targetName === '') {
            continue;
        }
        foreach ($candidateNames as $candidateName) {
            if ($candidateName === '') {
                continue;
            }
            if ($targetName === $candidateName) {
                $score += 90;
                $reasons[] = 'совпадает имя';
                break 2;
            }
            if (strlen($targetName) >= 4 && strlen($candidateName) >= 4
                && (str_contains($targetName, $candidateName) || str_contains($candidateName, $targetName))) {
                $score += 55;
                $reasons[] = 'очень похожее имя';
                break 2;
            }

            similar_text($targetName, $candidateName, $percent);
            if ($percent >= 72) {
                $score += min(50, $percent * 0.55);
                $reasons[] = 'похожее имя';
                break 2;
            }
        }
    }

    if (!empty($target['birth_date']) && !empty($candidate['birth_date']) && $target['birth_date'] === $candidate['birth_date']) {
        $score += 25;
        $reasons[] = 'совпадает дата рождения';
    }
    if (!empty($target['age_years']) && !empty($candidate['age_years']) && (int)$target['age_years'] === (int)$candidate['age_years']) {
        $score += 8;
        $reasons[] = 'совпадает возраст';
    }
    if (!empty($target['city']) && !empty($candidate['city'])
        && normalize_merge_text((string)$target['city']) === normalize_merge_text((string)$candidate['city'])) {
        $score += 18;
        $reasons[] = 'совпадает город';
    }
    if (!empty($target['gender']) && !empty($candidate['gender']) && $target['gender'] === $candidate['gender']) {
        $score += 6;
    }
    if (!empty($target['manager_id']) && !empty($candidate['manager_id']) && (int)$target['manager_id'] === (int)$candidate['manager_id']) {
        $score += 5;
    } elseif (!empty($target['reseller_id']) && !empty($candidate['reseller_id']) && (int)$target['reseller_id'] === (int)$candidate['reseller_id']) {
        $score += 4;
    }
    if (!empty($target['platform']) && !empty($candidate['platform']) && $target['platform'] !== $candidate['platform']) {
        $score += 6;
        $reasons[] = 'другая платформа';
    }

    return [
        'score' => $score,
        'query_rank' => $queryRank,
        'reason' => implode(', ', array_values(array_unique($reasons))) ?: 'возможное совпадение',
    ];
}

function merge_user_search_results(int $targetUserId, string $query, array $admin): array
{
    $target = merge_user_row($targetUserId, $admin);
    if (!$target) {
        throw new RuntimeException('Пользователь недоступен.');
    }

    [$where, $params] = scoped_where_with_alias(scope_where_for_users($admin), 'eu');
    $where = $where
        ? $where . ' AND eu.id <> :target_user_id AND eu.merged_into_user_id IS NULL'
        : 'WHERE eu.id <> :target_user_id AND eu.merged_into_user_id IS NULL';
    $params['target_user_id'] = $targetUserId;

    $stmt = db()->prepare(merge_user_base_select() . " $where ORDER BY eu.id DESC LIMIT 10000");
    $stmt->execute($params);

    $queryNorm = normalize_merge_text($query);
    $minScore = $queryNorm !== '' ? 20 : 45;
    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $match = merge_user_similarity_score($target, $row, $query);
        if ($match['score'] < $minScore) {
            continue;
        }
        $items[] = [
            'id' => (int)$row['id'],
            'label' => user_display_label($row),
            'meta' => merge_user_meta_label($row),
            'reason' => $match['reason'],
            'score' => round($match['score'], 1),
            'query_rank' => $match['query_rank'],
        ];
    }

    usort($items, static fn(array $a, array $b): int => ($b['query_rank'] <=> $a['query_rank'])
        ?: ($b['score'] <=> $a['score'])
        ?: ($b['id'] <=> $a['id']));
    return array_slice($items, 0, 12);
}

function merge_user_meta_label(array $row): string
{
    $parts = [];
    if (!empty($row['city'])) {
        $parts[] = (string)$row['city'];
    }
    if (!empty($row['birth_date'])) {
        $parts[] = 'д.р. ' . $row['birth_date'];
    } elseif (!empty($row['age_years'])) {
        $parts[] = (int)$row['age_years'] . ' лет';
    }
    if (!empty($row['platform_accounts_summary'])) {
        $parts[] = (string)$row['platform_accounts_summary'];
    }

    return implode(' · ', $parts);
}

function user_platform_accounts(int $endUserId): array
{
    $stmt = db()->prepare(
        'SELECT platform, platform_user_id, username, first_name, last_name, display_name, created_at
         FROM platform_accounts
         WHERE end_user_id = :end_user_id
         ORDER BY FIELD(platform, "telegram", "VK", "OK", "MAX", "web"), id'
    );
    $stmt->execute(['end_user_id' => $endUserId]);
    return $stmt->fetchAll();
}

function merge_end_users(int $targetUserId, int $sourceUserId, array $admin): void
{
    if ($targetUserId <= 0 || $sourceUserId <= 0 || $targetUserId === $sourceUserId) {
        throw new RuntimeException('Выберите двух разных пользователей.');
    }
    if (!scoped_end_user_exists($targetUserId, $admin) || !scoped_end_user_exists($sourceUserId, $admin)) {
        throw new RuntimeException('Пользователь недоступен.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $targetStmt = $pdo->prepare('SELECT * FROM end_users WHERE id = :id AND merged_into_user_id IS NULL FOR UPDATE');
        $targetStmt->execute(['id' => $targetUserId]);
        $target = $targetStmt->fetch();

        $sourceStmt = $pdo->prepare('SELECT * FROM end_users WHERE id = :id AND merged_into_user_id IS NULL FOR UPDATE');
        $sourceStmt->execute(['id' => $sourceUserId]);
        $source = $sourceStmt->fetch();

        if (!$target || !$source) {
            throw new RuntimeException('Один из пользователей уже объединён.');
        }

        $mergeFields = [
            'username',
            'first_name',
            'last_name',
            'gender',
            'birth_date',
            'age_years',
            'city',
            'phone',
            'email',
            'referral_code_used',
            'onboarding_completed_at',
        ];
        $assignments = [];
        $mergeParams = ['target_id' => $targetUserId];
        foreach ($mergeFields as $field) {
            if (($target[$field] ?? null) === null || trim((string)$target[$field]) === '') {
                if (($source[$field] ?? null) !== null && trim((string)$source[$field]) !== '') {
                    $assignments[] = "$field = :$field";
                    $mergeParams[$field] = $source[$field];
                }
            }
        }
        if (empty($target['reseller_id']) && empty($target['manager_id'])
            && (!empty($source['reseller_id']) || !empty($source['manager_id']))) {
            $assignments[] = 'reseller_id = :reseller_id';
            $assignments[] = 'manager_id = :manager_id';
            $mergeParams['reseller_id'] = $source['reseller_id'];
            $mergeParams['manager_id'] = $source['manager_id'];
        }
        if ($assignments) {
            $mergeData = $pdo->prepare('UPDATE end_users SET ' . implode(', ', $assignments) . ' WHERE id = :target_id');
            $mergeData->execute($mergeParams);
        }

        $dedupeAutomation = $pdo->prepare(
            'DELETE source_log
             FROM automation_logs source_log
             INNER JOIN automation_logs target_log
               ON target_log.end_user_id = :target_id
              AND target_log.automation_type = source_log.automation_type
              AND target_log.context_key = source_log.context_key
              AND target_log.platform = source_log.platform
             WHERE source_log.end_user_id = :source_id'
        );
        $dedupeAutomation->execute(['target_id' => $targetUserId, 'source_id' => $sourceUserId]);

        $updates = [
            'platform_accounts' => 'end_user_id',
            'leads' => 'end_user_id',
            'user_test_sessions' => 'end_user_id',
            'recommendations' => 'end_user_id',
            'broadcast_logs' => 'end_user_id',
            'user_consents' => 'end_user_id',
            'client_stage_history' => 'end_user_id',
            'user_notifications' => 'end_user_id',
            'automation_logs' => 'end_user_id',
            'consultant_notifications' => 'end_user_id',
        ];
        foreach ($updates as $table => $column) {
            $stmt = $pdo->prepare("UPDATE $table SET $column = :target_id WHERE $column = :source_id");
            $stmt->execute(['target_id' => $targetUserId, 'source_id' => $sourceUserId]);
        }

        $mark = $pdo->prepare(
            'UPDATE end_users
             SET merged_into_user_id = :target_id, status = "unsubscribed"
             WHERE id = :source_id'
        );
        $mark->execute(['target_id' => $targetUserId, 'source_id' => $sourceUserId]);

        log_activity('admin', (int)$admin['id'], 'merge_end_users', 'end_users', $targetUserId, [
            'source_user_id' => $sourceUserId,
            'target_user_id' => $targetUserId,
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function select_options(string $source, array $admin): array
{
    $allowed = [
        'resellers' => ['table' => 'resellers', 'label' => 'name'],
        'managers' => ['table' => 'managers', 'label' => 'name'],
        'end_users' => ['table' => 'end_users', 'label' => 'platform_user_id'],
        'products' => ['table' => 'products', 'label' => 'title'],
        'product_categories' => ['table' => 'product_categories', 'label' => 'title'],
        'content_posts' => ['table' => 'content_posts', 'label' => 'title'],
        'tests' => ['table' => 'tests', 'label' => 'title'],
    ];
    if (!isset($allowed[$source])) {
        return [];
    }

    $item = $allowed[$source];
    $where = '';
    $params = [];
    if ($source === 'managers' && $admin['role'] === 'reseller') {
        $where = 'WHERE reseller_id = :reseller_id';
        $params['reseller_id'] = $admin['reseller_id'];
    }
    if ($source === 'end_users') {
        [$where, $params] = scope_where_for_users($admin);
    }
    if (in_array($source, ['products', 'product_categories', 'content_posts', 'tests'], true)) {
        $moduleForSource = match ($source) {
            'products' => 'products',
            'product_categories' => 'categories',
            'content_posts' => 'content',
            'tests' => 'tests',
            default => '',
        };
        $alias = match ($source) {
            'products' => 'p',
            'product_categories' => 'pc',
            'content_posts' => 'cp',
            'tests' => 't',
            default => 'item',
        };
        [$where, $params] = owned_content_scope_condition($moduleForSource, $admin, $alias);
        $stmt = db()->prepare("SELECT {$alias}.id, {$alias}.{$item['label']} AS label FROM {$item['table']} {$alias} $where ORDER BY {$alias}.id DESC LIMIT 500");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    $stmt = db()->prepare("SELECT id, {$item['label']} AS label FROM {$item['table']} $where ORDER BY id DESC LIMIT 500");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function safe_select_options(string $source, array $admin, array &$errors): array
{
    try {
        return select_options($source, $admin);
    } catch (Throwable $e) {
        $errors[] = app_text('auto.k_e81a65169eba') . $source . '. ' . $e->getMessage();
        return [];
    }
}

function form_option_label(string $fieldName, string $option): string
{
    if ($fieldName === 'status') {
        return status_label($option);
    }

    if (in_array($fieldName, ['platform', 'source_platform'], true)) {
        return platform_label($option);
    }

    if ($fieldName === 'target_type') {
        return target_label($option);
    }

    if ($fieldName === 'scoring_type') {
        return $option === 'multiscale' ? 'Многошкальная матрица' : 'Обычный тест';
    }

    if ($fieldName === 'client_stage') {
        return client_stage_labels()[$option] ?? $option;
    }

    if ($fieldName === 'gender') {
        return client_gender_labels()[$option] ?? $option;
    }

    if ($fieldName === 'audience_type') {
        return $option === 'consultants' ? 'Консультанты команды' : 'Клиенты';
    }

    if ($fieldName === 'section_type') {
        return [
            'general' => 'Общее',
            'story' => 'История',
            'result' => 'Результаты',
            'promotion' => 'Акция',
            'giveaway' => 'Розыгрыш',
            'program' => 'Программа',
            'marathon' => 'Марафон',
        ][$option] ?? $option;
    }

    return $option;
}

function format_cell_value(mixed $value): string
{
    if ($value === null || $value === '') {
        return app_text('auto.k_1b93795b9768');
    }

    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    return (string)$value;
}

function normalize_datetime(?string $value): ?string
{
    if (!$value) {
        return null;
    }

    return str_replace('T', ' ', $value);
}

function datetime_for_input(?string $value): string
{
    if (!$value) {
        return '';
    }

    return str_replace(' ', 'T', substr($value, 0, 16));
}

function collect_payload(array $fields): array
{
    $payload = [];
    foreach ($fields as $name => $field) {
        if (!empty($field['readonly'])) {
            continue;
        }
        $type = $field['type'] ?? 'text';
        if ($type === 'file') {
            $current = trim((string)($_POST[$name . '_current'] ?? ''));
            $removeFiles = $_POST['remove_file'] ?? [];
            $removeCurrent = is_array($removeFiles) && isset($removeFiles[$name]);
            $payload[$name] = (!$removeCurrent && $current !== '') ? $current : null;
            continue;
        }

        if ($type === 'checkbox') {
            $payload[$name] = isset($_POST[$name]) ? 1 : 0;
            continue;
        }

        $value = trim((string)($_POST[$name] ?? ''));
        if ($type === 'datetime-local') {
            $value = normalize_datetime($value);
        }
        if (($field['nullable'] ?? false) && $value === '') {
            $value = null;
        }
        if ($type === 'number' && $value !== null && $value !== '') {
            $value = str_contains($value, '.') ? (float)$value : (int)$value;
        }
        $payload[$name] = $value;
    }

    return $payload;
}

function normalize_referral_slug(?string $value): string
{
    $value = strtoupper(trim((string)$value));
    $value = preg_replace('/\s+/', '-', $value) ?? '';
    $value = preg_replace('/[^A-Z0-9_-]/', '', $value) ?? '';
    $value = preg_replace('/[-_]{2,}/', '-', $value) ?? '';
    return trim($value, '-_');
}

function normalize_module_payload(string $moduleKey, array $payload): array
{
    if (in_array($moduleKey, ['managers', 'resellers'], true) && array_key_exists('referral_code', $payload)) {
        $payload['referral_code'] = normalize_referral_slug((string)$payload['referral_code']);
    }

    return $payload;
}

function public_upload_path(string $moduleKey, string $filename): string
{
    $folder = match ($moduleKey) {
        'products' => 'products',
        'broadcasts' => 'broadcasts',
        'content' => 'content',
        'leads' => 'responses',
        default => 'files',
    };

    return '/admin/uploads/' . $folder . '/' . $filename;
}

function upload_directory(string $moduleKey): string
{
    $folder = match ($moduleKey) {
        'products' => 'products',
        'broadcasts' => 'broadcasts',
        'content' => 'content',
        'leads' => 'responses',
        default => 'files',
    };

    return dirname(__DIR__) . '/uploads/' . $folder;
}

function apply_file_uploads(string $moduleKey, array $fields, array $payload, array &$errors): array
{
    $config = app_config();
    $allowedImageTypes = $config['security']['allowed_image_types'] ?? ['image/jpeg', 'image/png', 'image/webp'];
    $allowedAttachmentTypes = $config['security']['allowed_attachment_types'] ?? [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
        'video/mp4',
    ];
    $maxBytes = (int)($config['security']['upload_max_bytes'] ?? 5242880);

    foreach ($fields as $name => $field) {
        if (($field['type'] ?? 'text') !== 'file') {
            continue;
        }

        $file = $_FILES[$name] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = app_text('auto.k_ad245cc4b64e') . ($field['label'] ?? $name);
            continue;
        }

        if ($maxBytes > 0 && (int)$file['size'] > $maxBytes) {
            $errors[] = app_text('auto.k_016932bbc64e') . round($maxBytes / 1024 / 1024, 1) . app_text('auto.k_e9f54a42c9f8');
            continue;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $accept = (string)($field['accept'] ?? 'image/*');
        $allowedTypes = $accept === 'image/*' ? $allowedImageTypes : $allowedAttachmentTypes;
        if (!in_array($mime, $allowedTypes, true)) {
            $errors[] = $accept === 'image/*'
                ? app_text('auto.k_9b79f0e123f2')
                : app_text('auto.k_56dab6d101ae');
            continue;
        }

        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            'video/mp4' => 'mp4',
            default => null,
        };
        if (!$extension) {
            $errors[] = app_text('auto.k_0d13c589d224');
            continue;
        }

        $directory = upload_directory($moduleKey);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            $errors[] = app_text('auto.k_2365f1af5b59');
            continue;
        }

        $filename = date('YmdHis') . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
        $target = $directory . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            $errors[] = app_text('auto.k_efb84954029f');
            continue;
        }

        $payload[$name] = public_upload_path($moduleKey, $filename);
    }

    return $payload;
}

function validate_payload(array $fields, array $payload): array
{
    $errors = [];
    foreach ($fields as $name => $field) {
        if (($field['required'] ?? false) && (($payload[$name] ?? null) === null || $payload[$name] === '')) {
            $errors[] = app_text('auto.k_2dc144adf452') . ($field['label'] ?? $name);
        }
        if (isset($field['options']) && ($payload[$name] ?? '') !== '' && !in_array($payload[$name], $field['options'], true)) {
            $errors[] = app_text('auto.k_337d46ded7e2') . ($field['label'] ?? $name);
        }
    }

    return $errors;
}

function validate_scope_payload(string $moduleKey, array $payload, array $admin): array
{
    $errors = [];
    if (in_array($moduleKey, ['managers', 'resellers'], true)) {
        $code = (string)($payload['referral_code'] ?? '');
        if ($code === '' || !preg_match('/^[A-Z0-9_-]{3,64}$/', $code)) {
            $errors[] = app_text('referrals.invalid_code');
        }
    }

    if ($moduleKey === 'resellers'
        && ($payload['manager_limit'] ?? null) !== null
        && (int)$payload['manager_limit'] < 0) {
        $errors[] = 'Лимит консультантов не может быть отрицательным.';
    }

    if (in_array($moduleKey, ['users', 'leads'], true) && !empty($payload['manager_id']) && !empty($payload['reseller_id'])) {
        $stmt = db()->prepare('SELECT reseller_id FROM managers WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => (int)$payload['manager_id']]);
        $manager = $stmt->fetch();
        if (!$manager) {
            $errors[] = app_text('auto.k_34b1bedb5064');
        } elseif ($manager['reseller_id'] !== null && (int)$manager['reseller_id'] !== (int)$payload['reseller_id']) {
            $errors[] = app_text('auto.k_34b1bedb5064');
        }
    }

    if (in_array($moduleKey, ['leads', 'platform_accounts'], true)) {
        $endUserId = (int)($payload['end_user_id'] ?? 0);
        if ($endUserId && !scoped_end_user_exists($endUserId, $admin)) {
            $errors[] = app_text('auto.k_34b1bedb5064');
        }
    }

    if (in_array($moduleKey, owned_modules(), true) && $admin['role'] === 'superadmin') {
        $ownerType = (string)($payload['owner_type'] ?? '');
        $ownerId = (int)($payload['owner_id'] ?? 0);
        if ($ownerType === '' && $ownerId > 0) {
            $errors[] = 'Выберите тип владельца материала.';
        } elseif ($ownerType !== '') {
            if ($ownerId <= 0) {
                $errors[] = 'Укажите ID владельца материала.';
            } elseif ($ownerType === 'reseller') {
                $stmt = db()->prepare('SELECT COUNT(*) FROM resellers WHERE id = :id');
                $stmt->execute(['id' => $ownerId]);
                if ((int)$stmt->fetchColumn() === 0) {
                    $errors[] = 'Лидер для материала не найден.';
                }
            } elseif ($ownerType === 'manager') {
                $stmt = db()->prepare('SELECT COUNT(*) FROM managers WHERE id = :id');
                $stmt->execute(['id' => $ownerId]);
                if ((int)$stmt->fetchColumn() === 0) {
                    $errors[] = 'Консультант для материала не найден.';
                }
            }
        }
    }

    if ($moduleKey === 'integrations' && $admin['role'] !== 'superadmin') {
        $ownerType = (string)($payload['owner_type'] ?? '');
        $ownerId = (int)($payload['owner_id'] ?? 0);
        if ($admin['role'] === 'reseller') {
            $allowed = ($ownerType === 'reseller' && $ownerId === (int)$admin['reseller_id']);
            if (!$allowed && $ownerType === 'manager') {
                $stmt = db()->prepare('SELECT COUNT(*) FROM managers WHERE id = :id AND reseller_id = :reseller_id');
                $stmt->execute(['id' => $ownerId, 'reseller_id' => $admin['reseller_id']]);
                $allowed = (int)$stmt->fetchColumn() > 0;
            }
            if (!$allowed) {
                $errors[] = app_text('integrations.owner_forbidden');
            }
        } elseif ($admin['role'] === 'manager' && !($ownerType === 'manager' && $ownerId === (int)$admin['manager_id'])) {
            $errors[] = app_text('integrations.owner_forbidden');
        }
    }

    if ($moduleKey === 'broadcasts'
        && trim((string)($payload['message_text'] ?? '')) === ''
        && empty($payload['image_path'])
        && empty($payload['video_path'])) {
        $errors[] = 'Добавьте текст, фотографию или видео.';
    }

    return $errors;
}

function manager_reseller_id(int $managerId): ?int
{
    $stmt = db()->prepare('SELECT reseller_id FROM managers WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $managerId]);
    $manager = $stmt->fetch();

    return $manager && $manager['reseller_id'] !== null ? (int)$manager['reseller_id'] : null;
}

function leader_manager_limit_state(?int $resellerId, ?int $excludeManagerId = null): ?array
{
    if (!$resellerId) {
        return null;
    }

    $leaderStmt = db()->prepare('SELECT manager_limit FROM resellers WHERE id = :id LIMIT 1');
    $leaderStmt->execute(['id' => $resellerId]);
    $leader = $leaderStmt->fetch();
    if (!$leader) {
        return null;
    }

    $params = ['reseller_id' => $resellerId];
    $sql = 'SELECT COUNT(*) FROM managers WHERE reseller_id = :reseller_id AND is_active = 1';
    if ($excludeManagerId) {
        $sql .= ' AND id <> :exclude_manager_id';
        $params['exclude_manager_id'] = $excludeManagerId;
    }

    $countStmt = db()->prepare($sql);
    $countStmt->execute($params);

    return [
        'limit' => $leader['manager_limit'] !== null && $leader['manager_limit'] !== '' ? (int)$leader['manager_limit'] : null,
        'active' => (int)$countStmt->fetchColumn(),
    ];
}

function validate_manager_limit_payload(string $moduleKey, array $payload, ?int $recordId = null): array
{
    if ($moduleKey !== 'managers' || (int)($payload['is_active'] ?? 0) !== 1) {
        return [];
    }

    $resellerId = nullable_int_value($payload['reseller_id'] ?? null);
    if (!$resellerId) {
        return [];
    }

    $state = leader_manager_limit_state($resellerId, $recordId);
    if (!$state || $state['limit'] === null) {
        return [];
    }

    $projected = $state['active'] + 1;
    if ($projected <= $state['limit']) {
        return [];
    }

    return [
        'У лидера заполнен лимит консультантов: ' . $state['active'] . ' из ' . $state['limit']
        . '. Увеличьте лимит в карточке лидера или отключите лишнего консультанта.',
    ];
}

function validate_leader_limit_payload(string $moduleKey, array $payload, ?int $recordId = null): array
{
    if ($moduleKey !== 'resellers' || !$recordId) {
        return [];
    }

    $limit = nullable_int_value($payload['manager_limit'] ?? null);
    if ($limit === null) {
        return [];
    }

    $state = leader_manager_limit_state($recordId);
    if (!$state || $state['active'] <= $limit) {
        return [];
    }

    return [
        'Нельзя поставить лимит ' . $limit . ': у лидера уже активных консультантов ' . $state['active'] . '.',
    ];
}

function apply_role_defaults(string $moduleKey, array $payload, array $admin): array
{
    if (in_array($moduleKey, ['users', 'leads'], true) && !empty($payload['manager_id'])) {
        $payload['reseller_id'] = manager_reseller_id((int)$payload['manager_id']);
    }

    if ($admin['role'] === 'reseller' && in_array($moduleKey, ['managers', 'users', 'leads'], true)) {
        $payload['reseller_id'] = $admin['reseller_id'];
    }
    if ($admin['role'] === 'manager' && in_array($moduleKey, ['users', 'leads'], true)) {
        $payload['manager_id'] = $admin['manager_id'];
        $payload['reseller_id'] = $admin['reseller_id'];
    }
    if (in_array($moduleKey, owned_modules(), true) && $admin['role'] !== 'superadmin') {
        $payload['owner_type'] = $admin['role'];
        $payload['owner_id'] = $admin['role'] === 'reseller' ? $admin['reseller_id'] : $admin['manager_id'];
    }
    if ($moduleKey === 'integrations' && $admin['role'] === 'manager') {
        $payload['owner_type'] = 'manager';
        $payload['owner_id'] = $admin['manager_id'];
    }
    if ($moduleKey === 'integrations' && $admin['role'] === 'reseller' && empty($payload['owner_type'])) {
        $payload['owner_type'] = 'reseller';
        $payload['owner_id'] = $admin['reseller_id'];
    }
    if ($moduleKey === 'broadcasts') {
        $payload['created_by'] = $admin['id'];
        if ($admin['role'] === 'manager') {
            $payload['owner_type'] = 'manager';
            $payload['owner_id'] = $admin['manager_id'];
            $payload['audience_type'] = 'clients';
            $payload['target_type'] = 'manager';
            $payload['target_manager_id'] = $admin['manager_id'];
            $payload['target_reseller_id'] = $admin['reseller_id'];
        } elseif ($admin['role'] === 'reseller') {
            $payload['owner_type'] = 'reseller';
            $payload['owner_id'] = $admin['reseller_id'];
            $payload['target_type'] = 'reseller';
            $payload['target_reseller_id'] = $admin['reseller_id'];
            $payload['target_manager_id'] = null;
        }
    }
    if ($moduleKey === 'content') {
        $payload['created_by'] = $admin['id'];
    }

    return $payload;
}

function nullable_int_value(mixed $value): ?int
{
    return $value === null || $value === '' ? null : (int)$value;
}

function sync_active_leads_assignment(int $endUserId, ?int $resellerId, ?int $managerId): int
{
    $stmt = db()->prepare(
        'UPDATE leads
         SET reseller_id = :reseller_id,
             manager_id = :manager_id
         WHERE end_user_id = :end_user_id
           AND status IN ("new", "contacted", "interested")'
    );
    $stmt->execute([
        'reseller_id' => $resellerId,
        'manager_id' => $managerId,
        'end_user_id' => $endUserId,
    ]);

    return $stmt->rowCount();
}

function sync_consultant_notifications_assignment(int $endUserId, ?int $resellerId, ?int $managerId): int
{
    $stmt = db()->prepare(
        'UPDATE IGNORE consultant_notifications
         SET reseller_id = :reseller_id,
             manager_id = :manager_id
         WHERE end_user_id = :end_user_id
           AND is_read = 0'
    );
    $stmt->execute([
        'reseller_id' => $resellerId,
        'manager_id' => $managerId,
        'end_user_id' => $endUserId,
    ]);

    return $stmt->rowCount();
}

function log_end_user_transfer(
    int $endUserId,
    ?int $oldResellerId,
    ?int $oldManagerId,
    ?int $newResellerId,
    ?int $newManagerId,
    array $admin,
    string $action,
    array $extra = []
): void {
    if ($oldResellerId === $newResellerId && $oldManagerId === $newManagerId) {
        return;
    }

    log_activity('admin', (int)$admin['id'], $action, 'end_users', $endUserId, $extra + [
        'old_reseller_id' => $oldResellerId,
        'old_manager_id' => $oldManagerId,
        'new_reseller_id' => $newResellerId,
        'new_manager_id' => $newManagerId,
    ]);
}

function sync_user_assignment_from_lead(int $leadId, array $leadBefore, array $payload, array $admin): void
{
    $endUserId = nullable_int_value($payload['end_user_id'] ?? $leadBefore['end_user_id'] ?? null);
    if (!$endUserId) {
        return;
    }

    $oldLeadResellerId = nullable_int_value($leadBefore['reseller_id'] ?? null);
    $oldLeadManagerId = nullable_int_value($leadBefore['manager_id'] ?? null);
    $newResellerId = nullable_int_value($payload['reseller_id'] ?? null);
    $newManagerId = nullable_int_value($payload['manager_id'] ?? null);
    if ($newResellerId === null && $newManagerId === null) {
        return;
    }
    if ($oldLeadResellerId === $newResellerId && $oldLeadManagerId === $newManagerId) {
        return;
    }

    $stmt = db()->prepare('SELECT reseller_id, manager_id FROM end_users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $endUserId]);
    $userBefore = $stmt->fetch();
    if (!$userBefore) {
        return;
    }

    $oldUserResellerId = nullable_int_value($userBefore['reseller_id'] ?? null);
    $oldUserManagerId = nullable_int_value($userBefore['manager_id'] ?? null);

    $update = db()->prepare(
        'UPDATE end_users
         SET reseller_id = :reseller_id,
             manager_id = :manager_id
         WHERE id = :id'
    );
    $update->execute([
        'reseller_id' => $newResellerId,
        'manager_id' => $newManagerId,
        'id' => $endUserId,
    ]);

    $updatedLeads = sync_active_leads_assignment($endUserId, $newResellerId, $newManagerId);
    $updatedNotifications = sync_consultant_notifications_assignment($endUserId, $newResellerId, $newManagerId);

    log_end_user_transfer(
        $endUserId,
        $oldUserResellerId,
        $oldUserManagerId,
        $newResellerId,
        $newManagerId,
        $admin,
        'transfer_end_user_from_lead',
        [
            'lead_id' => $leadId,
            'updated_active_leads' => $updatedLeads,
            'updated_unread_notifications' => $updatedNotifications,
        ]
    );
}

function detach_owner_content(string $ownerType, int $ownerId): void
{
    $updates = [
        'product_categories' => ['column' => 'is_active', 'value' => 0],
        'products' => ['column' => 'is_active', 'value' => 0],
        'tests' => ['column' => 'is_active', 'value' => 0],
        'content_posts' => ['column' => 'status', 'value' => 'hidden'],
        'broadcasts' => ['column' => 'status', 'value' => 'cancelled'],
    ];

    foreach ($updates as $table => $update) {
        $stmt = db()->prepare(
            "UPDATE {$table}
             SET {$update['column']} = :inactive_value
             WHERE owner_type = :owner_type AND owner_id = :owner_id"
        );
        $stmt->execute([
            'inactive_value' => $update['value'],
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
        ]);
    }
}

function delete_owner_service_records(string $ownerType, int $ownerId): void
{
    $stmt = db()->prepare('DELETE FROM referral_links WHERE owner_type = :owner_type AND owner_id = :owner_id');
    $stmt->execute(['owner_type' => $ownerType, 'owner_id' => $ownerId]);

    $stmt = db()->prepare('DELETE FROM messaging_integrations WHERE owner_type = :owner_type AND owner_id = :owner_id');
    $stmt->execute(['owner_type' => $ownerType, 'owner_id' => $ownerId]);

    $stmt = db()->prepare('DELETE FROM consultant_profiles WHERE owner_type = :owner_type AND owner_id = :owner_id');
    $stmt->execute(['owner_type' => $ownerType, 'owner_id' => $ownerId]);
}

function delete_crud_record(string $moduleKey, array $module, int $id, array $admin): void
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        if ($moduleKey === 'users') {
            $stmt = $pdo->prepare('DELETE FROM end_users WHERE id = :id');
            $stmt->execute(['id' => $id]);
            log_activity('admin', (int)$admin['id'], 'delete_end_users', 'end_users', $id);
            $pdo->commit();
            return;
        }

        if (owned_content_delete_for_admin($moduleKey, $id, $admin)) {
            log_activity('admin', (int)$admin['id'], 'hide_owned_' . $module['table'], $module['table'], $id);
            $pdo->commit();
            return;
        }

        if ($moduleKey === 'content') {
            $stmt = $pdo->prepare('UPDATE content_posts SET status = "hidden" WHERE id = :id');
            $stmt->execute(['id' => $id]);
            log_activity('admin', (int)$admin['id'], 'hide_content_posts', 'content_posts', $id);
            $pdo->commit();
            return;
        }

        if ($moduleKey === 'managers') {
            detach_owner_content('manager', $id);
            delete_owner_service_records('manager', $id);

            $stmt = $pdo->prepare('DELETE FROM admin_users WHERE role = "manager" AND manager_id = :manager_id');
            $stmt->execute(['manager_id' => $id]);

            $stmt = $pdo->prepare('DELETE FROM managers WHERE id = :id');
            $stmt->execute(['id' => $id]);
            log_activity('admin', (int)$admin['id'], 'delete_managers', 'managers', $id);
            $pdo->commit();
            return;
        }

        if ($moduleKey === 'resellers') {
            detach_owner_content('reseller', $id);
            delete_owner_service_records('reseller', $id);

            $stmt = $pdo->prepare('DELETE FROM admin_users WHERE role = "reseller" AND reseller_id = :reseller_id AND manager_id IS NULL');
            $stmt->execute(['reseller_id' => $id]);

            $stmt = $pdo->prepare('DELETE FROM resellers WHERE id = :id');
            $stmt->execute(['id' => $id]);
            log_activity('admin', (int)$admin['id'], 'delete_resellers', 'resellers', $id);
            $pdo->commit();
            return;
        }

        $stmt = $pdo->prepare("DELETE FROM {$module['table']} WHERE id = :id");
        $stmt->execute(['id' => $id]);
        log_activity('admin', (int)$admin['id'], 'delete_' . $module['table'], $module['table'], $id);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function manager_admin_access(int $managerId): ?array
{
    if ($managerId <= 0) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT id, name, email, is_active
         FROM admin_users
         WHERE role = "manager" AND manager_id = :manager_id
         ORDER BY id DESC
         LIMIT 1'
    );
    $stmt->execute(['manager_id' => $managerId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function reseller_admin_access(int $resellerId): ?array
{
    if ($resellerId <= 0) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT id, name, email, is_active
         FROM admin_users
         WHERE role = "reseller" AND reseller_id = :reseller_id AND manager_id IS NULL
         ORDER BY id DESC
         LIMIT 1'
    );
    $stmt->execute(['reseller_id' => $resellerId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function save_reseller_admin_access(int $resellerId, array $resellerPayload, array $post, array &$errors): void
{
    $email = trim((string)($post['admin_email'] ?? ''));
    $password = (string)($post['admin_password'] ?? '');
    $isActive = isset($post['admin_is_active']) ? 1 : 0;
    $existing = reseller_admin_access($resellerId);

    if ($email === '' && $password === '' && !$existing) {
        return;
    }

    if ($email === '') {
        $errors[] = app_text('admin_access.email_required');
        return;
    }

    if (!$existing && $password === '') {
        $errors[] = app_text('admin_access.password_required');
        return;
    }

    if ($existing) {
        $params = [
            'id' => (int)$existing['id'],
            'name' => $resellerPayload['name'] ?? $email,
            'email' => $email,
            'reseller_id' => $resellerId,
            'is_active' => $isActive,
        ];
        $passwordSql = '';
        if ($password !== '') {
            $passwordSql = ', password_hash = :password_hash';
            $params['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $stmt = db()->prepare(
            'UPDATE admin_users
             SET name = :name, email = :email, reseller_id = :reseller_id, manager_id = NULL, is_active = :is_active' . $passwordSql . '
             WHERE id = :id'
        );
        $stmt->execute($params);
        return;
    }

    $stmt = db()->prepare(
        'INSERT INTO admin_users (role, reseller_id, manager_id, name, email, password_hash, is_active)
         VALUES ("reseller", :reseller_id, NULL, :name, :email, :password_hash, :is_active)'
    );
    $stmt->execute([
        'reseller_id' => $resellerId,
        'name' => $resellerPayload['name'] ?? $email,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'is_active' => $isActive,
    ]);
}

function save_manager_admin_access(int $managerId, array $managerPayload, array $post, array &$errors): void
{
    $email = trim((string)($post['admin_email'] ?? ''));
    $password = (string)($post['admin_password'] ?? '');
    $isActive = isset($post['admin_is_active']) ? 1 : 0;
    $existing = manager_admin_access($managerId);

    if ($email === '' && $password === '' && !$existing) {
        return;
    }

    if ($email === '') {
        $errors[] = app_text('admin_access.email_required');
        return;
    }

    if (!$existing && $password === '') {
        $errors[] = app_text('admin_access.password_required');
        return;
    }

    $resellerId = $managerPayload['reseller_id'] !== null && $managerPayload['reseller_id'] !== ''
        ? (int)$managerPayload['reseller_id']
        : null;

    if ($existing) {
        $params = [
            'id' => (int)$existing['id'],
            'name' => $managerPayload['name'] ?? $email,
            'email' => $email,
            'reseller_id' => $resellerId,
            'manager_id' => $managerId,
            'is_active' => $isActive,
        ];
        $passwordSql = '';
        if ($password !== '') {
            $passwordSql = ', password_hash = :password_hash';
            $params['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $stmt = db()->prepare(
            'UPDATE admin_users
             SET name = :name, email = :email, reseller_id = :reseller_id, manager_id = :manager_id, is_active = :is_active' . $passwordSql . '
             WHERE id = :id'
        );
        $stmt->execute($params);
        return;
    }

    $stmt = db()->prepare(
        'INSERT INTO admin_users (role, reseller_id, manager_id, name, email, password_hash, is_active)
         VALUES ("manager", :reseller_id, :manager_id, :name, :email, :password_hash, :is_active)'
    );
    $stmt->execute([
        'reseller_id' => $resellerId,
        'manager_id' => $managerId,
        'name' => $managerPayload['name'] ?? $email,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'is_active' => $isActive,
    ]);
}

function save_record(string $moduleKey, array $module, array $payload, ?int $id, array $admin): int
{
    $payload = apply_role_defaults($moduleKey, $payload, $admin);
    $columns = array_keys($payload);

    if ($id) {
        $before = null;
        if ($moduleKey === 'users') {
            $beforeStmt = db()->prepare('SELECT reseller_id, manager_id, client_stage FROM end_users WHERE id = :id LIMIT 1');
            $beforeStmt->execute(['id' => $id]);
            $before = $beforeStmt->fetch();
        } elseif ($moduleKey === 'leads') {
            $beforeStmt = db()->prepare('SELECT end_user_id, reseller_id, manager_id FROM leads WHERE id = :id LIMIT 1');
            $beforeStmt->execute(['id' => $id]);
            $before = $beforeStmt->fetch();
        }

        $assignments = implode(', ', array_map(static fn($column) => "`$column` = :$column", $columns));
        $payload['id'] = $id;
        $stmt = db()->prepare("UPDATE {$module['table']} SET $assignments WHERE id = :id");
        $stmt->execute($payload);
        log_activity('admin', (int)$admin['id'], 'update_' . $module['table'], $module['table'], $id);

        if ($moduleKey === 'users' && $before) {
            $oldResellerId = $before['reseller_id'] !== null ? (int)$before['reseller_id'] : null;
            $oldManagerId = $before['manager_id'] !== null ? (int)$before['manager_id'] : null;
            $newResellerId = $payload['reseller_id'] !== null ? (int)$payload['reseller_id'] : null;
            $newManagerId = $payload['manager_id'] !== null ? (int)$payload['manager_id'] : null;
            if ($oldResellerId !== $newResellerId || $oldManagerId !== $newManagerId) {
                $updatedLeads = sync_active_leads_assignment($id, $newResellerId, $newManagerId);
                $updatedNotifications = sync_consultant_notifications_assignment($id, $newResellerId, $newManagerId);
                log_end_user_transfer(
                    $id,
                    $oldResellerId,
                    $oldManagerId,
                    $newResellerId,
                    $newManagerId,
                    $admin,
                    'transfer_end_user',
                    [
                        'updated_active_leads' => $updatedLeads,
                        'updated_unread_notifications' => $updatedNotifications,
                    ]
                );
            }
            $oldStage = (string)($before['client_stage'] ?? 'new');
            $newStage = (string)($payload['client_stage'] ?? $oldStage);
            if ($oldStage !== $newStage) {
                $touchStage = db()->prepare('UPDATE end_users SET stage_updated_at = NOW() WHERE id = :id');
                $touchStage->execute(['id' => $id]);
                $history = db()->prepare(
                    'INSERT INTO client_stage_history (
                        end_user_id, previous_stage, new_stage, source, actor_id
                     ) VALUES (
                        :end_user_id, :previous_stage, :new_stage, :source, :actor_id
                     )'
                );
                $history->execute([
                    'end_user_id' => $id,
                    'previous_stage' => $oldStage,
                    'new_stage' => $newStage,
                    'source' => $admin['role'] === 'manager' ? 'consultant' : ($admin['role'] === 'reseller' ? 'leader' : 'admin'),
                    'actor_id' => $admin['id'],
                ]);
            }
        }
        if ($moduleKey === 'leads' && $before) {
            sync_user_assignment_from_lead($id, $before, $payload, $admin);
        }

        return $id;
    }

    $columnSql = implode(', ', array_map(static fn($column) => "`$column`", $columns));
    $placeholderSql = implode(', ', array_map(static fn($column) => ":$column", $columns));
    $stmt = db()->prepare("INSERT INTO {$module['table']} ($columnSql) VALUES ($placeholderSql)");
    $stmt->execute($payload);
    $newId = (int)db()->lastInsertId();
    log_activity('admin', (int)$admin['id'], 'create_' . $module['table'], $module['table'], $newId);
    return $newId;
}

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$errors = [];
$success = $_GET['success'] ?? null;
$editRow = null;
$canCreate = crud_create_enabled($moduleKey);
$canEdit = crud_edit_enabled($moduleKey);
$canDelete = crud_delete_enabled($moduleKey);
$formFields = crud_form_fields($moduleKey, $module['fields']);
if (in_array($moduleKey, owned_modules(), true) && $admin['role'] !== 'superadmin') {
    unset($formFields['owner_type'], $formFields['owner_id']);
}
if ($moduleKey === 'integrations' && $admin['role'] !== 'superadmin') {
    unset($formFields['owner_type'], $formFields['owner_id']);
}

if ($moduleKey === 'users' && $action === 'merge_search') {
    header('Content-Type: application/json; charset=utf-8');

    $targetUserId = (int)($_GET['id'] ?? 0);
    $query = trim((string)($_GET['q'] ?? ''));

    try {
        if (!$targetUserId) {
            http_response_code(404);
            echo json_encode(['items' => [], 'error' => app_text('user_merge.target_required')], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode(['items' => merge_user_search_results($targetUserId, $query, $admin)], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['items' => [], 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($action === 'create' && !$canCreate) {
    $errors[] = app_text('auto.k_868d1fd837c9');
    $action = 'list';
}

if ($action === 'edit' && !$canEdit) {
    $errors[] = app_text('auto.k_e26ff1144bac');
    $action = 'list';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $postAction = $_POST['action'] ?? 'save';
    $postId = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;

    if ($postAction === 'send_lead_response') {
        if ($moduleKey !== 'leads' || !$postId || !scoped_row_exists($moduleKey, $module, $postId, $admin)) {
            http_response_code(404);
            exit('Record not found');
        }

        try {
            $responseId = create_and_send_lead_response($postId, $admin, $errors);
        } catch (Throwable $e) {
            $responseId = null;
            $errors[] = app_text('auto.k_5cececf97899') . $e->getMessage();
        }
        if ($responseId && !$errors) {
            $sentPlatform = lead_response_platform($responseId);
            $sentPlatformQuery = $sentPlatform !== '' ? '&sent_platform=' . rawurlencode($sentPlatform) : '';
            redirect('crud.php?module=leads&action=edit&id=' . $postId . '&success=response_sent' . $sentPlatformQuery);
        }
        $action = 'edit';
        $id = $postId;
    }

    if ($postAction === 'run_broadcast') {
        if ($moduleKey !== 'broadcasts' || !$postId || !scoped_row_exists($moduleKey, $module, $postId, $admin)) {
            http_response_code(404);
            exit('Record not found');
        }

        try {
            $postId = owned_content_editable_id('broadcasts', $postId, $admin);
            $result = run_broadcast($postId);
            redirect('crud.php?module=broadcasts&success=broadcast_sent&sent=' . (int)$result['sent'] . '&failed=' . (int)$result['failed']);
        } catch (Throwable $e) {
            $errors[] = app_text('broadcasts.run_failed') . $e->getMessage();
            $action = 'list';
        }
    }

    if ($moduleKey === 'tests' && $postId && str_starts_with($postAction, 'test_')) {
        if (!scoped_row_exists($moduleKey, $module, $postId, $admin)) {
            http_response_code(404);
            exit('Record not found');
        }
        $editablePostId = owned_content_editable_id('tests', $postId, $admin);
        if ($editablePostId !== $postId) {
            redirect('crud.php?module=tests&action=edit&id=' . $editablePostId . '&success=personal_copy');
        }
        handle_test_builder_action($postAction, $postId, $admin, $errors);
        if (!$errors) {
            redirect('crud.php?module=tests&action=edit&id=' . $postId . '&success=saved');
        }
        $action = 'edit';
        $id = $postId;
    }

    if ($postAction === 'merge_user') {
        if ($moduleKey !== 'users' || !$postId || !scoped_row_exists($moduleKey, $module, $postId, $admin)) {
            http_response_code(404);
            exit('Record not found');
        }

        $sourceUserId = (int)($_POST['source_user_id'] ?? 0);
        try {
            merge_end_users($postId, $sourceUserId, $admin);
            redirect('crud.php?module=users&action=edit&id=' . $postId . '&success=merged');
        } catch (Throwable $e) {
            $errors[] = app_text('user_merge.failed') . $e->getMessage();
        }
        $action = 'edit';
        $id = $postId;
    }

    if ($postAction === 'delete') {
        if (!$canDelete) {
            $errors[] = app_text('auto.k_da5ca3c5fc80');
        } else {
        if (!$postId || !scoped_row_exists($moduleKey, $module, $postId, $admin)) {
            http_response_code(404);
            exit('Record not found');
        }

        try {
            delete_crud_record($moduleKey, $module, $postId, $admin);
            redirect('crud.php?module=' . urlencode($moduleKey) . '&success=deleted');
        } catch (Throwable $e) {
            $errors[] = app_text('auto.k_cdec27146810') . $e->getMessage();
        }
        }
        $action = 'list';
    }

    if ($postAction === 'save') {
    if (($postId && !$canEdit) || (!$postId && !$canCreate)) {
        $errors[] = $postId
            ? app_text('auto.k_fd8f8d50baa8')
            : app_text('auto.k_6eaca3d4de92');
        $action = 'list';
    } else {
    if ($postId && !scoped_row_exists($moduleKey, $module, $postId, $admin)) {
        http_response_code(404);
        exit('Record not found');
    }

    if ($postId && owned_content_config($moduleKey)) {
        $postId = owned_content_editable_id($moduleKey, $postId, $admin);
        $id = $postId;
    }

    $payload = collect_payload($formFields);
    $payload = normalize_module_payload($moduleKey, $payload);
    $payload = apply_file_uploads($moduleKey, $formFields, $payload, $errors);
    $errors = array_merge($errors, validate_payload($formFields, $payload));
    $payload = apply_role_defaults($moduleKey, $payload, $admin);
    $errors = array_merge($errors, validate_scope_payload($moduleKey, $payload, $admin));
    $errors = array_merge($errors, validate_manager_limit_payload($moduleKey, $payload, $postId));
    $errors = array_merge($errors, validate_leader_limit_payload($moduleKey, $payload, $postId));
    if (!$errors) {
        try {
            $savedId = save_record($moduleKey, $module, $payload, $postId, $admin);
            if ($moduleKey === 'managers' && $admin['role'] === 'superadmin') {
                save_manager_admin_access($savedId, $payload, $_POST, $errors);
                if ($errors) {
                    $action = $postId ? 'edit' : 'create';
                    $id = $savedId;
                    $editRow = $payload + ['id' => $savedId];
                }
            }
            if ($moduleKey === 'resellers' && $admin['role'] === 'superadmin') {
                save_reseller_admin_access($savedId, $payload, $_POST, $errors);
                if ($errors) {
                    $action = $postId ? 'edit' : 'create';
                    $id = $savedId;
                    $editRow = $payload + ['id' => $savedId];
                }
            }
            if (!$errors) {
                redirect('crud.php?module=' . urlencode($moduleKey) . '&success=saved');
            }
            $postId = $savedId;
        } catch (Throwable $e) {
            $errors[] = app_text('auto.k_02613f541f5f') . $e->getMessage();
        }
    }

    $editRow = $payload + ['id' => $postId];
    $action = $postId ? 'edit' : 'create';
    }
    }
}

if ($action === 'edit' && $id) {
    if (!scoped_row_exists($moduleKey, $module, $id, $admin)) {
        http_response_code(404);
        exit('Record not found');
    }
    if (owned_content_config($moduleKey) && $admin['role'] !== 'superadmin') {
        $editableId = owned_content_editable_id($moduleKey, $id, $admin);
        if ($editableId !== $id) {
            redirect('crud.php?module=' . urlencode($moduleKey) . '&action=edit&id=' . $editableId . '&success=personal_copy');
        }
    }
    $stmt = db()->prepare("SELECT * FROM {$module['table']} WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $editRow = $stmt->fetch();
}

$rows = [];
$listHtml = '';
$displayColumns = crud_display_columns($moduleKey);
try {
    [$listSql, $params] = crud_list_query($moduleKey, $module, $admin);
    $stmt = db()->prepare($listSql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $listHtml = render_crud_list($moduleKey, $displayColumns, $rows, $canEdit, $canDelete);
} catch (Throwable $e) {
    $errors[] = app_text('auto.k_49fb23bb29cf') . $e->getMessage();
    $listHtml = app_text('auto.k_fda0c24ca2e9');
}

$adminAccess = null;
if (in_array($moduleKey, ['managers', 'resellers'], true) && $admin['role'] === 'superadmin' && ($action === 'create' || $action === 'edit')) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $adminAccess = [
            'email' => trim((string)($_POST['admin_email'] ?? '')),
            'is_active' => isset($_POST['admin_is_active']) ? 1 : 0,
        ];
    } elseif (!empty($editRow['id'])) {
        $adminAccess = $moduleKey === 'managers'
            ? manager_admin_access((int)$editRow['id'])
            : reseller_admin_access((int)$editRow['id']);
    }
}

require __DIR__ . '/../app/views/layouts/header.php';
?>
<div class="toolbar">
    <h1><?= h($title) ?></h1>
    <?php if ($canCreate): ?>
        <a class="button" href="crud.php?module=<?= h($moduleKey) ?>&action=create"><?= h(app_text('auto.k_559a87f7cc13')) ?></a>
    <?php endif; ?>
</div>
<?php if ($success === 'saved'): ?>
    <div class="notice success"><?= h(app_text('auto.k_ead4c298eba3')) ?></div>
<?php elseif ($success === 'deleted'): ?>
    <div class="notice success"><?= h(app_text('auto.k_5db71cdc4927')) ?></div>
<?php elseif ($success === 'response_sent'): ?>
    <?php $sentPlatformLabel = platform_label((string)($_GET['sent_platform'] ?? '')); ?>
    <div class="notice success"><?= h(app_text('auto.k_0184f257cbfc', ['platform' => $sentPlatformLabel !== '' ? $sentPlatformLabel : 'платформу заявки'])) ?></div>
<?php elseif ($success === 'merged'): ?>
    <div class="notice success"><?= h(app_text('user_merge.success')) ?></div>
<?php elseif ($success === 'personal_copy'): ?>
    <div class="notice success"><?= h(app_text('content_ownership.personal_copy')) ?></div>
<?php elseif ($success === 'broadcast_sent'): ?>
    <div class="notice success"><?= h(app_text('broadcasts.run_success', [
        'sent' => (int)($_GET['sent'] ?? 0),
        'failed' => (int)($_GET['failed'] ?? 0),
    ])) ?></div>
<?php endif; ?>
<?php foreach ($errors as $error): ?>
    <div class="alert"><?= h($error) ?></div>
<?php endforeach; ?>
<?php if ($action === 'create' || $action === 'edit'): ?>
    <section class="panel form-panel">
        <h2><?= h(crud_form_title($moduleKey, $action)) ?></h2>
        <form method="post" class="crud-form" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= h((string)($editRow['id'] ?? '')) ?>">
            <?php foreach ($formFields as $name => $field): ?>
                <?php
                $type = $field['type'] ?? 'text';
                $value = $editRow[$name] ?? ($field['default'] ?? '');
                ?>
                <label class="field">
                    <span><?= h($field['label'] ?? $name) ?><?= ($field['required'] ?? false) ? ' *' : '' ?></span>
                    <?php if ($type === 'textarea'): ?>
                        <textarea name="<?= h($name) ?>" rows="4" <?= !empty($field['readonly']) ? 'readonly' : '' ?>><?= h((string)$value) ?></textarea>
                    <?php elseif ($type === 'select'): ?>
                        <select name="<?= h($name) ?>">
                            <?php if ($field['nullable'] ?? false): ?>
                                <option value=""><?= h(app_text('auto.k_24da5932344a')) ?></option>
                            <?php endif; ?>
                            <?php if (isset($field['options'])): ?>
                                <?php foreach ($field['options'] as $option): ?>
                                    <option value="<?= h($option) ?>" <?= (string)$value === (string)$option ? 'selected' : '' ?>><?= h(form_option_label($name, $option)) ?></option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php foreach (safe_select_options($field['source'], $admin, $errors) as $option): ?>
                                    <option value="<?= (int)$option['id'] ?>" <?= (string)$value === (string)$option['id'] ? 'selected' : '' ?>>
                                        #<?= (int)$option['id'] ?> <?= h($option['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    <?php elseif ($type === 'file'): ?>
                        <?php if ($value): ?>
                            <div class="file-control">
                                <span class="cell-muted"><?= h(app_text('media.current_file')) ?></span>
                                <a class="file-link" href="<?= h((string)$value) ?>" target="_blank" rel="noopener"><?= h(app_text('media.open_file')) ?></a>
                            <?php if (($field['accept'] ?? '') === 'image/*'): ?>
                                <img class="file-preview" src="<?= h((string)$value) ?>" alt="">
                            <?php endif; ?>
                                <label class="checkbox-line file-remove">
                                    <input type="checkbox" name="remove_file[<?= h($name) ?>]" value="1">
                                    <?= h(app_text('media.remove_current_file')) ?>
                                </label>
                            </div>
                        <?php endif; ?>
                        <input type="hidden" name="<?= h($name) ?>_current" value="<?= h((string)$value) ?>">
                        <input type="file" name="<?= h($name) ?>" <?= isset($field['accept']) ? 'accept="' . h($field['accept']) . '"' : '' ?>>
                    <?php elseif ($type === 'checkbox'): ?>
                        <input type="checkbox" name="<?= h($name) ?>" value="1" <?= (int)$value === 1 ? 'checked' : '' ?>>
                    <?php else: ?>
                        <?php $inputValue = $type === 'datetime-local' ? datetime_for_input($value ? (string)$value : null) : (string)$value; ?>
                        <input
                            type="<?= h($type) ?>"
                            name="<?= h($name) ?>"
                            value="<?= h($inputValue) ?>"
                            <?= isset($field['step']) ? 'step="' . h($field['step']) . '"' : '' ?>
                            <?= isset($field['min']) ? 'min="' . h((string)$field['min']) . '"' : '' ?>
                            <?= !empty($field['readonly']) ? 'readonly' : '' ?>
                        >
                    <?php endif; ?>
                </label>
            <?php endforeach; ?>
            <?php if ($moduleKey === 'broadcasts'): ?>
                <section class="field wide broadcast-preview" id="broadcast-preview">
                    <span>Предварительный просмотр</span>
                    <strong id="broadcast-preview-title"><?= h((string)($editRow['title'] ?? 'Заголовок рассылки')) ?></strong>
                    <p id="broadcast-preview-text"><?= nl2br(h((string)($editRow['message_text'] ?? 'Текст сообщения'))) ?></p>
                    <div id="broadcast-preview-media"></div>
                </section>
                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        const form = document.querySelector('.crud-form');
                        const title = document.querySelector('#broadcast-preview-title');
                        const text = document.querySelector('#broadcast-preview-text');
                        const media = document.querySelector('#broadcast-preview-media');
                        if (!form || !title || !text || !media) return;
                        const update = () => {
                            title.textContent = form.elements.title?.value || 'Заголовок рассылки';
                            text.textContent = form.elements.message_text?.value || 'Текст сообщения';
                        };
                        form.elements.title?.addEventListener('input', update);
                        form.elements.message_text?.addEventListener('input', update);
                        ['image_path', 'video_path'].forEach((name) => {
                            form.elements[name]?.addEventListener('change', (event) => {
                                const file = event.target.files?.[0];
                                if (!file) return;
                                const url = URL.createObjectURL(file);
                                media.innerHTML = name === 'video_path'
                                    ? `<video controls src="${url}"></video>`
                                    : `<img src="${url}" alt="">`;
                            });
                        });
                    });
                </script>
            <?php endif; ?>
            <?php if (in_array($moduleKey, ['managers', 'resellers'], true) && $admin['role'] === 'superadmin'): ?>
                <fieldset class="field admin-access-group">
                    <legend><?= h(app_text('admin_access.title')) ?></legend>
                    <label class="field">
                        <span><?= h(app_text('admin_access.email')) ?></span>
                        <input type="email" name="admin_email" value="<?= h((string)($adminAccess['email'] ?? '')) ?>">
                    </label>
                    <label class="field">
                        <span><?= h(app_text('admin_access.password')) ?></span>
                        <input type="password" name="admin_password" autocomplete="new-password">
                    </label>
                    <?php if (!empty($adminAccess['id'])): ?>
                        <p class="cell-muted"><?= h(app_text('admin_access.password_hint')) ?></p>
                    <?php endif; ?>
                    <label class="checkbox-line">
                        <input type="checkbox" name="admin_is_active" value="1" <?= (int)($adminAccess['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                        <?= h(app_text('admin_access.active')) ?>
                    </label>
                </fieldset>
            <?php endif; ?>
            <div class="form-actions">
                <button type="submit"><?= h(app_text('auto.k_4864057d626a')) ?></button>
                <a class="button secondary-button" href="crud.php?module=<?= h($moduleKey) ?>"><?= h(app_text('auto.k_0ec753be8df9')) ?></a>
            </div>
        </form>
    </section>
    <?php if ($moduleKey === 'tests' && $action === 'edit' && $editRow): ?>
        <?= render_test_builder((int)$editRow['id'], $admin) ?>
    <?php endif; ?>
    <?php if ($moduleKey === 'users' && $action === 'edit' && $editRow): ?>
        <?php $consentStatus = client_onboarding_status($editRow); ?>
        <section class="panel form-panel">
            <h2>Анкета и согласия</h2>
            <p><strong>Этап:</strong> <?= h(user_client_stage_label($editRow)) ?></p>
            <p><strong>Анкета:</strong> <?= $consentStatus['profile_complete'] ? 'заполнена' : 'не завершена' ?></p>
            <p><strong>Обязательные согласия:</strong> <?= $consentStatus['missing_consents'] ? 'не подтверждены полностью' : 'подтверждены' ?></p>
            <p><strong>Информационные рассылки:</strong> <?= $consentStatus['marketing_consent'] ? 'разрешены' : 'не разрешены' ?></p>
            <a
                class="button secondary-button"
                href="results.php?user_id=<?= (int)$editRow['id'] ?>"
                data-admin-modal-url="results.php?user_id=<?= (int)$editRow['id'] ?>&modal=1"
            >Результаты чек-апов</a>
        </section>

        <section class="panel form-panel">
            <h2><?= h(app_text('user_platforms.title')) ?></h2>
            <?php $accounts = user_platform_accounts((int)$editRow['id']); ?>
            <?php if ($accounts): ?>
                <table class="data-table">
                    <thead>
                    <tr>
                        <th><?= h(app_text('auto.k_89009febe5c6')) ?></th>
                        <th>ID</th>
                        <th><?= h(app_text('user_platforms.profile')) ?></th>
                        <th>Username</th>
                        <th><?= h(app_text('user_platforms.created')) ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($accounts as $account): ?>
                        <tr>
                            <td><?= render_platform_badge((string)$account['platform']) ?></td>
                            <td><?= h((string)$account['platform_user_id']) ?></td>
                            <td><?= h(crud_cell_value('platform_accounts', 'platform_profile', $account)) ?></td>
                            <td><?= h((string)($account['username'] ?? '')) ?></td>
                            <td><?= h((string)$account['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state"><?= h(app_text('user_platforms.empty')) ?></div>
            <?php endif; ?>
        </section>

        <section class="panel form-panel">
            <h2><?= h(app_text('user_merge.title')) ?></h2>
            <p class="cell-muted"><?= h(app_text('user_merge.description')) ?></p>
            <form method="post" class="crud-form user-merge-form" data-user-merge-form onsubmit="return this.querySelector('[data-merge-user-id]').value !== '' &amp;&amp; confirm(<?= json_encode(app_text('user_merge.confirm'), JSON_UNESCAPED_UNICODE) ?>);">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="merge_user">
                <input type="hidden" name="id" value="<?= h((string)$editRow['id']) ?>">
                <div
                    class="merge-search"
                    data-user-merge-search
                    data-search-url="crud.php?module=users&amp;action=merge_search&amp;id=<?= (int)$editRow['id'] ?>"
                    data-loading="<?= h(app_text('user_merge.loading_suggestions')) ?>"
                    data-empty="<?= h(app_text('user_merge.empty_suggestions')) ?>"
                    data-selected="<?= h(app_text('user_merge.selected_user')) ?>"
                    data-choose-first="<?= h(app_text('user_merge.choose_first')) ?>"
                >
                    <input type="hidden" name="source_user_id" data-merge-user-id>
                    <label class="field">
                        <span><?= h(app_text('user_merge.source_user')) ?></span>
                        <input
                            type="search"
                            autocomplete="off"
                            placeholder="<?= h(app_text('user_merge.search_placeholder')) ?>"
                            data-merge-search-input
                        >
                    </label>
                    <div class="merge-selected" data-merge-selected hidden></div>
                    <div class="merge-suggestions" data-merge-suggestions>
                        <div class="empty-state"><?= h(app_text('user_merge.loading_suggestions')) ?></div>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="danger-button" data-merge-submit disabled><?= h(app_text('user_merge.submit')) ?></button>
                </div>
            </form>
        </section>
    <?php endif; ?>
    <?php if ($moduleKey === 'leads' && $action === 'edit' && $editRow): ?>
        <section class="panel form-panel">
            <h2><?= h(app_text('auto.k_e33268c4b97d')) ?></h2>
            <form method="post" class="crud-form" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="send_lead_response">
                <input type="hidden" name="id" value="<?= h((string)$editRow['id']) ?>">

                <label class="field">
                    <span><?= h(app_text('auto.k_a76a99a18c25')) ?></span>
                    <textarea name="response_text" rows="4" placeholder="<?= h(app_text('auto.response_placeholder')) ?>"></textarea>
                </label>

                <label class="field">
                    <span><?= h(app_text('auto.k_19114f713f60')) ?></span>
                    <select name="response_content_id">
                        <option value=""><?= h(app_text('auto.k_92250813ceb7')) ?></option>
                        <?php foreach (safe_select_options('content_posts', $admin, $errors) as $option): ?>
                            <option value="<?= (int)$option['id'] ?>">#<?= (int)$option['id'] ?> <?= h($option['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="field">
                    <span><?= h(app_text('auto.k_3e644b83e4f3')) ?></span>
                    <select name="response_test_id">
                        <option value=""><?= h(app_text('auto.k_92250813ceb7')) ?></option>
                        <?php foreach (safe_select_options('tests', $admin, $errors) as $option): ?>
                            <option value="<?= (int)$option['id'] ?>">#<?= (int)$option['id'] ?> <?= h($option['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="field">
                    <span><?= h(app_text('auto.k_4012dea6eccf')) ?></span>
                    <input type="file" name="response_attachments[]" accept="image/*,application/pdf,video/mp4" multiple>
                </label>

                <label class="field">
                    <span><?= h(app_text('auto.k_e6877b1b589a')) ?></span>
                    <input type="url" name="response_external_url" placeholder="https://...">
                </label>

                <div class="form-actions">
                    <button type="submit"><?= h(app_text('auto.k_18523c1df9fa')) ?></button>
                </div>
            </form>
        </section>

        <section class="panel">
            <h2><?= h(app_text('auto.k_238615f19976')) ?></h2>
            <?php
            try {
                $responses = lead_response_history((int)$editRow['id']);
            } catch (Throwable $e) {
                $responses = [];
                echo app_text('auto.k_8646540328ff') . h($e->getMessage()) . '</div>';
            }
            ?>
            <?php if ($responses): ?>
                <div class="lead-response-timeline">
                    <?php foreach ($responses as $response): ?>
                        <?php
                        $attachments = lead_response_attachment_paths($response['attachment_path'] ?? null);
                        $status = status_label($response['status'] ?? 'pending');
                        $contentUrl = !empty($response['content_post_id']) ? 'crud.php?module=content&action=edit&id=' . (int)$response['content_post_id'] : '#';
                        $testUrl = !empty($response['test_id']) ? 'crud.php?module=tests&action=edit&id=' . (int)$response['test_id'] : '#';
                        ?>
                        <article class="lead-response-card">
                            <div class="lead-response-head">
                                <div>
                                    <strong><?= h(
                                        ($response['response_source'] ?? 'admin') === 'telegram'
                                            ? 'Ответ из Telegram'
                                            : ($response['admin_name'] ?? app_text('auto.k_1b93795b9768'))
                                    ) ?></strong>
                                    <span><?= h($response['created_at']) ?></span>
                                </div>
                                <span class="<?= h(status_badge_class($status)) ?>"><?= h($status) ?></span>
                            </div>

                            <?php if (trim((string)($response['message_text'] ?? '')) !== ''): ?>
                                <div class="lead-response-message"><?= nl2br(h($response['message_text'])) ?></div>
                            <?php endif; ?>

                            <?php if (($response['content_title'] ?? '') || ($response['test_title'] ?? '') || $attachments || ($response['external_url'] ?? '')): ?>
                                <div class="lead-response-resources">
                                    <?php if ($response['content_title'] ?? ''): ?>
                                        <a href="<?= h($contentUrl) ?>">
                                            <?= h(app_text('lead_response.open_material')) ?>: <?= h($response['content_title']) ?>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($response['test_title'] ?? ''): ?>
                                        <a href="<?= h($testUrl) ?>">
                                            <?= h(app_text('lead_response.pass_test')) ?>: <?= h($response['test_title']) ?>
                                        </a>
                                    <?php endif; ?>
                                    <?php foreach ($attachments as $fileIndex => $attachmentPath): ?>
                                        <a href="<?= h($attachmentPath) ?>" target="_blank" rel="noopener">
                                            <?= h(app_text('lead_response.lead_file_numbered', [
                                                'index' => $fileIndex + 1,
                                                'total' => count($attachments),
                                            ])) ?>
                                        </a>
                                    <?php endforeach; ?>
                                    <?php if ($response['external_url'] ?? ''): ?>
                                        <a href="<?= h($response['external_url']) ?>" target="_blank" rel="noopener">
                                            <?= h(app_text('lead_response.open_link')) ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($response['error_message'] ?? ''): ?>
                                <div class="alert error"><?= h($response['error_message']) ?></div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state"><?= h(app_text('auto.k_06fe678de6fe')) ?></div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
<?php endif; ?>
<?php $showList = !($moduleKey === 'users' && $action === 'edit'); ?>
<?php if ($showList): ?>
    <?php if ($moduleKey === 'users'): ?>
        <section class="panel">
            <form method="get" class="filters">
                <input type="hidden" name="module" value="users">
                <label>
                    <span>Записи</span>
                    <?php $userScope = users_scope_filter(); ?>
                    <select name="user_scope">
                        <option value="clients" <?= $userScope === 'clients' ? 'selected' : '' ?>>Клиенты</option>
                        <option value="visitors" <?= $userScope === 'visitors' ? 'selected' : '' ?>>Без консультанта</option>
                        <option value="all" <?= $userScope === 'all' ? 'selected' : '' ?>>Все записи</option>
                    </select>
                </label>
                <label>
                    <span>Этап</span>
                    <select name="client_stage">
                        <option value="">Все этапы</option>
                        <?php foreach (client_stage_labels() as $value => $label): ?>
                            <option value="<?= h($value) ?>" <?= ($_GET['client_stage'] ?? '') === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Чек-ап</span>
                    <select name="checkup">
                        <option value="">Любой</option>
                        <option value="not_started" <?= ($_GET['checkup'] ?? '') === 'not_started' ? 'selected' : '' ?>>Не начат</option>
                        <option value="started" <?= ($_GET['checkup'] ?? '') === 'started' ? 'selected' : '' ?>>Начат</option>
                        <option value="completed" <?= ($_GET['checkup'] ?? '') === 'completed' ? 'selected' : '' ?>>Завершён</option>
                    </select>
                </label>
                <label>
                    <span>Активность</span>
                    <select name="activity">
                        <option value="">Любая</option>
                        <option value="active_7" <?= ($_GET['activity'] ?? '') === 'active_7' ? 'selected' : '' ?>>Был активен за 7 дней</option>
                        <option value="inactive_14" <?= ($_GET['activity'] ?? '') === 'inactive_14' ? 'selected' : '' ?>>Неактивен 14 дней</option>
                    </select>
                </label>
                <button type="submit">Применить</button>
                <a class="button secondary-button" href="crud.php?module=users">Сбросить</a>
            </form>
        </section>
    <?php endif; ?>
    <section class="panel">
        <?= $listHtml ?>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/../app/views/layouts/footer.php'; ?>
