<?php

require_once __DIR__ . '/team_tree.php';

function can_manage(string $module, array $user): bool
{
    if ($user['role'] === 'superadmin') {
        return true;
    }

    $resellerModules = ['dashboard', 'account', 'my_page', 'resellers', 'managers', 'users', 'results', 'broadcasts', 'leads', 'categories', 'products', 'content', 'site_templates', 'subscriptions', 'billing_self', 'integrations'];
    $managerModules = ['dashboard', 'account', 'my_page', 'users', 'results', 'leads', 'categories', 'products', 'content', 'site_templates', 'broadcasts', 'billing_self', 'integrations'];

    if ($user['role'] === 'reseller') {
        return in_array($module, $resellerModules, true);
    }

    if ($user['role'] === 'manager') {
        return in_array($module, $managerModules, true);
    }

    return false;
}

function scope_where_for_users(array $user): array
{
    if ($user['role'] === 'superadmin') {
        return ['', []];
    }

    if ($user['role'] === 'reseller') {
        $branchIds = team_reseller_branch_ids((int)$user['reseller_id'], true);
        [$resellerSql, $resellerParams] = team_sql_in_condition('reseller_id', $branchIds, 'scope_reseller');
        $managerIds = team_manager_ids_for_resellers($branchIds);
        [$managerSql, $managerParams] = team_sql_in_condition('manager_id', $managerIds, 'scope_manager');

        return [
            'WHERE (' . $resellerSql . ' OR ' . $managerSql . ')',
            $resellerParams + $managerParams,
        ];
    }

    return ['WHERE manager_id = :manager_id', ['manager_id' => $user['manager_id']]];
}

function scope_where_for_leads(array $user): array
{
    if ($user['role'] === 'superadmin') {
        return ['', []];
    }

    if ($user['role'] === 'reseller') {
        $branchIds = team_reseller_branch_ids((int)$user['reseller_id'], true);
        [$resellerSql, $resellerParams] = team_sql_in_condition('reseller_id', $branchIds, 'scope_reseller');
        $managerIds = team_manager_ids_for_resellers($branchIds);
        [$managerSql, $managerParams] = team_sql_in_condition('manager_id', $managerIds, 'scope_manager');

        return [
            'WHERE (' . $resellerSql . ' OR ' . $managerSql . ')',
            $resellerParams + $managerParams,
        ];
    }

    return ['WHERE manager_id = :manager_id', ['manager_id' => $user['manager_id']]];
}
