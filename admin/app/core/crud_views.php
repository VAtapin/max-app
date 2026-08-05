<?php

require_once __DIR__ . '/content_ownership.php';
require_once __DIR__ . '/table_ui.php';
require_once __DIR__ . '/subscription_plans.php';

function crud_create_enabled(string $moduleKey): bool
{
    return !in_array($moduleKey, ['users', 'platform_accounts', 'leads'], true);
}

function crud_delete_enabled(string $moduleKey): bool
{
    return !in_array($moduleKey, ['platform_accounts', 'leads'], true);
}

function crud_edit_enabled(string $moduleKey): bool
{
    return $moduleKey !== 'platform_accounts';
}

function crud_action_label(string $moduleKey): string
{
    return match ($moduleKey) {
        'users' => app_text('auto.k_8960ddc30e73'),
        'leads' => app_text('auto.k_eeae8354f40d'),
        default => app_text('auto.k_901beb5fcd38'),
    };
}

function crud_form_title(string $moduleKey, string $action): string
{
    if ($action === 'create') {
        return match ($moduleKey) {
            'resellers' => app_text('auto.k_7ec48194f4ef'),
            'managers' => app_text('auto.k_8b7415ecc1e9'),
            'categories' => app_text('auto.k_31426d435c63'),
            'products' => app_text('auto.k_ea41540b34c3'),
            'tests' => app_text('auto.k_74f8257e9b63'),
            'broadcasts' => app_text('auto.k_e822b9f8ad3a'),
            'content' => app_text('auto.k_f257071b2057'),
            default => app_text('auto.k_909a83238c9a'),
        };
    }

    return match ($moduleKey) {
        'users' => app_text('auto.k_02173687ed70'),
        'leads' => app_text('auto.k_f4dc966338c1'),
        'resellers' => app_text('auto.k_915c922fc34c'),
        'managers' => app_text('auto.k_d13578d48831'),
        'categories' => app_text('auto.k_00f82cbbb66d'),
        'products' => app_text('auto.k_f2e67b1e156f'),
        'tests' => app_text('auto.k_058863fd2c04'),
        'broadcasts' => app_text('auto.k_3a60e45259e0'),
        'content' => app_text('auto.k_1fc7d815c49f'),
        default => app_text('auto.k_e99ceeeb190e'),
    };
}

function crud_form_fields(string $moduleKey, array $fields): array
{
    if ($moduleKey === 'users') {
        return array_intersect_key($fields, array_flip([
            'reseller_id',
            'manager_id',
            'first_name',
            'last_name',
            'gender',
            'birth_date',
            'age_years',
            'city',
            'timezone',
            'phone',
            'email',
            'client_stage',
            'notifications_enabled',
            'status',
        ]));
    }

    if ($moduleKey === 'leads') {
        return array_intersect_key($fields, array_flip([
            'manager_id',
            'message',
            'status',
        ]));
    }

    return $fields;
}

function crud_display_columns(string $moduleKey): array
{
    return [
        'resellers' => [
            'id' => 'ID',
            'name' => app_text('auto.k_86469fea3a4a'),
            'parent_name' => 'Вышестоящий лидер',
            'contacts' => app_text('auto.k_dba0fcb2cbbb'),
            'referral_code' => app_text('auto.k_b162c37f62ea'),
            'team_capacity' => 'Ветка',
            'billing_summary' => 'Плательщик',
            'subscription_summary' => 'Подписка',
            'users_count' => app_text('auto.k_0f0b8f55edcc'),
            'state' => app_text('auto.k_f7f293b5c58c'),
        ],
        'managers' => [
            'id' => 'ID',
            'name' => app_text('auto.k_8d98911527e4'),
            'reseller_name' => app_text('auto.k_86469fea3a4a'),
            'contacts' => app_text('auto.k_dba0fcb2cbbb'),
            'referral_code' => app_text('auto.k_b162c37f62ea'),
            'users_count' => app_text('auto.k_0f0b8f55edcc'),
            'state' => app_text('auto.k_f7f293b5c58c'),
        ],
        'users' => [
            'id' => 'ID',
            'display_name' => app_text('auto.k_51aff1853949'),
            'platform_accounts_summary' => app_text('user_platforms.title'),
            'client_profile' => 'Анкета',
            'client_stage' => 'Этап',
            'checkup_status' => 'Чек-ап',
            'manager_name' => app_text('auto.k_8d98911527e4'),
            'last_activity_at' => 'Последняя активность',
        ],
        'platform_accounts' => [
            'id' => 'ID',
            'user_name' => app_text('auto.k_51aff1853949'),
            'platform_profile' => 'Профиль платформы',
            'platform_account' => app_text('auto.k_89009febe5c6'),
            'username' => 'Username',
            'created_at' => app_text('auto.k_33415c6ac49e'),
        ],
        'leads' => [
            'id' => 'ID',
            'lead_status' => app_text('auto.k_f7f293b5c58c'),
            'lead_summary' => app_text('auto.k_ca87acdc9c19'),
            'user_name' => app_text('auto.k_51aff1853949'),
            'product_title' => app_text('auto.k_82a9ca014bb8'),
            'response_summary' => app_text('auto.k_e9d7bdd83831'),
            'created_at' => app_text('auto.k_2ca3cb47e1d9'),
        ],
        'categories' => [
            'id' => 'ID',
            'title' => app_text('auto.k_19c85838e63f'),
            'owner_label' => 'Версия',
            'slug' => 'Slug',
            'products_count' => app_text('auto.k_c85756a1ae45'),
            'sort_order' => app_text('auto.k_c00d5a4cbda0'),
            'state' => app_text('auto.k_f7f293b5c58c'),
        ],
        'products' => [
            'id' => 'ID',
            'image_preview' => app_text('auto.k_fb8ffc7377b8'),
            'title' => app_text('auto.k_82a9ca014bb8'),
            'owner_label' => 'Версия',
            'category_title' => app_text('auto.k_19c85838e63f'),
            'media_summary' => app_text('auto.k_198be2a9a816'),
            'price' => app_text('auto.k_367e2792c179'),
            'sort_order' => app_text('auto.k_c00d5a4cbda0'),
            'state' => app_text('auto.k_f7f293b5c58c'),
        ],
        'tests' => [
            'id' => 'ID',
            'title' => app_text('auto.k_ec1868c5a7fb'),
            'owner_label' => 'Версия',
            'test_type' => 'Тип',
            'category_title' => app_text('auto.k_19c85838e63f'),
            'questions_count' => app_text('auto.k_beeac564c743'),
            'scales_count' => 'Шкалы',
            'sort_order' => app_text('auto.k_c00d5a4cbda0'),
            'state' => app_text('auto.k_f7f293b5c58c'),
        ],
        'broadcasts' => [
            'id' => 'ID',
            'title' => app_text('auto.k_38090ead89f2'),
            'owner_label' => app_text('integrations.owner'),
            'audience_type' => 'Получатели',
            'platform' => app_text('auto.k_89009febe5c6'),
            'target_type' => app_text('auto.k_e9476ab1820b'),
            'scheduled_at' => app_text('auto.k_725347e42525'),
            'status' => app_text('auto.k_f7f293b5c58c'),
        ],
        'content' => [
            'id' => 'ID',
            'image_preview' => app_text('auto.k_fb8ffc7377b8'),
            'title' => app_text('auto.k_19114f713f60'),
            'owner_label' => app_text('integrations.owner'),
            'content_type' => app_text('auto.k_d25691ca401e'),
            'section_type' => 'Раздел сайта',
            'category_title' => app_text('auto.k_19c85838e63f'),
            'media_summary' => app_text('auto.k_012475a7b6b0'),
            'status' => app_text('auto.k_f7f293b5c58c'),
            'publish_at' => app_text('auto.k_eb8ec7038ec2'),
        ],
        'integrations' => [
            'id' => 'ID',
            'title' => app_text('auto.k_3de49828e86a'),
            'owner_label' => app_text('integrations.owner'),
            'platform' => app_text('auto.k_89009febe5c6'),
            'external_id' => app_text('integrations.external_id'),
            'callback_last_event_at' => app_text('integrations.callback_last_event_at'),
            'state' => app_text('auto.k_f7f293b5c58c'),
        ],
        'legal_documents' => [
            'id' => 'ID',
            'document_type' => 'Тип',
            'title' => 'Название',
            'version' => 'Версия',
            'is_required' => 'Обязательный',
            'is_active' => 'Активен',
            'updated_at' => 'Обновлён',
        ],
    ][$moduleKey] ?? [];
}

function lead_filters_from_request(): array
{
    $allowedStatuses = ['new', 'contacted', 'interested', 'closed', 'lost'];
    $allowedPlatforms = ['telegram', 'VK', 'OK', 'MAX', 'web'];
    $allowedResponse = ['all', 'none', 'sent', 'pending', 'failed'];

    $status = $_GET['status'] ?? 'new';
    $platform = $_GET['platform'] ?? 'all';
    $response = $_GET['response'] ?? 'all';
    $page = max(1, (int)($_GET['page'] ?? 1));

    return [
        'status' => in_array($status, $allowedStatuses, true) ? $status : 'all',
        'platform' => in_array($platform, $allowedPlatforms, true) ? $platform : 'all',
        'response' => in_array($response, $allowedResponse, true) ? $response : 'all',
        'page' => $page,
        'per_page' => 25,
    ];
}

