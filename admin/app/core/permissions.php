<?php

function can_manage(string $module, array $user): bool
{
    if ($user['role'] === 'superadmin') {
        return true;
    }

    $resellerModules = ['dashboard', 'managers', 'users', 'platform_accounts', 'broadcasts', 'leads'];
    $managerModules = ['dashboard', 'users', 'platform_accounts', 'leads'];

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
        return ['WHERE reseller_id = :reseller_id', ['reseller_id' => $user['reseller_id']]];
    }

    return ['WHERE manager_id = :manager_id', ['manager_id' => $user['manager_id']]];
}

function scope_where_for_leads(array $user): array
{
    if ($user['role'] === 'superadmin') {
        return ['', []];
    }

    if ($user['role'] === 'reseller') {
        return ['WHERE reseller_id = :reseller_id', ['reseller_id' => $user['reseller_id']]];
    }

    return ['WHERE manager_id = :manager_id', ['manager_id' => $user['manager_id']]];
}
