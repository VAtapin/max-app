<?php

function select_options(string $source, array $admin): array
{
    $allowed = [
        'resellers' => ['table' => 'resellers', 'label' => 'name'],
        'managers' => ['table' => 'managers', 'label' => 'name'],
        'end_users' => ['table' => 'end_users', 'label' => 'platform_user_id'],
        'products' => ['table' => 'products', 'label' => 'title'],
        'product_variants' => ['table' => 'product_variants', 'label' => 'sku'],
        'recommendation_signals' => ['table' => 'recommendation_signals', 'label' => 'title'],
        'product_categories' => ['table' => 'product_categories', 'label' => 'title'],
        'content_posts' => ['table' => 'content_posts', 'label' => 'title'],
        'tests' => ['table' => 'tests', 'label' => 'title'],
        'site_templates' => ['table' => 'site_templates', 'label' => 'title'],
        'subscription_plans' => ['table' => 'subscription_plans', 'label' => 'title'],
    ];
    if (!isset($allowed[$source])) {
        return [];
    }

    if ($source === 'site_templates') {
        return site_template_options($admin);
    }

    $item = $allowed[$source];
    $where = '';
    $params = [];
    if ($source === 'resellers') {
        $options = team_reseller_options_for_admin($admin, true);
        if ($options) {
            return array_map(static fn(array $option): array => [
                'id' => (int)$option['id'],
                'label' => (string)$option['label'],
            ], $options);
        }
    }
    if ($source === 'managers' && $admin['role'] === 'reseller') {
        $branchIds = team_reseller_branch_ids((int)$admin['reseller_id'], true);
        [$whereSql, $params] = team_sql_in_condition('reseller_id', $branchIds, 'select_manager_branch');
        $where = 'WHERE ' . $whereSql;
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
    if ($source === 'subscription_plans') {
        return subscription_plan_options(true, $admin);
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
    if ($fieldName === 'document_type') {
        return legal_document_type_labels()[$option] ?? $option;
    }

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

    if ($fieldName === 'segment_stage') {
        return client_stage_labels()[$option] ?? $option;
    }

    if ($fieldName === 'gender') {
        return client_gender_labels()[$option] ?? $option;
    }

    if ($fieldName === 'audience_type') {
        return $option === 'consultants' ? 'Консультанты команды' : 'Клиенты';
    }

    if ($fieldName === 'segment_checkup') {
        return [
            'not_started' => 'Чек-ап не начат',
            'started' => 'Чек-ап начат',
            'completed' => 'Чек-ап завершен',
        ][$option] ?? $option;
    }

    if ($fieldName === 'segment_activity') {
        return [
            'active_7' => 'Активны за 7 дней',
            'active_30' => 'Активны за 30 дней',
            'inactive_14' => 'Неактивны 14 дней',
            'inactive_30' => 'Неактивны 30 дней',
        ][$option] ?? $option;
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
        if (!empty($field['virtual'])) {
            continue;
        }
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

    if ($moduleKey === 'integrations' && trim((string)($payload['callback_secret'] ?? '')) === '') {
        $payload['callback_secret'] = integration_callback_secret();
    }

    if ($moduleKey === 'site_templates') {
        $payload = site_template_normalize_payload($payload);
    }

    return $payload;
}