function append_lead_filter_sql(string $sql, array $filters, array &$params): string
{
    if (($filters['status'] ?? 'all') !== 'all') {
        $sql .= ' AND l.status = :lead_status_filter';
        $params['lead_status_filter'] = $filters['status'];
    }

    if (($filters['platform'] ?? 'all') !== 'all') {
        $sql .= ' AND l.source_platform = :lead_platform_filter';
        $params['lead_platform_filter'] = $filters['platform'];
    }

    $response = $filters['response'] ?? 'all';
    if ($response === 'none') {
        $sql .= ' AND lrc.response_count IS NULL';
    } elseif (in_array($response, ['sent', 'pending', 'failed'], true)) {
        $sql .= ' AND lr.status = :lead_response_filter';
        $params['lead_response_filter'] = $response;
    }

    return $sql;
}

function scoped_where_with_alias(array $scope, string $alias): array
{
    [$where, $params] = $scope;
    if (!$where) {
        return ['', $params];
    }

    $where = preg_replace_callback(
        '/(?<![.:`"\w])\b(reseller_id|manager_id)\b/',
        static fn(array $match): string => $alias . '.' . $match[1],
        $where
    ) ?? $where;

    return [$where, $params];
}

function users_scope_filter(): string
{
    $scope = (string)($_GET['user_scope'] ?? 'clients');
    return in_array($scope, ['clients', 'visitors', 'all'], true) ? $scope : 'clients';
}

function leader_subscription_status_label(?string $value): string
{
    return [
        'pending' => 'Ожидает оплаты',
        'active' => 'Активна',
        'expired' => 'Истекла',
        'suspended' => 'Приостановлена',
    ][(string)$value] ?? (string)$value;
}

function append_sql_condition(string $where, string $condition): string
{
    return $where !== '' ? $where . ' AND ' . $condition : 'WHERE ' . $condition;
}

function user_is_unassigned(array $row): bool
{
    return empty($row['reseller_id']) && empty($row['manager_id']);
}

function user_client_stage_label(array $row): string
{
    if (user_is_unassigned($row)) {
        return 'Ожидает реферальный код';
    }

    $stage = (string)($row['client_stage'] ?? 'new');
    return client_stage_labels()[$stage] ?? $stage;
}

