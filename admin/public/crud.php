<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/permissions.php';
require_once __DIR__ . '/../app/core/crud_views.php';
require_once __DIR__ . '/../app/core/lead_responses.php';
require_once __DIR__ . '/../app/core/test_admin.php';

$admin = require_auth();

$modules = [
    'resellers' => [
        'title' => app_text('auto.k_32cea47742bf'),
        'table' => 'resellers',
        'columns' => ['id', 'name', 'email', 'phone', 'referral_code', 'is_active'],
        'fields' => [
            'name' => ['label' => app_text('auto.k_3de49828e86a'), 'required' => true],
            'email' => ['label' => 'Email', 'type' => 'email'],
            'phone' => ['label' => app_text('auto.k_87ec4b495b56')],
            'referral_code' => ['label' => app_text('auto.k_a9d3a61b02f2'), 'required' => true],
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
        'title' => app_text('auto.k_0f0b8f55edcc'),
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
            'phone' => ['label' => app_text('auto.k_87ec4b495b56')],
            'email' => ['label' => 'Email', 'type' => 'email'],
            'referral_code_used' => ['label' => app_text('auto.k_23f8d055a5d6')],
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
        'columns' => ['id', 'end_user_id', 'manager_id', 'reseller_id', 'product_id', 'source_platform', 'status', 'created_at'],
        'fields' => [
            'end_user_id' => ['label' => app_text('auto.k_51aff1853949'), 'type' => 'select', 'source' => 'end_users', 'required' => true],
            'manager_id' => ['label' => app_text('auto.k_8d98911527e4'), 'type' => 'select', 'source' => 'managers', 'nullable' => true],
            'reseller_id' => ['label' => app_text('auto.k_86469fea3a4a'), 'type' => 'select', 'source' => 'resellers', 'nullable' => true],
            'product_id' => ['label' => app_text('auto.k_82a9ca014bb8'), 'type' => 'select', 'source' => 'products', 'nullable' => true],
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
        'columns' => ['id', 'title', 'category_id', 'is_active', 'sort_order'],
        'fields' => [
            'title' => ['label' => app_text('auto.k_3de49828e86a'), 'required' => true],
            'description' => ['label' => app_text('auto.k_f5441f6aee76'), 'type' => 'textarea'],
            'category_id' => ['label' => app_text('auto.k_19c85838e63f'), 'type' => 'select', 'source' => 'product_categories', 'nullable' => true],
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
            'message_text' => ['label' => app_text('auto.k_1ba376a71bcf'), 'type' => 'textarea', 'required' => true],
            'image_path' => ['label' => app_text('auto.k_56a1fd52891d'), 'type' => 'file', 'accept' => 'image/*'],
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
        'columns' => ['id', 'title', 'status', 'publish_at', 'created_by'],
        'fields' => [
            'content_type' => ['label' => app_text('auto.k_ef19578bced0'), 'type' => 'select', 'options' => ['article', 'image', 'pdf', 'video', 'link'], 'required' => true],
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
        'columns' => ['id', 'owner_type', 'owner_id', 'platform', 'title', 'external_id', 'is_active'],
        'fields' => [
            'owner_type' => ['label' => app_text('integrations.owner_type'), 'type' => 'select', 'options' => ['reseller', 'manager'], 'required' => true],
            'owner_id' => ['label' => app_text('integrations.owner_id'), 'type' => 'number', 'required' => true],
            'platform' => ['label' => app_text('auto.k_89009febe5c6'), 'type' => 'select', 'options' => ['VK', 'OK', 'telegram', 'MAX'], 'required' => true],
            'title' => ['label' => app_text('auto.k_3de49828e86a'), 'required' => true],
            'external_id' => ['label' => app_text('integrations.external_id')],
            'access_token' => ['label' => app_text('integrations.access_token'), 'type' => 'textarea'],
            'is_active' => ['label' => app_text('auto.k_667904ef22a4'), 'type' => 'checkbox', 'default' => 1],
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
        return owner_scope_condition($admin);
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
    return ['categories', 'products', 'tests', 'content', 'broadcasts'];
}

function owner_scope_condition(array $admin, string $alias = ''): array
{
    $prefix = $alias !== '' ? $alias . '.' : '';
    if ($admin['role'] === 'superadmin') {
        return ['', []];
    }

    if ($admin['role'] === 'reseller') {
        return [
            'WHERE (' . $prefix . 'owner_type IS NULL OR (' . $prefix . 'owner_type = "reseller" AND ' . $prefix . 'owner_id = :owner_reseller_id) OR (' . $prefix . 'owner_type = "manager" AND ' . $prefix . 'owner_id IN (SELECT id FROM managers WHERE reseller_id = :owner_reseller_id_sub)))',
            ['owner_reseller_id' => $admin['reseller_id'], 'owner_reseller_id_sub' => $admin['reseller_id']],
        ];
    }

    return [
        'WHERE (' . $prefix . 'owner_type IS NULL OR (' . $prefix . 'owner_type = "manager" AND ' . $prefix . 'owner_id = :owner_manager_id))',
        ['owner_manager_id' => $admin['manager_id']],
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
    if ($name === '') {
        $name = trim((string)($row['username'] ?? ''));
    }
    if ($name === '') {
        $name = '#' . (int)$row['id'];
    }

    $platform = trim((string)($row['platform'] ?? ''));
    $platformUserId = trim((string)($row['platform_user_id'] ?? ''));
    return '#' . (int)$row['id'] . ' ' . $name . ($platform ? ' (' . platform_label($platform) . ' ' . $platformUserId . ')' : '');
}

function merge_user_options(int $targetUserId, array $admin): array
{
    [$where, $params] = scoped_where_with_alias(scope_where_for_users($admin), 'eu');
    $where = $where
        ? $where . ' AND eu.id <> :target_user_id AND eu.merged_into_user_id IS NULL'
        : 'WHERE eu.id <> :target_user_id AND eu.merged_into_user_id IS NULL';
    $params['target_user_id'] = $targetUserId;

    $stmt = db()->prepare(
        "SELECT eu.id, CONCAT_WS(' ', NULLIF(eu.first_name, ''), NULLIF(eu.last_name, '')) AS full_name,
                eu.username, eu.platform, eu.platform_user_id
         FROM end_users eu
         $where
         ORDER BY eu.id DESC
         LIMIT 300"
    );
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function user_platform_accounts(int $endUserId): array
{
    $stmt = db()->prepare(
        'SELECT platform, platform_user_id, username, created_at
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
        $targetStmt = $pdo->prepare('SELECT id FROM end_users WHERE id = :id AND merged_into_user_id IS NULL FOR UPDATE');
        $targetStmt->execute(['id' => $targetUserId]);
        $target = $targetStmt->fetch();

        $sourceStmt = $pdo->prepare('SELECT id FROM end_users WHERE id = :id AND merged_into_user_id IS NULL FOR UPDATE');
        $sourceStmt->execute(['id' => $sourceUserId]);
        $source = $sourceStmt->fetch();

        if (!$target || !$source) {
            throw new RuntimeException('Один из пользователей уже объединён.');
        }

        $updates = [
            'platform_accounts' => 'end_user_id',
            'leads' => 'end_user_id',
            'user_test_sessions' => 'end_user_id',
            'recommendations' => 'end_user_id',
            'broadcast_logs' => 'end_user_id',
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
        [$where, $params] = scope_where_for_module($moduleForSource, $admin);
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
        $type = $field['type'] ?? 'text';
        if ($type === 'file') {
            $current = trim((string)($_POST[$name . '_current'] ?? ''));
            $payload[$name] = $current !== '' ? $current : null;
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
    if ($moduleKey === 'users' && !empty($payload['manager_id']) && !empty($payload['reseller_id'])) {
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

    return $errors;
}

function apply_role_defaults(string $moduleKey, array $payload, array $admin): array
{
    if ($moduleKey === 'users' && !empty($payload['manager_id'])) {
        $stmt = db()->prepare('SELECT reseller_id FROM managers WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => (int)$payload['manager_id']]);
        $manager = $stmt->fetch();
        if ($manager) {
            $payload['reseller_id'] = $manager['reseller_id'] !== null ? (int)$manager['reseller_id'] : null;
        }
    }

    if ($admin['role'] === 'reseller' && in_array($moduleKey, ['managers', 'users', 'leads'], true)) {
        $payload['reseller_id'] = $admin['reseller_id'];
    }
    if ($admin['role'] === 'manager' && in_array($moduleKey, ['users', 'leads'], true)) {
        $payload['manager_id'] = $admin['manager_id'];
        $payload['reseller_id'] = $admin['reseller_id'];
    }
    if (in_array($moduleKey, owned_modules(), true) && empty($payload['owner_type']) && $admin['role'] !== 'superadmin') {
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
    }
    if ($moduleKey === 'content') {
        $payload['created_by'] = $admin['id'];
    }

    return $payload;
}

function default_manager_platform_options(): array
{
    return ['telegram', 'VK', 'OK', 'MAX', 'web'];
}

function default_platforms_for_manager(int $managerId): array
{
    if ($managerId <= 0) {
        return [];
    }

    $stmt = db()->prepare('SELECT platform FROM default_platform_managers WHERE manager_id = :manager_id AND is_active = 1');
    $stmt->execute(['manager_id' => $managerId]);
    return array_map(static fn($row) => (string)$row['platform'], $stmt->fetchAll());
}

function save_default_manager_platforms(int $managerId, array $platforms): void
{
    $allowed = default_manager_platform_options();
    $platforms = array_values(array_intersect($allowed, array_map('normalize_platform', $platforms)));

    $delete = db()->prepare('DELETE FROM default_platform_managers WHERE manager_id = :manager_id');
    $delete->execute(['manager_id' => $managerId]);

    if (!$platforms) {
        return;
    }

    $stmt = db()->prepare(
        'INSERT INTO default_platform_managers (platform, manager_id, is_active)
         VALUES (:platform, :manager_id, 1)
         ON DUPLICATE KEY UPDATE manager_id = VALUES(manager_id), is_active = 1'
    );
    foreach ($platforms as $platform) {
        $stmt->execute([
            'platform' => $platform,
            'manager_id' => $managerId,
        ]);
    }
}

function save_record(string $moduleKey, array $module, array $payload, ?int $id, array $admin): int
{
    $payload = apply_role_defaults($moduleKey, $payload, $admin);
    $columns = array_keys($payload);

    if ($id) {
        $before = null;
        if ($moduleKey === 'users') {
            $beforeStmt = db()->prepare('SELECT reseller_id, manager_id FROM end_users WHERE id = :id LIMIT 1');
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
                log_activity('admin', (int)$admin['id'], 'transfer_end_user', 'end_users', $id, [
                    'old_reseller_id' => $oldResellerId,
                    'old_manager_id' => $oldManagerId,
                    'new_reseller_id' => $newResellerId,
                    'new_manager_id' => $newManagerId,
                ]);
            }
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
            redirect('crud.php?module=leads&action=edit&id=' . $postId . '&success=response_sent');
        }
        $action = 'edit';
        $id = $postId;
    }

    if ($moduleKey === 'tests' && $postId && handle_test_builder_action($postAction, $postId, $admin, $errors)) {
        if (!scoped_row_exists($moduleKey, $module, $postId, $admin)) {
            http_response_code(404);
            exit('Record not found');
        }
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
            $errors[] = 'Не удалось объединить пользователей: ' . $e->getMessage();
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
            $stmt = db()->prepare("DELETE FROM {$module['table']} WHERE id = :id");
            $stmt->execute(['id' => $postId]);
            log_activity('admin', (int)$admin['id'], 'delete_' . $module['table'], $module['table'], $postId);
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

    $payload = collect_payload($formFields);
    $payload = apply_file_uploads($moduleKey, $formFields, $payload, $errors);
    $errors = array_merge($errors, validate_payload($formFields, $payload));
    $errors = array_merge($errors, validate_scope_payload($moduleKey, $payload, $admin));
    if (!$errors) {
        try {
            $savedId = save_record($moduleKey, $module, $payload, $postId, $admin);
            if ($moduleKey === 'managers' && $admin['role'] === 'superadmin') {
                save_default_manager_platforms($savedId, $_POST['default_platforms'] ?? []);
            }
            redirect('crud.php?module=' . urlencode($moduleKey) . '&success=saved');
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

$managerDefaultPlatforms = [];
if ($moduleKey === 'managers' && $admin['role'] === 'superadmin' && ($action === 'create' || $action === 'edit')) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $managerDefaultPlatforms = array_map('normalize_platform', $_POST['default_platforms'] ?? []);
    } elseif (!empty($editRow['id'])) {
        $managerDefaultPlatforms = default_platforms_for_manager((int)$editRow['id']);
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
    <div class="notice success"><?= h(app_text('auto.k_0184f257cbfc')) ?></div>
<?php elseif ($success === 'merged'): ?>
    <div class="notice success">Пользователи объединены.</div>
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
                        <textarea name="<?= h($name) ?>" rows="4"><?= h((string)$value) ?></textarea>
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
                            <a class="file-link" href="<?= h((string)$value) ?>" target="_blank" rel="noopener"><?= h(app_text('auto.k_ffa20070c6e2')) ?></a>
                            <?php if (($field['accept'] ?? '') === 'image/*'): ?>
                                <img class="file-preview" src="<?= h((string)$value) ?>" alt="">
                            <?php endif; ?>
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
                        >
                    <?php endif; ?>
                </label>
            <?php endforeach; ?>
            <?php if ($moduleKey === 'managers' && $admin['role'] === 'superadmin'): ?>
                <fieldset class="field checkbox-group">
                    <legend><?= h(app_text('auto.k_89009febe5c6')) ?></legend>
                    <?php foreach (default_manager_platform_options() as $platformOption): ?>
                        <label>
                            <input
                                type="checkbox"
                                name="default_platforms[]"
                                value="<?= h($platformOption) ?>"
                                <?= in_array($platformOption, $managerDefaultPlatforms, true) ? 'checked' : '' ?>
                            >
                            <?= h(platform_label($platformOption)) ?>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
            <?php endif; ?>
            <div class="form-actions">
                <button type="submit"><?= h(app_text('auto.k_4864057d626a')) ?></button>
                <a class="button secondary-button" href="crud.php?module=<?= h($moduleKey) ?>"><?= h(app_text('auto.k_0ec753be8df9')) ?></a>
            </div>
        </form>
    </section>
    <?php if ($moduleKey === 'tests' && $action === 'edit' && $editRow): ?>
        <?= render_test_builder((int)$editRow['id']) ?>
    <?php endif; ?>
    <?php if ($moduleKey === 'users' && $action === 'edit' && $editRow): ?>
        <section class="panel form-panel">
            <h2>Платформы пользователя</h2>
            <?php $accounts = user_platform_accounts((int)$editRow['id']); ?>
            <?php if ($accounts): ?>
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Платформа</th>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Создан</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($accounts as $account): ?>
                        <tr>
                            <td><?= render_platform_badge((string)$account['platform']) ?></td>
                            <td><?= h((string)$account['platform_user_id']) ?></td>
                            <td><?= h((string)($account['username'] ?? '')) ?></td>
                            <td><?= h((string)$account['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">Подключённых платформ пока нет.</div>
            <?php endif; ?>
        </section>

        <section class="panel form-panel">
            <h2>Объединить пользователей</h2>
            <p class="cell-muted">Выберите второго пользователя. Его платформы, заявки, тесты и рекомендации будут перенесены к текущему пользователю.</p>
            <form method="post" class="crud-form" onsubmit="return confirm('Объединить выбранного пользователя с текущим?');">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="merge_user">
                <input type="hidden" name="id" value="<?= h((string)$editRow['id']) ?>">
                <label class="field">
                    <span>Второй пользователь</span>
                    <select name="source_user_id" required>
                        <option value="">Не выбран</option>
                        <?php foreach (merge_user_options((int)$editRow['id'], $admin) as $option): ?>
                            <option value="<?= (int)$option['id'] ?>"><?= h(user_display_label($option)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="form-actions">
                    <button type="submit" class="danger-button">Объединить</button>
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
                <table class="data-table">
                    <thead>
                    <tr>
                        <th><?= h(app_text('auto.k_a5b49d2ebad2')) ?></th>
                        <th><?= h(app_text('auto.k_8d98911527e4')) ?></th>
                        <th><?= h(app_text('auto.k_e9d7bdd83831')) ?></th>
                        <th><?= h(app_text('auto.k_8963194b107f')) ?></th>
                        <th><?= h(app_text('auto.k_f7f293b5c58c')) ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($responses as $response): ?>
                        <tr>
                            <td><?= h($response['created_at']) ?></td>
                            <td><?= h($response['admin_name'] ?? '—') ?></td>
                            <td><?= nl2br(h($response['message_text'] ?? '')) ?></td>
                            <td>
                                <?= h($response['content_title'] ?? '') ?>
                                <?= $response['test_title'] ? '<br>' . h($response['test_title']) : '' ?>
                                <?php foreach (lead_response_attachment_paths($response['attachment_path'] ?? null) as $fileIndex => $attachmentPath): ?>
                                    <br><a href="<?= h($attachmentPath) ?>" target="_blank" rel="noopener"><?= h(app_text('auto.k_94b8df93b6ec')) ?><?= $fileIndex + 1 ?></a>
                                <?php endforeach; ?>
                            </td>
                            <td>
                                <?= h(status_label($response['status'] ?? 'pending')) ?>
                                <?= $response['error_message'] ? '<br>' . h($response['error_message']) : '' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state"><?= h(app_text('auto.k_06fe678de6fe')) ?></div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
<?php endif; ?>
<section class="panel">
    <?= $listHtml ?>
</section>
<section class="panel">
    <h2><?= h(app_text('auto.k_770fa6d360ac')) ?></h2>
    <div class="access-rules">
        <p><strong><?= h(app_text('auto.k_58b681fd4d17')) ?></strong><?= h(app_text('auto.k_15250cf3f350')) ?></p>
        <p><strong><?= h(app_text('auto.k_674627c4bad0')) ?></strong><?= h(app_text('auto.k_344db6faf528')) ?></p>
        <p><strong><?= h(app_text('auto.k_839732a73e8e')) ?></strong><?= h(app_text('auto.k_73dde519a420')) ?></p>
    </div>
</section>
<?php require __DIR__ . '/../app/views/layouts/footer.php'; ?>
