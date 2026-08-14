<?php

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

    if ($moduleKey === 'resellers' && $admin['role'] === 'reseller') {
        [$where, $params] = team_sql_in_condition('id', team_reseller_branch_ids((int)$admin['reseller_id'], true), 'scope_reseller_branch');
        return ['WHERE ' . $where, $params];
    }

    if ($moduleKey === 'managers' && $admin['role'] === 'reseller') {
        [$where, $params] = team_sql_in_condition('reseller_id', team_reseller_branch_ids((int)$admin['reseller_id'], true), 'scope_manager_branch');
        return ['WHERE ' . $where, $params];
    }

    if ($moduleKey === 'site_templates') {
        return site_template_admin_scope_condition($admin);
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
    return owned_content_config_keys();
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