function crud_list_query(string $moduleKey, array $module, array $admin): array
{
    if ($moduleKey === 'users') {
        [$where, $params] = scoped_where_with_alias(scope_where_for_users($admin), 'eu');
        $where = $where
            ? $where . ' AND eu.merged_into_user_id IS NULL'
            : 'WHERE eu.merged_into_user_id IS NULL';

        $userScope = users_scope_filter();
        if ($userScope === 'clients') {
            $where .= ' AND (eu.reseller_id IS NOT NULL OR eu.manager_id IS NOT NULL)';
        } elseif ($userScope === 'visitors') {
            $where .= ' AND eu.reseller_id IS NULL AND eu.manager_id IS NULL';
        }

        $stage = (string)($_GET['client_stage'] ?? '');
        if ($stage !== '' && isset(client_stage_labels()[$stage])) {
            $where .= ' AND eu.client_stage = :client_stage_filter';
            $params['client_stage_filter'] = $stage;
        }
        $activity = (string)($_GET['activity'] ?? '');
        if ($activity === 'active_7') {
            $where .= ' AND eu.last_activity_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
        } elseif ($activity === 'inactive_14') {
            $where .= ' AND (eu.last_activity_at IS NULL OR eu.last_activity_at < DATE_SUB(NOW(), INTERVAL 14 DAY))';
        }
        $checkup = (string)($_GET['checkup'] ?? '');
        if ($checkup === 'started') {
            $where .= ' AND EXISTS (SELECT 1 FROM user_test_sessions utsf WHERE utsf.end_user_id = eu.id AND utsf.completed_at IS NULL)';
        } elseif ($checkup === 'completed') {
            $where .= ' AND EXISTS (SELECT 1 FROM user_test_sessions utsf WHERE utsf.end_user_id = eu.id AND utsf.completed_at IS NOT NULL)';
        } elseif ($checkup === 'not_started') {
            $where .= ' AND NOT EXISTS (SELECT 1 FROM user_test_sessions utsf WHERE utsf.end_user_id = eu.id)';
        }

        return [
            "SELECT eu.id, CONCAT_WS(' ', NULLIF(eu.first_name, ''), NULLIF(eu.last_name, '')) AS full_name,
                    eu.username, eu.platform, eu.platform_user_id, eu.status, eu.created_at,
                    eu.reseller_id, eu.manager_id,
                    eu.gender, eu.birth_date, eu.age_years, eu.city, eu.client_stage,
                    eu.onboarding_completed_at, eu.last_activity_at,
                    r.name AS reseller_name, m.name AS manager_name,
                    (SELECT COUNT(*) FROM user_test_sessions uts WHERE uts.end_user_id = eu.id AND uts.completed_at IS NULL) AS draft_tests_count,
                    (SELECT COUNT(*) FROM user_test_sessions uts WHERE uts.end_user_id = eu.id AND uts.completed_at IS NOT NULL) AS completed_tests_count,
                    GROUP_CONCAT(CONCAT(pa.platform, ':', pa.platform_user_id) ORDER BY FIELD(pa.platform, 'telegram', 'VK', 'OK', 'MAX', 'web'), pa.id SEPARATOR '\n') AS platform_accounts_summary
             FROM end_users eu
             LEFT JOIN resellers r ON r.id = eu.reseller_id
             LEFT JOIN managers m ON m.id = eu.manager_id
             LEFT JOIN platform_accounts pa ON pa.end_user_id = eu.id
             $where
             GROUP BY eu.id
             ORDER BY eu.id DESC
             LIMIT 100",
            $params,
        ];
    }

    if ($moduleKey === 'platform_accounts') {
        [$where, $params] = scope_where_for_module($moduleKey, $admin);
        return [
            "SELECT pa.id, pa.platform, pa.platform_user_id, pa.username, pa.first_name, pa.last_name, pa.display_name, pa.created_at,
                    CONCAT_WS(' ', NULLIF(eu.first_name, ''), NULLIF(eu.last_name, '')) AS full_name,
                    eu.username AS user_username
             FROM platform_accounts pa
             LEFT JOIN end_users eu ON eu.id = pa.end_user_id
             $where
             ORDER BY pa.id DESC
             LIMIT 100",
            $params,
        ];
    }

    if ($moduleKey === 'leads') {
        [$where, $params] = scoped_where_with_alias(scope_where_for_leads($admin), 'l');
        $filters = lead_filters_from_request();
        $offset = ($filters['page'] - 1) * $filters['per_page'];
        $baseWhere = $where ?: 'WHERE 1=1';
        $baseWhere = append_lead_filter_sql($baseWhere, $filters, $params);
        return [
            "SELECT l.id, l.end_user_id, l.status, l.source_platform, l.request_type, l.message, l.attachments_json, l.created_at,
                    CONCAT_WS(' ', NULLIF(eu.first_name, ''), NULLIF(eu.last_name, '')) AS full_name,
                    eu.username AS user_username,
                    eu.platform AS user_platform,
                    eu.platform_user_id AS user_platform_user_id,
                    p.title AS product_title, m.name AS manager_name, r.name AS reseller_name,
                    lrc.response_count, lr.status AS last_response_status, lr.created_at AS last_response_at,
                    lr.message_text AS last_response_text
             FROM leads l
             LEFT JOIN end_users eu ON eu.id = l.end_user_id
             LEFT JOIN products p ON p.id = l.product_id
             LEFT JOIN managers m ON m.id = l.manager_id
             LEFT JOIN resellers r ON r.id = l.reseller_id
             LEFT JOIN (
                SELECT lead_id, COUNT(*) AS response_count, MAX(id) AS last_response_id
                FROM lead_responses
                GROUP BY lead_id
             ) lrc ON lrc.lead_id = l.id
             LEFT JOIN lead_responses lr ON lr.id = lrc.last_response_id
             $baseWhere
             ORDER BY l.id DESC
             LIMIT {$filters['per_page']} OFFSET $offset",
            $params,
        ];
    }

    if ($moduleKey === 'resellers') {
        [$where, $params] = scoped_where_with_alias(scope_where_for_module($moduleKey, $admin), 'r');
        if ($where) {
            $where = str_replace('WHERE id', 'WHERE r.id', $where);
            $where = str_replace('AND id', 'AND r.id', $where);
            $where = str_replace('OR id', 'OR r.id', $where);
        }
        return [
            "SELECT r.id, r.parent_reseller_id, parent.name AS parent_name,
                    r.name, r.email, r.phone, r.billing_name, r.billing_inn, r.billing_email,
                    r.billing_comment, r.referral_code, r.subscription_plan_id,
                    COALESCE(sp.direct_consultant_limit, r.manager_limit) AS manager_limit,
                    COALESCE(sp.direct_leader_limit, r.direct_leader_limit) AS direct_leader_limit,
                    COALESCE(sp.branch_leader_limit, r.branch_leader_limit) AS branch_leader_limit,
                    COALESCE(sp.direct_consultant_limit, r.direct_manager_limit) AS direct_manager_limit,
                    COALESCE(sp.branch_consultant_limit, r.branch_manager_limit) AS branch_manager_limit,
                    COALESCE(sp.per_child_consultant_limit, r.per_child_manager_limit) AS per_child_manager_limit,
                    sp.title AS subscription_plan_title,
                    sp.slug AS subscription_plan_slug,
                    IF(r.is_active = 1, 'active', 'inactive') AS state,
                    (SELECT COUNT(*) FROM managers m WHERE m.reseller_id = r.id) AS managers_count,
                    (SELECT COUNT(*) FROM managers m WHERE m.reseller_id = r.id AND m.is_active = 1) AS active_managers_count,
                    (SELECT COUNT(*) FROM resellers child WHERE child.parent_reseller_id = r.id AND child.is_active = 1) AS active_direct_leaders_count,
                    ls.status AS subscription_status,
                    ls.starts_at AS subscription_starts_at,
                    ls.ends_at AS subscription_ends_at,
                    ls.consultant_limit AS subscription_consultant_limit,
                    ls.leader_limit AS subscription_leader_limit,
                    ls.price_per_consultant,
                    ls.price_per_leader,
                    ls.amount_due,
                    ls.leader_amount_due,
                    (SELECT COUNT(*) FROM end_users eu WHERE eu.reseller_id = r.id) AS users_count
             FROM resellers r
             LEFT JOIN resellers parent ON parent.id = r.parent_reseller_id
             LEFT JOIN subscription_plans sp ON sp.id = r.subscription_plan_id
             LEFT JOIN (
                SELECT s.*
                FROM leader_subscriptions s
                INNER JOIN (
                    SELECT reseller_id, MAX(id) AS latest_id
                    FROM leader_subscriptions
                    GROUP BY reseller_id
                ) latest ON latest.latest_id = s.id
             ) ls ON ls.reseller_id = r.id
             $where
             ORDER BY r.id DESC
             LIMIT 100",
            $params,
        ];
    }

    if ($moduleKey === 'managers') {
        [$where, $params] = scoped_where_with_alias(scope_where_for_module($moduleKey, $admin), 'm');
        return [
            "SELECT m.id, m.name, m.email, m.phone, m.referral_code,
                    IF(m.is_active = 1, 'active', 'inactive') AS state,
                    r.name AS reseller_name,
                    COUNT(DISTINCT eu.id) AS users_count
             FROM managers m
             LEFT JOIN resellers r ON r.id = m.reseller_id
             LEFT JOIN end_users eu ON eu.manager_id = m.id
             $where
             GROUP BY m.id
             ORDER BY m.id DESC
             LIMIT 100",
            $params,
        ];
    }

    if ($moduleKey === 'categories') {
        [$where, $params] = owner_scope_condition($admin, 'c', 'categories');
        return [
            "SELECT c.id, c.title, c.slug, c.owner_type, c.owner_id, c.source_category_id, c.sort_order,
                    IF(c.is_active = 1, 'active', 'inactive') AS state,
                    COUNT(p.id) AS products_count
             FROM product_categories c
             LEFT JOIN products p ON p.category_id = c.id
             $where
             GROUP BY c.id
             ORDER BY c.sort_order ASC, c.id DESC
             LIMIT 100",
            $params,
        ];
    }

    if ($moduleKey === 'products') {
        [$where, $params] = owner_scope_condition($admin, 'p', 'products');
        return [
            "SELECT p.id, p.title, p.owner_type, p.owner_id, p.source_product_id,
                    c.title AS category_title, p.image_path, p.document_path, p.video_url, p.purchase_url, p.price, p.sort_order,
                    IF(p.is_active = 1, 'active', 'inactive') AS state
             FROM products p
             LEFT JOIN product_categories c ON c.id = p.category_id
             $where
             ORDER BY p.sort_order ASC, p.id DESC
             LIMIT 100",
            $params,
        ];
    }

    if ($moduleKey === 'tests') {
        [$where, $params] = owner_scope_condition($admin, 't', 'tests');
        return [
            "SELECT t.id, t.title, t.owner_type, t.owner_id, t.source_test_id, t.scoring_type, c.title AS category_title, t.sort_order,
                    IF(t.is_active = 1, 'active', 'inactive') AS state,
                    COUNT(DISTINCT q.id) AS questions_count,
                    COUNT(DISTINCT ts.id) AS scales_count
             FROM tests t
             LEFT JOIN product_categories c ON c.id = t.category_id
             LEFT JOIN test_questions q ON q.test_id = t.id
             LEFT JOIN test_scales ts ON ts.test_id = t.id
             $where
             GROUP BY t.id
             ORDER BY t.sort_order ASC, t.id DESC
             LIMIT 100",
            $params,
        ];
    }

    if ($moduleKey === 'broadcasts') {
        [$where, $params] = owned_content_scope_condition('broadcasts', $admin, 'b');
        return [
            "SELECT b.id, b.title, b.owner_type, b.owner_id, b.source_broadcast_id,
                    b.audience_type, b.platform, b.target_type, b.scheduled_at, b.status,
                    CASE
                        WHEN b.owner_type = 'reseller' THEN CONCAT('Лидер: ', COALESCE(r.name, CONCAT('#', b.owner_id)))
                        WHEN b.owner_type = 'manager' THEN CONCAT('Консультант: ', COALESCE(m.name, CONCAT('#', b.owner_id)))
                        ELSE 'Шаблон супер-админа'
                    END AS owner_label
             FROM broadcasts b
             LEFT JOIN resellers r ON r.id = b.owner_id AND b.owner_type = 'reseller'
             LEFT JOIN managers m ON m.id = b.owner_id AND b.owner_type = 'manager'
             $where
             ORDER BY b.id DESC
             LIMIT 100",
            $params,
        ];
    }

    if ($moduleKey === 'content') {
        [$where, $params] = owner_scope_condition($admin, 'cp', 'content');
        if ($admin['role'] !== 'superadmin') {
            $where = append_sql_condition($where, 'cp.status <> "hidden"');
        }
        return [
            "SELECT cp.id, cp.title, cp.owner_type, cp.owner_id, cp.source_content_post_id,
                    cp.content_type, cp.section_type, c.title AS category_title, cp.image_path,
                    cp.attachment_path, cp.video_url, cp.button_url, cp.status, cp.publish_at,
                    CASE
                        WHEN cp.owner_type = 'reseller' THEN CONCAT('Лидер: ', COALESCE(r.name, CONCAT('#', cp.owner_id)))
                        WHEN cp.owner_type = 'manager' THEN CONCAT('Консультант: ', COALESCE(m.name, CONCAT('#', cp.owner_id)))
                        ELSE 'Шаблон супер-админа'
                    END AS owner_label
             FROM content_posts cp
             LEFT JOIN product_categories c ON c.id = cp.category_id
             LEFT JOIN resellers r ON r.id = cp.owner_id AND cp.owner_type = 'reseller'
             LEFT JOIN managers m ON m.id = cp.owner_id AND cp.owner_type = 'manager'
             $where
             ORDER BY cp.id DESC
             LIMIT 100",
            $params,
        ];
    }

    if ($moduleKey === 'integrations') {
        [$where, $params] = integration_scope_condition($admin);
        return [
            "SELECT i.id, i.title,
                    CASE
                        WHEN i.owner_type = 'manager' THEN CONCAT('#', i.owner_id, ' ', COALESCE(NULLIF(m.name, ''), 'без имени'))
                        WHEN i.owner_type = 'reseller' THEN CONCAT('#', i.owner_id, ' ', COALESCE(NULLIF(r.name, ''), 'без имени'))
                        ELSE 'Общее'
                    END AS owner_label,
                    i.platform, i.external_id, i.callback_last_event_at, IF(i.is_active = 1, 'active', 'inactive') AS state
             FROM messaging_integrations i
             LEFT JOIN managers m ON m.id = i.owner_id AND i.owner_type = 'manager'
             LEFT JOIN resellers r ON r.id = i.owner_id AND i.owner_type = 'reseller'
             $where
             ORDER BY i.id DESC
             LIMIT 100",
            $params,
        ];
    }

    $columnsSql = implode(', ', array_map(static fn($column) => "`$column`", $module['columns']));
    [$where, $params] = scope_where_for_module($moduleKey, $admin);
    return ["SELECT $columnsSql FROM {$module['table']} $where ORDER BY id DESC LIMIT 100", $params];
}

function is_technical_client_name(string $name): bool
{
    return in_array(trim($name), ['Web User', 'VK User'], true);
}

function crud_format_ru_datetime(mixed $value): string
{
    $raw = trim((string)$value);
    if ($raw === '') {
        return app_text('auto.k_1b93795b9768');
    }

    try {
        return (new DateTimeImmutable($raw))->format('d.m.Y H:i');
    } catch (Throwable) {
        return $raw;
    }
}

