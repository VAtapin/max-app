<?php

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
            'phone',
            'email',
            'status',
        ]));
    }

    if ($moduleKey === 'leads') {
        return array_intersect_key($fields, array_flip([
            'manager_id',
            'reseller_id',
            'product_id',
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
            'contacts' => app_text('auto.k_dba0fcb2cbbb'),
            'referral_code' => app_text('auto.k_b162c37f62ea'),
            'managers_count' => app_text('auto.k_6756aa53b5b5'),
            'users_count' => app_text('auto.k_0f0b8f55edcc'),
            'state' => app_text('auto.k_f7f293b5c58c'),
        ],
        'managers' => [
            'id' => 'ID',
            'name' => app_text('auto.k_8d98911527e4'),
            'reseller_name' => app_text('auto.k_86469fea3a4a'),
            'contacts' => app_text('auto.k_dba0fcb2cbbb'),
            'referral_code' => app_text('auto.k_b162c37f62ea'),
            'default_platforms' => app_text('auto.k_89009febe5c6'),
            'users_count' => app_text('auto.k_0f0b8f55edcc'),
            'state' => app_text('auto.k_f7f293b5c58c'),
        ],
        'users' => [
            'id' => 'ID',
            'display_name' => app_text('auto.k_51aff1853949'),
            'platform_accounts_summary' => app_text('auto.k_89009febe5c6'),
            'reseller_name' => app_text('auto.k_86469fea3a4a'),
            'manager_name' => app_text('auto.k_8d98911527e4'),
            'status' => app_text('auto.k_f7f293b5c58c'),
            'created_at' => app_text('auto.k_33415c6ac49e'),
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
            'slug' => 'Slug',
            'products_count' => app_text('auto.k_c85756a1ae45'),
            'sort_order' => app_text('auto.k_c00d5a4cbda0'),
            'state' => app_text('auto.k_f7f293b5c58c'),
        ],
        'products' => [
            'id' => 'ID',
            'image_preview' => app_text('auto.k_fb8ffc7377b8'),
            'title' => app_text('auto.k_82a9ca014bb8'),
            'category_title' => app_text('auto.k_19c85838e63f'),
            'media_summary' => app_text('auto.k_198be2a9a816'),
            'price' => app_text('auto.k_367e2792c179'),
            'sort_order' => app_text('auto.k_c00d5a4cbda0'),
            'state' => app_text('auto.k_f7f293b5c58c'),
        ],
        'tests' => [
            'id' => 'ID',
            'title' => app_text('auto.k_ec1868c5a7fb'),
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
            'platform' => app_text('auto.k_89009febe5c6'),
            'target_type' => app_text('auto.k_e9476ab1820b'),
            'scheduled_at' => app_text('auto.k_725347e42525'),
            'status' => app_text('auto.k_f7f293b5c58c'),
        ],
        'content' => [
            'id' => 'ID',
            'image_preview' => app_text('auto.k_fb8ffc7377b8'),
            'title' => app_text('auto.k_19114f713f60'),
            'content_type' => app_text('auto.k_d25691ca401e'),
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
            'state' => app_text('auto.k_f7f293b5c58c'),
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

    foreach (['reseller_id', 'manager_id'] as $column) {
        $where = str_replace('WHERE ' . $column, 'WHERE ' . $alias . '.' . $column, $where);
        $where = str_replace('AND ' . $column, 'AND ' . $alias . '.' . $column, $where);
        $where = str_replace('OR ' . $column, 'OR ' . $alias . '.' . $column, $where);
    }

    return [$where, $params];
}

function crud_list_query(string $moduleKey, array $module, array $admin): array
{
    if ($moduleKey === 'users') {
        [$where, $params] = scoped_where_with_alias(scope_where_for_users($admin), 'eu');
        $where = $where
            ? $where . ' AND eu.merged_into_user_id IS NULL'
            : 'WHERE eu.merged_into_user_id IS NULL';
        return [
            "SELECT eu.id, CONCAT_WS(' ', NULLIF(eu.first_name, ''), NULLIF(eu.last_name, '')) AS full_name,
                    eu.username, eu.platform, eu.platform_user_id, eu.status, eu.created_at,
                    r.name AS reseller_name, m.name AS manager_name,
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
            "SELECT l.id, l.status, l.source_platform, l.message, l.created_at,
                    CONCAT_WS(' ', NULLIF(eu.first_name, ''), NULLIF(eu.last_name, '')) AS full_name,
                    eu.username AS user_username,
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
        return [
            "SELECT r.id, r.name, r.email, r.phone, r.referral_code,
                    IF(r.is_active = 1, 'active', 'inactive') AS state,
                    COUNT(DISTINCT m.id) AS managers_count,
                    COUNT(DISTINCT eu.id) AS users_count
             FROM resellers r
             LEFT JOIN managers m ON m.reseller_id = r.id
             LEFT JOIN end_users eu ON eu.reseller_id = r.id
             GROUP BY r.id
             ORDER BY r.id DESC
             LIMIT 100",
            [],
        ];
    }

    if ($moduleKey === 'managers') {
        [$where, $params] = scoped_where_with_alias(scope_where_for_module($moduleKey, $admin), 'm');
        return [
            "SELECT m.id, m.name, m.email, m.phone, m.referral_code,
                    IF(m.is_active = 1, 'active', 'inactive') AS state,
                    r.name AS reseller_name,
                    GROUP_CONCAT(DISTINCT dpm.platform ORDER BY FIELD(dpm.platform, 'telegram', 'VK', 'OK', 'MAX', 'web') SEPARATOR ',') AS default_platforms,
                    COUNT(DISTINCT eu.id) AS users_count
             FROM managers m
             LEFT JOIN resellers r ON r.id = m.reseller_id
             LEFT JOIN end_users eu ON eu.manager_id = m.id
             LEFT JOIN default_platform_managers dpm ON dpm.manager_id = m.id AND dpm.is_active = 1
             $where
             GROUP BY m.id
             ORDER BY m.id DESC
             LIMIT 100",
            $params,
        ];
    }

    if ($moduleKey === 'categories') {
        [$where, $params] = owner_scope_condition($admin, 'c');
        return [
            "SELECT c.id, c.title, c.slug, c.sort_order,
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
        [$where, $params] = owner_scope_condition($admin, 'p');
        return [
            "SELECT p.id, p.title, c.title AS category_title, p.image_path, p.document_path, p.video_url, p.purchase_url, p.price, p.sort_order,
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
        [$where, $params] = owner_scope_condition($admin, 't');
        return [
            "SELECT t.id, t.title, t.scoring_type, c.title AS category_title, t.sort_order,
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
        [$where, $params] = owner_scope_condition($admin);
        return [
            "SELECT id, title, platform, target_type, scheduled_at, status FROM broadcasts $where ORDER BY id DESC LIMIT 100",
            $params,
        ];
    }

    if ($moduleKey === 'content') {
        [$where, $params] = owner_scope_condition($admin, 'cp');
        return [
            "SELECT cp.id, cp.title, cp.content_type, c.title AS category_title, cp.image_path,
                    cp.attachment_path, cp.video_url, cp.button_url, cp.status, cp.publish_at
             FROM content_posts cp
             LEFT JOIN product_categories c ON c.id = cp.category_id
             $where
             ORDER BY cp.id DESC
             LIMIT 100",
            $params,
        ];
    }

    if ($moduleKey === 'integrations') {
        [$where, $params] = integration_scope_condition($admin);
        return [
            "SELECT id, title, CONCAT(owner_type, ' #', owner_id) AS owner_label, platform, external_id, IF(is_active = 1, 'active', 'inactive') AS state
             FROM messaging_integrations
             $where
             ORDER BY id DESC
             LIMIT 100",
            $params,
        ];
    }

    $columnsSql = implode(', ', array_map(static fn($column) => "`$column`", $module['columns']));
    [$where, $params] = scope_where_for_module($moduleKey, $admin);
    return ["SELECT $columnsSql FROM {$module['table']} $where ORDER BY id DESC LIMIT 100", $params];
}

function crud_cell_value(string $moduleKey, string $column, array $row): string
{
    if ($column === 'contacts') {
        return trim(($row['email'] ?? '') . "\n" . ($row['phone'] ?? '')) ?: app_text('auto.k_1b93795b9768');
    }

    if ($column === 'display_name' || $column === 'user_name') {
        return trim(($row['full_name'] ?? '') ?: ($row['user_username'] ?? '') ?: ($row['username'] ?? '')) ?: app_text('auto.k_1b93795b9768');
    }

    if ($column === 'platform_account') {
        return trim(($row['platform'] ?? '') . "\n" . ($row['platform_user_id'] ?? '')) ?: app_text('auto.k_1b93795b9768');
    }

    if ($column === 'platform_profile') {
        return trim((string)($row['display_name'] ?? ''))
            ?: trim((string)(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')))
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

    return format_cell_value($row[$column] ?? null);
}

function status_badge_class(string $value): string
{
    return match ($value) {
        'new', 'none', status_label('none') => 'badge badge-new',
        'contacted', 'sent', status_label('contacted'), status_label('sent') => 'badge badge-sent',
        'interested', 'pending', status_label('interested'), status_label('pending') => 'badge badge-pending',
        'closed', status_label('closed') => 'badge badge-closed',
        'lost', 'failed', status_label('lost'), status_label('failed') => 'badge badge-failed',
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
            $html[] = render_platform_badge($platform) . '<div class="cell-muted">' . h($platformUserId) . '</div>';
        }
        return implode('', $html);
    }

    if ($key === 'default_platforms') {
        $platforms = array_filter(explode(',', (string)($row[$key] ?? '')));
        return $platforms
            ? implode(' ', array_map(static fn($platform) => render_platform_badge($platform), $platforms))
            : '—';
    }

    if ($key === 'platform') {
        return render_platform_badge((string)($row['platform'] ?? ''));
    }

    if ($moduleKey === 'tests' && $key === 'test_type') {
        $type = (string)($row['scoring_type'] ?? 'single');
        $class = $type === 'multiscale' ? 'badge badge-pending' : 'badge';
        return '<span class="' . h($class) . '">' . h(crud_cell_value($moduleKey, $key, $row)) . '</span>';
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

function render_lead_cards(array $rows, bool $canEdit, bool $canDelete): string
{
    ob_start();
    ?>
    <div class="lead-card-list">
        <?php foreach ($rows as $row): ?>
            <?php
            $status = status_label((string)($row['status'] ?? 'new'));
            $response = crud_cell_value('leads', 'response_summary', $row);
            $responseFirstLine = strtok($response, "\n") ?: $response;
            $responseRest = trim(substr($response, strlen($responseFirstLine)));
            $message = trim((string)($row['message'] ?? '')) ?: app_text('auto.k_503360e76342');
            $user = crud_cell_value('leads', 'user_name', $row);
            $responseCount = (int)($row['response_count'] ?? 0);
            $lastResponseText = trim((string)($row['last_response_text'] ?? ''));
            $lastResponseText = mb_strlen($lastResponseText, 'UTF-8') > 180 ? mb_substr($lastResponseText, 0, 180, 'UTF-8') . '...' : $lastResponseText;
            ?>
            <article class="lead-card">
                <div class="lead-card-main">
                    <div class="lead-card-top">
                        <span class="<?= h(status_badge_class($status)) ?>"><?= h($status) ?></span>
                        <?= render_platform_badge((string)($row['source_platform'] ?? '')) ?>
                        <span class="cell-muted">#<?= (int)$row['id'] ?> · <?= h((string)($row['created_at'] ?? '')) ?></span>
                    </div>
                    <h3><?= h($user) ?></h3>
                    <p class="lead-card-message"><?= nl2br(h($message)) ?></p>
                    <div class="lead-card-meta">
                        <span><?= h(app_text('auto.k_82a9ca014bb8')) ?>: <?= h((string)($row['product_title'] ?: app_text('auto.k_1b93795b9768'))) ?></span>
                        <span><?= h(app_text('auto.k_8d98911527e4')) ?>: <?= h((string)($row['manager_name'] ?: app_text('auto.k_1b93795b9768'))) ?></span>
                        <span><?= h(app_text('auto.k_86469fea3a4a')) ?>: <?= h((string)($row['reseller_name'] ?: app_text('auto.k_1b93795b9768'))) ?></span>
                        <span><?= h(app_text('auto.k_e9d7bdd83831')) ?>: <span class="<?= h(status_badge_class($responseFirstLine)) ?>"><?= h($responseFirstLine) ?></span></span>
                        <span><?= h(app_text('lead_response.count_label')) ?>: <?= $responseCount ?></span>
                        <?php if ($responseRest !== ''): ?>
                            <span class="cell-muted"><?= nl2br(h($responseRest)) ?></span>
                        <?php endif; ?>
                        <?php if ($lastResponseText !== ''): ?>
                            <span class="lead-last-response"><?= nl2br(h($lastResponseText)) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($canEdit || $canDelete): ?>
                    <div class="lead-card-actions">
                        <?php if ($canEdit): ?>
                            <a class="button" href="crud.php?module=leads&action=edit&id=<?= (int)$row['id'] ?>"><?= h(crud_action_label('leads')) ?></a>
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

function render_crud_list(string $moduleKey, array $columns, array $rows, bool $canEdit, bool $canDelete): string
{
    ob_start();
    ?>
    <?php if ($moduleKey === 'leads'): ?>
        <?= render_lead_filters() ?>
    <?php endif; ?>
    <div class="table-summary"><?= h(app_text('auto.k_b1062a5651c3')) ?><?= count($rows) ?></div>
    <?php if ($rows): ?>
        <?php if ($moduleKey === 'leads'): ?>
            <?= render_lead_cards($rows, $canEdit, $canDelete) ?>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <?php foreach ($columns as $label): ?>
                        <th><?= h($label) ?></th>
                    <?php endforeach; ?>
                    <?php if ($canEdit || $canDelete): ?>
                        <th><?= h(app_text('auto.k_9978ac34b293')) ?></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php foreach ($columns as $key => $label): ?>
                            <td><?= render_cell($moduleKey, $key, $row) ?></td>
                        <?php endforeach; ?>
                        <?php if ($canEdit || $canDelete): ?>
                            <td class="row-actions">
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
    <?php endif; ?>
    <?php
    return trim(ob_get_clean());
}