function crud_cell_value(string $moduleKey, string $column, array $row): string
{
    if ($column === 'contacts') {
        return trim(($row['email'] ?? '') . "\n" . ($row['phone'] ?? '')) ?: app_text('auto.k_1b93795b9768');
    }

    if ($moduleKey === 'integrations' && $column === 'callback_last_event_at') {
        return crud_format_ru_datetime($row[$column] ?? null);
    }

    if ($moduleKey === 'resellers' && $column === 'manager_capacity') {
        $active = (int)($row['active_managers_count'] ?? 0);
        $total = (int)($row['managers_count'] ?? 0);
        $limit = $row['manager_limit'] !== null && $row['manager_limit'] !== ''
            ? (int)$row['manager_limit']
            : null;
        return 'Активных: ' . $active . "\nВсего: " . $total . "\nЛимит: " . ($limit !== null ? (string)$limit : 'без ограничения');
    }

    if ($moduleKey === 'resellers' && $column === 'team_capacity') {
        $resellerId = (int)($row['id'] ?? 0);
        $summary = $resellerId > 0 ? team_branch_summary($resellerId) : [
            'direct_leaders' => 0,
            'branch_leaders' => 0,
            'direct_consultants' => (int)($row['active_managers_count'] ?? 0),
            'branch_consultants' => (int)($row['active_managers_count'] ?? 0),
        ];
        $directLeaderLimit = $row['direct_leader_limit'] !== null && $row['direct_leader_limit'] !== '' ? (int)$row['direct_leader_limit'] : null;
        $branchLeaderLimit = $row['branch_leader_limit'] !== null && $row['branch_leader_limit'] !== '' ? (int)$row['branch_leader_limit'] : null;
        $directManagerLimit = $row['direct_manager_limit'] !== null && $row['direct_manager_limit'] !== ''
            ? (int)$row['direct_manager_limit']
            : ($row['manager_limit'] !== null && $row['manager_limit'] !== '' ? (int)$row['manager_limit'] : null);
        $branchManagerLimit = $row['branch_manager_limit'] !== null && $row['branch_manager_limit'] !== ''
            ? (int)$row['branch_manager_limit']
            : ($row['manager_limit'] !== null && $row['manager_limit'] !== '' ? (int)$row['manager_limit'] : null);

        return 'Лидеры 1-го уровня: ' . (int)$summary['direct_leaders'] . '/' . ($directLeaderLimit !== null ? $directLeaderLimit : 'без лимита')
            . "\nВсего лидеров в ветке: " . (int)$summary['branch_leaders'] . '/' . ($branchLeaderLimit !== null ? $branchLeaderLimit : 'без лимита')
            . "\nКонсультанты 1-го уровня: " . (int)$summary['direct_consultants'] . '/' . ($directManagerLimit !== null ? $directManagerLimit : 'без лимита')
            . "\nВсего консультантов в ветке: " . (int)$summary['branch_consultants'] . '/' . ($branchManagerLimit !== null ? $branchManagerLimit : 'без лимита');
    }

    if ($column === 'owner_label' && in_array($moduleKey, ['categories', 'products', 'tests', 'content', 'broadcasts'], true)) {
        return owned_content_owner_label($row, current_admin() ?: ['role' => 'superadmin']);
    }

    if ($moduleKey === 'resellers' && $column === 'billing_summary') {
        $items = [];
        if (trim((string)($row['billing_name'] ?? '')) !== '') {
            $items[] = (string)$row['billing_name'];
        }
        if (trim((string)($row['billing_inn'] ?? '')) !== '') {
            $items[] = 'ИНН ' . $row['billing_inn'];
        }
        if (trim((string)($row['billing_email'] ?? '')) !== '') {
            $items[] = (string)$row['billing_email'];
        }
        return $items ? implode("\n", $items) : app_text('auto.k_1b93795b9768');
    }

    if ($moduleKey === 'resellers' && $column === 'subscription_summary') {
        $planTitle = trim((string)($row['subscription_plan_title'] ?? ''));
        if ($planTitle === '' && empty($row['subscription_status'])) {
            return 'Нет подписки';
        }
        $status = !empty($row['subscription_status'])
            ? leader_subscription_status_label((string)$row['subscription_status'])
            : 'Назначена';
        $billing = !empty($row['subscription_plan_id'])
            ? subscription_plan_usage_amount((int)$row['id'])
            : null;
        $amount = $billing
            ? subscription_money_text((float)$billing['amount_due'])
            : ($row['amount_due'] !== null ? number_format((float)$row['amount_due'], 2, ',', ' ') . ' руб.' : 'сумма не задана');
        $period = trim((string)($row['subscription_starts_at'] ?? '')) . ' - ' . trim((string)($row['subscription_ends_at'] ?? ''));
        $lines = [$planTitle !== '' ? $planTitle : 'Индивидуальная подписка', $status];
        if ($amount !== 'сумма не задана') {
            $lines[] = 'К оплате сейчас: ' . $amount;
        }
        if ($billing) {
            $lines[] = 'Активно: лидеры ' . (int)$billing['leaders'] . ', консультанты ' . (int)$billing['consultants'];
        }
        $period = trim($period, " -");
        if ($period !== '') {
            $lines[] = $period;
        }

        return implode("\n", $lines);
    }

    if ($column === 'display_name' || $column === 'user_name') {
        $fullName = trim((string)($row['full_name'] ?? ''));
        if (is_technical_client_name($fullName)) {
            $fullName = '';
        }
        $name = $fullName ?: trim((string)($row['user_username'] ?? '')) ?: trim((string)($row['username'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        $platform = (string)($row['user_platform'] ?? $row['platform'] ?? '');
        return ($platform ? platform_label($platform) . ' клиент ' : 'Клиент ') . '#' . (int)($row['end_user_id'] ?? $row['id'] ?? 0);
    }

    if ($column === 'platform_account') {
        return trim(($row['platform'] ?? '') . "\n" . ($row['platform_user_id'] ?? '')) ?: app_text('auto.k_1b93795b9768');
    }

    if ($column === 'platform_profile') {
        $displayName = trim((string)($row['display_name'] ?? ''));
        $fullName = trim((string)(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')));
        if (is_technical_client_name($displayName)) {
            $displayName = '';
        }
        if (is_technical_client_name($fullName)) {
            $fullName = '';
        }

        return $displayName
            ?: $fullName
            ?: trim((string)($row['username'] ?? ''))
            ?: app_text('auto.k_1b93795b9768');
    }

    if ($column === 'lead_summary') {
        $message = trim((string)($row['message'] ?? ''));
        $message = $message !== '' ? $message : app_text('auto.k_503360e76342');
        return $message . "\n" . ($row['source_platform'] ?? '');
    }

    if ($column === 'lead_status') {
        return status_label((string)($row['status'] ?? 'new'));
    }

    if ($column === 'response_summary') {
        if (empty($row['response_count'])) {
            return status_label('none');
        }

        $status = (string)($row['last_response_status'] ?? 'pending');
        $date = (string)($row['last_response_at'] ?? '');
        return status_label($status) . ($date ? "\n" . $date : '');
    }

    if ($column === 'media_summary') {
        $items = [];
        if (!empty($row['image_path'])) {
            $items[] = app_text('auto.k_a9b4dacfde04');
        }
        if (!empty($row['document_path']) || !empty($row['attachment_path'])) {
            $items[] = app_text('auto.k_11cb1cfa1861');
        }
        if (!empty($row['video_url'])) {
            $items[] = app_text('auto.k_be5983f0a49d');
        }
        if (!empty($row['purchase_url']) || !empty($row['button_url'])) {
            $items[] = app_text('auto.k_11f4c398ee04');
        }

        return $items ? implode("\n", $items) : app_text('auto.k_1b93795b9768');
    }

    if ($column === 'test_type') {
        return ($row['scoring_type'] ?? 'single') === 'multiscale' ? 'Многошкальная матрица' : 'Обычный тест';
    }

    if ($moduleKey === 'users' && $column === 'client_profile') {
        if (
            trim((string)($row['city'] ?? '')) === ''
            && empty($row['gender'])
            && empty($row['birth_date'])
            && empty($row['age_years'])
        ) {
            return '—';
        }
        $gender = client_gender_labels()[(string)($row['gender'] ?? '')] ?? '—';
        $age = $row['age_years'] ?: ($row['birth_date'] ? date_diff(date_create((string)$row['birth_date']), date_create('today'))->y : null);
        return trim((string)($row['city'] ?? '')) . "\n" . $gender . ($age ? ', ' . $age . ' лет' : '');
    }

    if ($moduleKey === 'users' && $column === 'client_stage') {
        return user_client_stage_label($row);
    }

    if ($moduleKey === 'users' && $column === 'checkup_status') {
        if ((int)($row['draft_tests_count'] ?? 0) > 0) {
            return 'Начат';
        }
        if ((int)($row['completed_tests_count'] ?? 0) > 0) {
            return 'Завершён';
        }
        return 'Не начат';
    }

    return format_cell_value($row[$column] ?? null);
}

function status_badge_class(string $value): string
{
    return match ($value) {
        'new', 'none', 'Присоединился', 'Ожидает реферальный код', 'Ожидает оплаты', 'Нет подписки', status_label('none') => 'badge badge-new',
        'contacted', 'sent', 'Активна', status_label('contacted'), status_label('sent') => 'badge badge-sent',
        'interested', 'pending', status_label('interested'), status_label('pending') => 'badge badge-pending',
        'closed', status_label('closed') => 'badge badge-closed',
        'lost', 'failed', 'Истекла', 'Приостановлена', status_label('lost'), status_label('failed') => 'badge badge-failed',
        default => 'badge',
    };
}

function platform_badge_label(string $platform): string
{
    return match (normalize_platform($platform)) {
        'telegram' => 'TG',
        'VK' => 'VK',
        'OK' => 'OK',
        'MAX' => 'MAX',
        'web' => 'WEB',
        default => strtoupper($platform),
    };
}

function platform_badge_class(string $platform): string
{
    return 'platform-badge platform-' . strtolower(normalize_platform($platform));
}

function render_platform_badge(string $platform): string
{
    $normalized = normalize_platform($platform);
    return '<span class="' . h(platform_badge_class($normalized)) . '" title="' . h(platform_label($normalized)) . '">' . h(platform_badge_label($normalized)) . '</span>';
}

function render_cell(string $moduleKey, string $key, array $row): string
{
    if ($key === 'image_preview' && !empty($row['image_path'])) {
        return '<img class="table-thumb" src="' . h($row['image_path']) . '" alt="">';
    }

    if ($moduleKey === 'leads' && in_array($key, ['lead_status', 'response_summary'], true)) {
        $value = crud_cell_value($moduleKey, $key, $row);
        $firstLine = strtok($value, "\n") ?: $value;
        $rest = trim(substr($value, strlen($firstLine)));
        return '<span class="' . h(status_badge_class($firstLine)) . '">' . h($firstLine) . '</span>'
            . ($rest !== '' ? '<div class="cell-muted">' . nl2br(h($rest)) . '</div>' : '');
    }

    if ($moduleKey === 'leads' && $key === 'lead_summary') {
        $message = trim((string)($row['message'] ?? ''));
        $message = $message !== '' ? $message : app_text('auto.k_503360e76342');
        return '<div class="lead-message">' . nl2br(h($message)) . '</div><div class="cell-muted">' . render_platform_badge((string)($row['source_platform'] ?? '')) . '</div>';
    }

    if ($moduleKey === 'resellers' && $key === 'subscription_summary') {
        $value = crud_cell_value($moduleKey, $key, $row);
        $firstLine = strtok($value, "\n") ?: $value;
        $rest = trim(substr($value, strlen($firstLine)));
        return '<span class="' . h(status_badge_class($firstLine)) . '">' . h($firstLine) . '</span>'
            . ($rest !== '' ? '<div class="cell-muted">' . nl2br(h($rest)) . '</div>' : '');
    }

    if ($key === 'platform_account') {
        return render_platform_badge((string)($row['platform'] ?? ''))
            . '<div class="cell-muted">' . h((string)($row['platform_user_id'] ?? '')) . '</div>';
    }

    if ($key === 'platform_accounts_summary') {
        $items = array_filter(explode("\n", (string)($row[$key] ?? '')));
        if (!$items) {
            return render_platform_badge((string)($row['platform'] ?? ''))
                . '<div class="cell-muted">' . h((string)($row['platform_user_id'] ?? '')) . '</div>';
        }

        $html = [];
        foreach ($items as $item) {
            [$platform, $platformUserId] = array_pad(explode(':', $item, 2), 2, '');
            $html[] = '<div class="platform-account-line">'
                . render_platform_badge($platform)
                . '<span class="cell-muted">' . h($platformUserId) . '</span>'
                . '</div>';
        }
        return implode('', $html);
    }

    if ($key === 'platform') {
        return render_platform_badge((string)($row['platform'] ?? ''));
    }

    if ($moduleKey === 'tests' && $key === 'test_type') {
        $type = (string)($row['scoring_type'] ?? 'single');
        $class = $type === 'multiscale' ? 'badge badge-pending' : 'badge';
        return '<span class="' . h($class) . '">' . h(crud_cell_value($moduleKey, $key, $row)) . '</span>';
    }

    if ($moduleKey === 'users' && in_array($key, ['client_stage', 'checkup_status'], true)) {
        $value = crud_cell_value($moduleKey, $key, $row);
        return '<span class="' . h(status_badge_class($value)) . '">' . h($value) . '</span>';
    }

    if ($moduleKey === 'users' && $key === 'client_profile') {
        return nl2br(h(crud_cell_value($moduleKey, $key, $row)));
    }

    return nl2br(h(crud_cell_value($moduleKey, $key, $row)));
}

function render_lead_filters(): string
{
    $filters = lead_filters_from_request();
    $statuses = [
        'all' => app_text('auto.k_dad15ae6903a'),
        'new' => status_label('new'),
        'contacted' => status_label('contacted'),
        'interested' => status_label('interested'),
        'closed' => status_label('closed'),
        'lost' => status_label('lost'),
    ];
    $platforms = ['all' => platform_label('all'), 'telegram' => platform_label('telegram'), 'VK' => platform_label('VK'), 'OK' => platform_label('OK'), 'MAX' => platform_label('MAX'), 'web' => platform_label('web')];
    $responses = ['all' => app_text('auto.k_a51484b486a9'), 'none' => status_label('none'), 'sent' => status_label('sent'), 'pending' => status_label('pending'), 'failed' => status_label('failed')];

    ob_start();
    ?>
    <form method="get" class="filters">
        <input type="hidden" name="module" value="leads">
        <label>
            <span><?= h(app_text('auto.k_f7f293b5c58c')) ?></span>
            <select name="status">
                <?php foreach ($statuses as $value => $label): ?>
                    <option value="<?= h($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span><?= h(app_text('auto.k_89009febe5c6')) ?></span>
            <select name="platform">
                <?php foreach ($platforms as $value => $label): ?>
                    <option value="<?= h($value) ?>" <?= $filters['platform'] === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span><?= h(app_text('auto.k_e9d7bdd83831')) ?></span>
            <select name="response">
                <?php foreach ($responses as $value => $label): ?>
                    <option value="<?= h($value) ?>" <?= $filters['response'] === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit"><?= h(app_text('auto.k_7788a11e4dbf')) ?></button>
        <a class="button secondary-button" href="crud.php?module=leads"><?= h(app_text('auto.k_058f162d2926')) ?></a>
    </form>
    <?php
    return trim(ob_get_clean());
}

function render_lead_pagination(int $rowCount): string
{
    $filters = lead_filters_from_request();
    $page = $filters['page'];
    $params = $_GET;
    $params['module'] = 'leads';

    ob_start();
    ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <?php $params['page'] = $page - 1; ?>
            <a class="button secondary-button" href="crud.php?<?= h(http_build_query($params)) ?>"><?= h(app_text('auto.k_f6dab074d7bb')) ?></a>
        <?php endif; ?>
        <span><?= h(app_text('auto.k_97e20f8391be')) ?><?= (int)$page ?></span>
        <?php if ($rowCount >= $filters['per_page']): ?>
            <?php $params['page'] = $page + 1; ?>
            <a class="button secondary-button" href="crud.php?<?= h(http_build_query($params)) ?>"><?= h(app_text('auto.k_e3b933130129')) ?></a>
        <?php endif; ?>
    </div>
    <?php
    return trim(ob_get_clean());
}

function crud_table_request(): array
{
    return admin_table_request([], 'id', 'desc');
}

function crud_search_columns(string $moduleKey): array
{
    return match ($moduleKey) {
        'resellers' => ['id', 'name', 'parent_name', 'email', 'phone', 'billing_name', 'billing_email', 'referral_code', 'subscription_plan_title', 'subscription_status', 'state'],
        'managers' => ['id', 'name', 'email', 'phone', 'referral_code', 'reseller_name', 'state'],
        'users' => ['id', 'full_name', 'username', 'platform', 'platform_user_id', 'city', 'reseller_name', 'manager_name', 'client_stage', 'platform_accounts_summary'],
        'platform_accounts' => ['id', 'full_name', 'user_username', 'display_name', 'username', 'platform', 'platform_user_id'],
        'categories' => ['id', 'title', 'slug', 'owner_type', 'state'],
        'products' => ['id', 'title', 'category_title', 'price', 'state'],
        'tests' => ['id', 'title', 'category_title', 'scoring_type', 'state'],
        'broadcasts' => ['id', 'title', 'owner_label', 'platform', 'target_type', 'status'],
        'content' => ['id', 'title', 'owner_label', 'content_type', 'section_type', 'category_title', 'status'],
        'integrations' => ['id', 'title', 'owner_label', 'platform', 'external_id', 'state'],
        default => ['id', 'title', 'name', 'email', 'status'],
    };
}

function crud_sort_columns(string $moduleKey): array
{
    $common = ['id' => '`id`'];
    return $common + match ($moduleKey) {
        'resellers' => [
            'name' => '`name`',
            'parent_name' => '`parent_name`',
            'contacts' => '`email`',
            'referral_code' => '`referral_code`',
            'subscription_summary' => '`subscription_plan_title`',
            'users_count' => '`users_count`',
            'state' => '`state`',
        ],
        'managers' => [
            'name' => '`name`',
            'reseller_name' => '`reseller_name`',
            'contacts' => '`email`',
            'referral_code' => '`referral_code`',
            'users_count' => '`users_count`',
            'state' => '`state`',
        ],
        'users' => [
            'display_name' => '`full_name`',
            'client_stage' => '`client_stage`',
            'checkup_status' => '`completed_tests_count`',
            'manager_name' => '`manager_name`',
            'last_activity_at' => '`last_activity_at`',
        ],
        'platform_accounts' => [
            'user_name' => '`full_name`',
            'platform_account' => '`platform`',
            'username' => '`username`',
            'created_at' => '`created_at`',
        ],
        'categories' => [
            'title' => '`title`',
            'slug' => '`slug`',
            'products_count' => '`products_count`',
            'sort_order' => '`sort_order`',
            'state' => '`state`',
        ],
        'products' => [
            'title' => '`title`',
            'category_title' => '`category_title`',
            'price' => '`price`',
            'sort_order' => '`sort_order`',
            'state' => '`state`',
        ],
        'tests' => [
            'title' => '`title`',
            'category_title' => '`category_title`',
            'test_type' => '`scoring_type`',
            'questions_count' => '`questions_count`',
            'sort_order' => '`sort_order`',
            'state' => '`state`',
        ],
        'broadcasts' => [
            'title' => '`title`',
            'owner_label' => '`owner_label`',
            'platform' => '`platform`',
            'status' => '`status`',
            'scheduled_at' => '`scheduled_at`',
        ],
        'content' => [
            'title' => '`title`',
            'owner_label' => '`owner_label`',
            'content_type' => '`content_type`',
            'section_type' => '`section_type`',
            'status' => '`status`',
            'publish_at' => '`publish_at`',
        ],
        'integrations' => [
            'title' => '`title`',
            'owner_label' => '`owner_label`',
            'platform' => '`platform`',
            'external_id' => '`external_id`',
            'state' => '`state`',
        ],
        default => [
            'title' => '`title`',
            'name' => '`name`',
            'status' => '`status`',
        ],
    };
}

function crud_strip_order_limit(string $sql): string
{
    return admin_table_strip_order_limit($sql);
}

function crud_paginated_rows(string $moduleKey, string $sql, array $params, array $columns): array
{
    $searchColumns = array_intersect(crud_search_columns($moduleKey), array_merge(array_keys($columns), [
        'id', 'name', 'title', 'email', 'phone', 'username', 'full_name', 'parent_name', 'reseller_name',
        'manager_name', 'platform', 'platform_user_id', 'city', 'referral_code', 'subscription_plan_title',
        'subscription_status', 'owner_label', 'category_title', 'status', 'state', 'external_id',
        'platform_accounts_summary', 'billing_name', 'billing_email',
    ]));

    return admin_table_paginated_rows($sql, $params, crud_sort_columns($moduleKey), $searchColumns, 'id', 'desc');
}

function crud_list_url(array $overrides = []): string
{
    return admin_table_url($overrides, 'crud.php');
}

function render_crud_table_tools(string $moduleKey, array $meta): string
{
    if (!$meta || $moduleKey === 'leads') {
        return '';
    }

    $filters = [];
    if ($moduleKey === 'users') {
        $stageOptions = ['' => 'Все этапы'];
        foreach (client_stage_labels() as $value => $label) {
            $stageOptions[(string)$value] = (string)$label;
        }
        $filters = [
            [
                'name' => 'user_scope',
                'label' => 'Записи',
                'options' => [
                    'clients' => 'Клиенты',
                    'visitors' => 'Без консультанта',
                    'all' => 'Все записи',
                ],
                'value' => users_scope_filter(),
            ],
            [
                'name' => 'client_stage',
                'label' => 'Этап',
                'options' => $stageOptions,
            ],
            [
                'name' => 'checkup',
                'label' => 'Чек-ап',
                'options' => [
                    '' => 'Любой',
                    'not_started' => 'Не начат',
                    'started' => 'Начат',
                    'completed' => 'Завершён',
                ],
            ],
            [
                'name' => 'activity',
                'label' => 'Активность',
                'options' => [
                    '' => 'Любая',
                    'active_7' => 'Был активен за 7 дней',
                    'inactive_14' => 'Неактивен 14 дней',
                ],
            ],
        ];
    }

    return render_admin_table_tools($meta, $filters, [
        'hidden' => ['module' => $moduleKey],
        'reset_url' => 'crud.php?module=' . urlencode($moduleKey),
        'search_placeholder' => 'Имя, email, код, статус',
    ]);
}

function render_sortable_header(string $moduleKey, string $key, string $label, array $meta): string
{
    if (!$meta || $moduleKey === 'leads' || !isset(crud_sort_columns($moduleKey)[$key])) {
        return h($label);
    }

    return render_admin_sort_link($key, $label, $meta, crud_sort_columns($moduleKey), 'crud.php');
}

function render_crud_pagination(string $moduleKey, array $meta): string
{
    if (!$meta || $moduleKey === 'leads' || (int)($meta['page_count'] ?? 1) <= 1) {
        return '';
    }

    return render_admin_pagination($meta, 'crud.php');
}

function lead_display_message(string $message): string
{
    $message = trim($message);
    if ($message === '') {
        return '';
    }

    $cleaned = preg_replace('/(?:\R{2,})?Вложения VK:\R(?:•[^\R]*(?:\R|$))+/u', '', $message);
    return trim($cleaned ?? $message);
}

function lead_decode_attachments(?string $json): array
{
    if (!$json) {
        return [];
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return [];
    }

    return array_values(array_filter($decoded, 'is_array'));
}

function lead_attachment_type_from_label(string $label, string $url): string
{
    $label = mb_strtolower($label, 'UTF-8');
    $path = strtolower((string)(parse_url($url, PHP_URL_PATH) ?: $url));
    $host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?: ''));

    if (str_contains($label, 'фото')
        || preg_match('/\.(png|jpe?g|webp|gif)(?:[?#]|$)/i', $path)
        || str_contains($host, 'vkuserphoto')
        || str_contains($host, 'userapi.com')
        || preg_match('/(^|\.)sun\d+-\d+\.userapi\.com$/i', $host)
    ) {
        return 'photo';
    }

    if (str_contains($label, 'стик')) {
        return 'sticker';
    }

    if (str_contains($label, 'голос') || str_contains($label, 'аудио') || preg_match('/\.(mp3|ogg|wav|m4a)(?:[?#]|$)/i', $path)) {
        return 'audio';
    }

    if (str_contains($label, 'видео') || preg_match('/\.(mp4|webm|mov)(?:[?#]|$)/i', $path)) {
        return 'video';
    }

    if (str_contains($label, 'документ')) {
        return 'doc';
    }

    return 'link';
}

function lead_parse_attachments_from_message(string $message): array
{
    if (!preg_match_all('/•\s*([^:\r\n]+):\s*(https?:\/\/[^\s\r\n]+)/u', $message, $matches, PREG_SET_ORDER)) {
        return [];
    }

    $items = [];
    foreach ($matches as $match) {
        $label = trim((string)$match[1]);
        $url = trim((string)$match[2]);
        if ($url === '') {
            continue;
        }

        $items[] = [
            'type' => lead_attachment_type_from_label($label, $url),
            'title' => $label !== '' ? $label : 'Вложение',
            'url' => $url,
        ];
    }

    return $items;
}

function lead_attachment_items(?string $json, string $message = ''): array
{
    $items = lead_decode_attachments($json);
    if ($items) {
        return $items;
    }

    return lead_parse_attachments_from_message($message);
}

function lead_attachment_payload(array $item): array
{
    $type = (string)($item['type'] ?? '');
    $raw = is_array($item['raw'] ?? null) ? $item['raw'] : $item;

    return is_array($raw[$type] ?? null) ? $raw[$type] : [];
}

function lead_best_image_url(array $images): string
{
    usort($images, static fn(array $a, array $b): int => (((int)($b['width'] ?? 0) * (int)($b['height'] ?? 0)) <=> ((int)($a['width'] ?? 0) * (int)($a['height'] ?? 0))));

    foreach ($images as $image) {
        if (!empty($image['url'])) {
            return (string)$image['url'];
        }
    }

    return '';
}

function lead_attachment_url(array $item): string
{
    $url = trim((string)($item['url'] ?? ''));
    if ($url !== '') {
        return $url;
    }

    $type = (string)($item['type'] ?? '');
    $payload = lead_attachment_payload($item);

    if ($type === 'photo') {
        return lead_best_image_url(is_array($payload['sizes'] ?? null) ? $payload['sizes'] : []);
    }

    if ($type === 'sticker') {
        $images = is_array($payload['images'] ?? null) ? $payload['images'] : [];
        if (!$images && is_array($payload['images_with_background'] ?? null)) {
            $images = $payload['images_with_background'];
        }

        return lead_best_image_url($images);
    }

    if ($type === 'audio_message') {
        return trim((string)($payload['link_mp3'] ?? $payload['link_ogg'] ?? ''));
    }

    if ($type === 'audio') {
        return trim((string)($payload['url'] ?? ''));
    }

    if ($type === 'video' && !empty($payload['owner_id']) && !empty($payload['id'])) {
        $videoUrl = 'https://vk.com/video' . $payload['owner_id'] . '_' . $payload['id'];
        return !empty($payload['access_key']) ? $videoUrl . '_' . $payload['access_key'] : $videoUrl;
    }

    return trim((string)($payload['url'] ?? ''));
}

function lead_attachment_kind(array $item): string
{
    return match ((string)($item['type'] ?? '')) {
        'photo', 'sticker' => 'image',
        'audio', 'audio_message' => 'audio',
        'video' => 'video',
        'doc', 'link' => 'link',
        default => lead_attachment_url($item) !== '' ? 'link' : 'unknown',
    };
}

function lead_attachment_short_label(array $item): string
{
    return match ((string)($item['type'] ?? '')) {
        'photo' => 'Фото',
        'sticker' => 'Стикер',
        'audio', 'audio_message' => 'Аудио',
        'video' => 'Видео',
        'doc' => 'Документ',
        'link' => 'Ссылка',
        default => 'Вложение',
    };
}

function render_lead_attachment(array $item): string
{
    $kind = lead_attachment_kind($item);
    $url = lead_attachment_url($item);
    $title = trim((string)($item['title'] ?? '')) ?: lead_attachment_short_label($item);
    $label = lead_attachment_short_label($item);

    if ($kind === 'image' && $url !== '') {
        return '<button type="button" class="lead-attachment lead-attachment-image" data-lead-media data-media-type="image" data-media-url="' . h($url) . '" data-media-title="' . h($title) . '"><span class="lead-attachment-thumb"><img src="' . h($url) . '" alt="' . h($label) . '" loading="lazy"></span><span>' . h($label) . '</span></button>';
    }

    if (in_array($kind, ['audio', 'video'], true) && $url !== '') {
        $icon = $kind === 'audio' ? '♪' : '▶';
        return '<button type="button" class="lead-attachment lead-attachment-icon-tile" data-lead-media data-media-type="' . h($kind) . '" data-media-url="' . h($url) . '" data-media-title="' . h($title) . '"><span class="lead-attachment-icon">' . h($icon) . '</span><span>' . h($label) . '</span></button>';
    }

    if ($url !== '') {
        return '<a class="lead-attachment lead-attachment-icon-tile" href="' . h($url) . '" target="_blank" rel="noopener"><span class="lead-attachment-icon">↗</span><span>' . h($label) . '</span></a>';
    }

    return '<span class="lead-attachment lead-attachment-static"><span class="lead-attachment-icon">•</span><span>' . h($label) . '</span></span>';
}

function render_lead_attachments(?string $json, string $message = '', string $extraClass = ''): string
{
    $items = lead_attachment_items($json, $message);
    if (!$items) {
        return '';
    }

    $html = '';
    foreach ($items as $item) {
        $html .= render_lead_attachment($item);
    }

    $class = trim('lead-attachments ' . $extraClass);
    return '<div class="' . h($class) . '">' . $html . '</div>';
}

function lead_attachment_type_from_url(string $url): string
{
    $path = strtolower((string)(parse_url($url, PHP_URL_PATH) ?: $url));
    $host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?: ''));

    if (preg_match('/\.(png|jpe?g|webp|gif)$/i', $path)
        || str_contains($host, 'vkuserphoto')
        || str_contains($host, 'userapi.com')
        || preg_match('/(^|\.)sun\d+-\d+\.userapi\.com$/i', $host)
    ) {
        return 'photo';
    }

    if (preg_match('/\.(mp3|ogg|wav|m4a|webm)$/i', $path)) {
        return 'audio';
    }

    if (preg_match('/\.(mp4|mov|m4v)$/i', $path)) {
        return 'video';
    }

    return 'doc';
}

function render_lead_file_attachments(array $paths, string $extraClass = ''): string
{
    $html = '';
    foreach ($paths as $index => $path) {
        $url = trim((string)$path);
        if ($url === '') {
            continue;
        }

        $html .= render_lead_attachment([
            'type' => lead_attachment_type_from_url($url),
            'title' => app_text('lead_response.lead_file_numbered', [
                'index' => $index + 1,
                'total' => count($paths),
            ]),
            'url' => $url,
        ]);
    }

    if ($html === '') {
        return '';
    }

    $class = trim('lead-attachments ' . $extraClass);
    return '<div class="' . h($class) . '">' . $html . '</div>';
}

function render_lead_media_modal(): string
{
    return '<dialog class="admin-modal lead-media-modal" id="lead-media-modal"><div class="modal-shell lead-media-shell"><div class="modal-head"><div><span class="eyebrow">Вложение</span><h2 data-lead-media-title>Просмотр</h2></div><form method="dialog"><button class="icon-button" aria-label="Закрыть">&times;</button></form></div><div class="modal-body lead-media-body" data-lead-media-body></div></div></dialog>';
}

function lead_group_key(array $row): string
{
    $userId = (int)($row['end_user_id'] ?? 0);
    if ($userId > 0) {
        return 'user:' . $userId;
    }

    return 'lead:' . (int)($row['id'] ?? 0);
}

function lead_group_rows(array $rows): array
{
    $groups = [];

    foreach ($rows as $row) {
        $key = lead_group_key($row);
        if (!isset($groups[$key])) {
            $groups[$key] = [
                'latest' => $row,
                'items' => [],
                'platforms' => [],
                'response_count' => 0,
                'latest_response' => null,
            ];
        }

        $platform = normalize_platform((string)($row['source_platform'] ?? ''));
        if ($platform !== '') {
            $groups[$key]['platforms'][$platform] = true;
        }

        $groups[$key]['items'][] = $row;
        $groups[$key]['response_count'] += (int)($row['response_count'] ?? 0);

        if (!empty($row['last_response_at'])) {
            $latestResponse = $groups[$key]['latest_response'];
            if (!$latestResponse || strcmp((string)$row['last_response_at'], (string)($latestResponse['last_response_at'] ?? '')) > 0) {
                $groups[$key]['latest_response'] = $row;
            }
        }
    }

    return array_values($groups);
}

function lead_snippet(string $text, int $limit = 180): string
{
    $text = trim($text);
    if ($text === '') {
        return app_text('auto.k_503360e76342');
    }

    return mb_strlen($text, 'UTF-8') > $limit
        ? mb_substr($text, 0, $limit, 'UTF-8') . '...'
        : $text;
}

function lead_conversation_items(int $endUserId): array
{
    if ($endUserId <= 0) {
        return [];
    }

    $leadStmt = db()->prepare(
        'SELECT id, source_platform, status, message, attachments_json, created_at
         FROM leads
         WHERE end_user_id = :end_user_id
         ORDER BY id DESC
         LIMIT 80'
    );
    $leadStmt->execute(['end_user_id' => $endUserId]);

    $items = [];
    foreach ($leadStmt->fetchAll() as $lead) {
        $items[] = [
            'kind' => 'incoming',
            'sort_id' => (int)$lead['id'],
            'platform' => (string)($lead['source_platform'] ?? ''),
            'status' => (string)($lead['status'] ?? 'new'),
            'created_at' => (string)($lead['created_at'] ?? ''),
            'message' => (string)($lead['message'] ?? ''),
            'attachments_json' => $lead['attachments_json'] ?? null,
            'attachment_path' => null,
            'content_title' => null,
            'content_id' => null,
            'test_title' => null,
            'test_id' => null,
            'actor_name' => 'Клиент',
        ];
    }

    $responseStmt = db()->prepare(
        'SELECT lr.id, lr.platform, lr.status, lr.message_text, lr.attachment_path, lr.created_at,
                au.name AS admin_name, cp.title AS content_title, lr.content_post_id, t.title AS test_title, lr.test_id
         FROM lead_responses lr
         INNER JOIN leads l ON l.id = lr.lead_id
         LEFT JOIN admin_users au ON au.id = lr.admin_user_id
         LEFT JOIN content_posts cp ON cp.id = lr.content_post_id
         LEFT JOIN tests t ON t.id = lr.test_id
         WHERE l.end_user_id = :end_user_id
         ORDER BY lr.id DESC
         LIMIT 80'
    );
    $responseStmt->execute(['end_user_id' => $endUserId]);

    foreach ($responseStmt->fetchAll() as $response) {
        $items[] = [
            'kind' => 'outgoing',
            'sort_id' => (int)$response['id'],
            'platform' => (string)($response['platform'] ?? ''),
            'status' => (string)($response['status'] ?? 'pending'),
            'created_at' => (string)($response['created_at'] ?? ''),
            'message' => (string)($response['message_text'] ?? ''),
            'attachments_json' => null,
            'attachment_path' => $response['attachment_path'] ?? null,
            'content_title' => $response['content_title'] ?? null,
            'content_id' => $response['content_post_id'] ?? null,
            'test_title' => $response['test_title'] ?? null,
            'test_id' => $response['test_id'] ?? null,
            'actor_name' => (string)($response['admin_name'] ?? 'Консультант'),
        ];
    }

    usort($items, static function (array $a, array $b): int {
        $timeCompare = strcmp((string)$a['created_at'], (string)$b['created_at']);
        if ($timeCompare !== 0) {
            return $timeCompare;
        }

        return (int)$a['sort_id'] <=> (int)$b['sort_id'];
    });

    return $items;
}

function render_lead_conversation(int $endUserId): string
{
    $items = lead_conversation_items($endUserId);
    if (!$items) {
        return '<div class="empty-state">Сообщений пока нет.</div>';
    }

    ob_start();
    ?>
    <div class="lead-conversation">
        <?php foreach ($items as $item): ?>
            <?php
            $isOutgoing = $item['kind'] === 'outgoing';
            $status = $isOutgoing ? status_label((string)$item['status']) : status_label((string)$item['status']);
            $message = lead_display_message((string)$item['message']);
            $attachments = $isOutgoing
                ? render_lead_file_attachments(lead_response_attachment_paths($item['attachment_path'] ?? null), 'lead-conversation-attachments')
                : render_lead_attachments($item['attachments_json'] ?? null, (string)$item['message'], 'lead-conversation-attachments');
            ?>
            <article class="lead-conversation-item <?= $isOutgoing ? 'lead-conversation-outgoing' : 'lead-conversation-incoming' ?>">
                <div class="lead-conversation-meta">
                    <strong><?= h($isOutgoing ? (string)$item['actor_name'] : 'Клиент') ?></strong>
                    <?= render_platform_badge((string)$item['platform']) ?>
                    <span class="<?= h(status_badge_class($status)) ?>"><?= h($status) ?></span>
                    <span class="cell-muted"><?= h((string)$item['created_at']) ?></span>
                </div>
                <?php if ($message !== ''): ?>
                    <div class="lead-response-message"><?= nl2br(h($message)) ?></div>
                <?php endif; ?>
                <?= $attachments ?>
                <?php if ($isOutgoing && (($item['content_title'] ?? '') || ($item['test_title'] ?? ''))): ?>
                    <div class="lead-response-resources">
                        <?php if ($item['content_title'] ?? ''): ?>
                            <a href="crud.php?module=content&amp;action=edit&amp;id=<?= (int)$item['content_id'] ?>"><?= h(app_text('lead_response.open_material')) ?>: <?= h((string)$item['content_title']) ?></a>
                        <?php endif; ?>
                        <?php if ($item['test_title'] ?? ''): ?>
                            <a href="crud.php?module=tests&amp;action=edit&amp;id=<?= (int)$item['test_id'] ?>"><?= h(app_text('lead_response.pass_test')) ?>: <?= h((string)$item['test_title']) ?></a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
    <?php
    return trim(ob_get_clean());
}

function render_lead_cards(array $rows, bool $canEdit, bool $canDelete): string
{
    $groups = lead_group_rows($rows);

    ob_start();
    ?>
    <div class="lead-card-list">
        <?php foreach ($groups as $group): ?>
            <?php
            $row = $group['latest'];
            $items = array_slice($group['items'], 0, 3);
            $status = status_label((string)($row['status'] ?? 'new'));
            $responseRow = $group['latest_response'] ?? $row;
            $response = crud_cell_value('leads', 'response_summary', $responseRow);
            $responseFirstLine = strtok($response, "\n") ?: $response;
            $responseRest = trim(substr($response, strlen($responseFirstLine)));
            $user = crud_cell_value('leads', 'user_name', $row);
            $responseCount = (int)$group['response_count'];
            $lastResponseText = trim((string)($responseRow['last_response_text'] ?? ''));
            $lastResponseText = mb_strlen($lastResponseText, 'UTF-8') > 180 ? mb_substr($lastResponseText, 0, 180, 'UTF-8') . '...' : $lastResponseText;
            $threadCount = count($group['items']);
            ?>
            <article class="lead-card lead-ticket">
                <div class="lead-ticket-main">
                    <div class="lead-card-top">
                        <span class="<?= h(status_badge_class($status)) ?>"><?= h($status) ?></span>
                        <?php foreach (array_keys($group['platforms']) as $platform): ?>
                            <?= render_platform_badge((string)$platform) ?>
                        <?php endforeach; ?>
                        <span class="badge"><?= h(lead_request_type_label((string)($row['request_type'] ?? 'consultation'))) ?></span>
                        <span class="cell-muted">Последнее: #<?= (int)$row['id'] ?> · <?= h((string)($row['created_at'] ?? '')) ?></span>
                    </div>
                    <h3><?= h($user) ?></h3>
                    <div class="lead-chat-preview">
                        <?php foreach ($items as $item): ?>
                            <?php
                            $itemStatus = status_label((string)($item['status'] ?? 'new'));
                            $message = lead_snippet(lead_display_message((string)($item['message'] ?? '')));
                            $attachments = render_lead_attachments($item['attachments_json'] ?? null, (string)($item['message'] ?? ''), 'lead-attachments-compact');
                            ?>
                            <div class="lead-chat-row">
                                <div class="lead-chat-row-head">
                                    <span class="cell-muted">#<?= (int)$item['id'] ?> · <?= h((string)($item['created_at'] ?? '')) ?></span>
                                    <span class="<?= h(status_badge_class($itemStatus)) ?>"><?= h($itemStatus) ?></span>
                                </div>
                                <p class="lead-card-message"><?= nl2br(h($message)) ?></p>
                                <?= $attachments ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($threadCount > count($items)): ?>
                            <div class="cell-muted">Еще обращений: <?= (int)($threadCount - count($items)) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php if ($lastResponseText !== ''): ?>
                        <span class="lead-last-response"><?= nl2br(h($lastResponseText)) ?></span>
                    <?php endif; ?>
                </div>
                <div class="lead-ticket-side">
                    <div class="lead-compact-meta">
                        <span><b><?= h(app_text('auto.k_8d98911527e4')) ?></b><?= h((string)($row['manager_name'] ?: app_text('auto.k_1b93795b9768'))) ?></span>
                        <span><b><?= h(app_text('auto.k_e9d7bdd83831')) ?></b><span class="<?= h(status_badge_class($responseFirstLine)) ?>"><?= h($responseFirstLine) ?></span></span>
                        <span><b>Обращений</b><?= (int)$threadCount ?></span>
                        <span><b><?= h(app_text('lead_response.count_label')) ?></b><?= $responseCount ?></span>
                        <?php if ($responseRest !== ''): ?>
                            <span class="lead-response-note"><b><?= h(app_text('auto.k_f7f293b5c58c')) ?></b><?= nl2br(h($responseRest)) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($canEdit || $canDelete): ?>
                    <div class="lead-card-actions">
                        <?php if ($canEdit): ?>
                            <a
                                class="button"
                                href="lead_chat.php?id=<?= (int)$row['id'] ?>"
                                target="_blank"
                                rel="noopener"
                            >Открыть чат</a>
                        <?php endif; ?>
                        <?php if ($canDelete): ?>
                            <form method="post" onsubmit="return confirm('<?= h(app_text('auto.k_112417195434', ['id' => (int)$row['id']])) ?>');">
                                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                <button type="submit" class="danger-button"><?= h(app_text('auto.k_86ea33aef5e9')) ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
    <?php
    return trim(ob_get_clean());
}

function render_crud_list(string $moduleKey, array $columns, array $rows, bool $canEdit, bool $canDelete, array $admin = [], array $meta = []): string
{
    ob_start();
    ?>
    <?php if ($moduleKey === 'leads'): ?>
        <?= render_lead_filters() ?>
    <?php else: ?>
        <?= render_crud_table_tools($moduleKey, $meta) ?>
    <?php endif; ?>
    <div class="table-summary"><?= h(app_text('auto.k_b1062a5651c3')) ?><?= (int)($meta['total'] ?? count($rows)) ?></div>
    <?php if ($rows): ?>
        <?php if ($moduleKey === 'leads'): ?>
            <?= render_lead_cards($rows, $canEdit, $canDelete) ?>
        <?php else: ?>
        <table class="data-table responsive-table" data-module="<?= h($moduleKey) ?>">
            <thead>
                <tr>
                    <?php foreach ($columns as $key => $label): ?>
                        <th data-column="<?= h((string)$key) ?>"><?= render_sortable_header($moduleKey, (string)$key, (string)$label, $meta) ?></th>
                    <?php endforeach; ?>
                    <?php if ($canEdit || $canDelete): ?>
                        <th data-column="actions"><?= h(app_text('auto.k_9978ac34b293')) ?></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php foreach ($columns as $key => $label): ?>
                            <td data-column="<?= h((string)$key) ?>" data-label="<?= h((string)$label) ?>"><?= render_cell($moduleKey, $key, $row) ?></td>
                        <?php endforeach; ?>
                        <?php if ($canEdit || $canDelete): ?>
                            <td class="row-actions" data-column="actions" data-label="<?= h(app_text('auto.k_9978ac34b293')) ?>">
                                <?php if ($canEdit): ?>
                                    <a class="link-button" href="crud.php?module=<?= h($moduleKey) ?>&action=edit&id=<?= (int)$row['id'] ?>"><?= h(crud_action_label($moduleKey)) ?></a>
                                <?php endif; ?>
                                <?php if ($moduleKey === 'broadcasts' && $canEdit && in_array((string)($row['status'] ?? ''), ['draft', 'scheduled'], true)): ?>
                                    <form method="post" class="inline-form" onsubmit="return confirm('<?= h(app_text('broadcasts.run_confirm')) ?>');">
                                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="run_broadcast">
                                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                        <button type="submit" class="link-button"><?= h(app_text('broadcasts.run_now')) ?></button>
                                    </form>
                                <?php endif; ?>
                                <?php if (owned_content_can_reset($moduleKey, $row, $admin)): ?>
                                    <form method="post" class="inline-form" onsubmit="return confirm('Сбросить личную версию и снова использовать версию выше?');">
                                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="reset_owned_content">
                                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                        <button type="submit" class="link-button">Сбросить к версии выше</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($canDelete): ?>
                                    <form method="post" class="inline-form" onsubmit="return confirm('<?= h(app_text('auto.k_112417195434', ['id' => (int)$row['id']])) ?>');">
                                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                        <button type="submit" class="link-button danger"><?= h(app_text('auto.k_86ea33aef5e9')) ?></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    <?php else: ?>
        <div class="empty-state"><?= h(app_text('auto.k_488eec688217')) ?></div>
    <?php endif; ?>
    <?php if ($moduleKey === 'leads'): ?>
        <?= render_lead_pagination(count($rows)) ?>
        <?= render_lead_media_modal() ?>
    <?php else: ?>
        <?= render_crud_pagination($moduleKey, $meta) ?>
    <?php endif; ?>
    <?php
    return trim(ob_get_clean());
}
