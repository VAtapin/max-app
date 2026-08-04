<?php

function team_reseller_rows(bool $activeOnly = false): array
{
    static $cache = [];
    $key = $activeOnly ? 'active' : 'all';
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $sql = 'SELECT id, parent_reseller_id, name, is_active,
                   manager_limit, direct_leader_limit, branch_leader_limit,
                   direct_manager_limit, branch_manager_limit, per_child_manager_limit,
                   price_per_leader, price_per_consultant
            FROM resellers';
    if ($activeOnly) {
        $sql .= ' WHERE is_active = 1';
    }
    $sql .= ' ORDER BY parent_reseller_id IS NOT NULL, parent_reseller_id, name, id';

    $rows = [];
    try {
        foreach (db()->query($sql)->fetchAll() as $row) {
            $rows[(int)$row['id']] = $row;
        }
    } catch (Throwable $e) {
        foreach (db()->query('SELECT id, name, is_active, manager_limit FROM resellers ORDER BY name, id')->fetchAll() as $row) {
            $row += [
                'parent_reseller_id' => null,
                'direct_leader_limit' => null,
                'branch_leader_limit' => null,
                'direct_manager_limit' => $row['manager_limit'] ?? null,
                'branch_manager_limit' => $row['manager_limit'] ?? null,
                'per_child_manager_limit' => null,
                'price_per_leader' => null,
                'price_per_consultant' => null,
            ];
            if (!$activeOnly || (int)$row['is_active'] === 1) {
                $rows[(int)$row['id']] = $row;
            }
        }
    }

    $cache[$key] = $rows;
    return $rows;
}

function team_reseller_row(int $resellerId): ?array
{
    $rows = team_reseller_rows();
    return $rows[$resellerId] ?? null;
}

function team_children_map(bool $activeOnly = false): array
{
    $map = [];
    foreach (team_reseller_rows($activeOnly) as $id => $row) {
        $parentId = !empty($row['parent_reseller_id']) ? (int)$row['parent_reseller_id'] : 0;
        $map[$parentId][] = (int)$id;
    }

    return $map;
}

function team_reseller_branch_ids(int $resellerId, bool $includeSelf = true, bool $activeOnly = false): array
{
    if ($resellerId <= 0) {
        return [];
    }

    $children = team_children_map($activeOnly);
    $result = $includeSelf ? [$resellerId] : [];
    $queue = [$resellerId];
    $seen = [$resellerId => true];

    while ($queue) {
        $current = array_shift($queue);
        foreach ($children[$current] ?? [] as $childId) {
            if (isset($seen[$childId])) {
                continue;
            }
            $seen[$childId] = true;
            $result[] = $childId;
            $queue[] = $childId;
        }
    }

    return $result;
}

function team_reseller_ancestor_ids(int $resellerId, bool $includeSelf = true): array
{
    if ($resellerId <= 0) {
        return [];
    }

    $rows = team_reseller_rows();
    $result = [];
    $currentId = $resellerId;
    $seen = [];

    while ($currentId > 0 && isset($rows[$currentId]) && !isset($seen[$currentId])) {
        $seen[$currentId] = true;
        $result[] = $currentId;
        $parentId = !empty($rows[$currentId]['parent_reseller_id']) ? (int)$rows[$currentId]['parent_reseller_id'] : 0;
        $currentId = $parentId;
    }

    if (!$includeSelf) {
        array_shift($result);
    }

    return array_reverse($result);
}

function team_owner_chain_for_reseller(?int $resellerId): array
{
    $owners = [['owner_type' => null, 'owner_id' => null]];
    if ($resellerId) {
        foreach (team_reseller_ancestor_ids($resellerId, true) as $ancestorId) {
            $owners[] = ['owner_type' => 'reseller', 'owner_id' => $ancestorId];
        }
    }

    return $owners;
}

function team_owner_chain_for_manager(?int $managerId, ?int $resellerId = null): array
{
    if ($resellerId === null && $managerId) {
        $resellerId = team_manager_reseller_id($managerId);
    }

    $owners = team_owner_chain_for_reseller($resellerId);
    if ($managerId) {
        $owners[] = ['owner_type' => 'manager', 'owner_id' => $managerId];
    }

    return $owners;
}

function team_owner_chain_for_admin(array $admin): array
{
    if (($admin['role'] ?? '') === 'manager') {
        return team_owner_chain_for_manager(
            !empty($admin['manager_id']) ? (int)$admin['manager_id'] : null,
            !empty($admin['reseller_id']) ? (int)$admin['reseller_id'] : null
        );
    }

    if (($admin['role'] ?? '') === 'reseller') {
        return team_owner_chain_for_reseller(!empty($admin['reseller_id']) ? (int)$admin['reseller_id'] : null);
    }

    return [];
}

function team_owner_chain_for_user(array $user): array
{
    if (!empty($user['manager_id'])) {
        return team_owner_chain_for_manager((int)$user['manager_id'], !empty($user['reseller_id']) ? (int)$user['reseller_id'] : null);
    }

    return team_owner_chain_for_reseller(!empty($user['reseller_id']) ? (int)$user['reseller_id'] : null);
}

function team_sql_in_condition(string $column, array $ids, string $paramPrefix): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
    if (!$ids) {
        return ['1=0', []];
    }

    $placeholders = [];
    $params = [];
    foreach ($ids as $index => $id) {
        $name = $paramPrefix . '_' . $index;
        $placeholders[] = ':' . $name;
        $params[$name] = $id;
    }

    return [$column . ' IN (' . implode(', ', $placeholders) . ')', $params];
}

function team_manager_reseller_id(int $managerId): ?int
{
    if ($managerId <= 0) {
        return null;
    }

    $stmt = db()->prepare('SELECT reseller_id FROM managers WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $managerId]);
    $resellerId = $stmt->fetchColumn();

    return $resellerId !== false && $resellerId !== null ? (int)$resellerId : null;
}

function team_manager_ids_for_resellers(array $resellerIds, bool $activeOnly = false): array
{
    [$where, $params] = team_sql_in_condition('reseller_id', $resellerIds, 'team_manager_reseller');
    $sql = 'SELECT id FROM managers WHERE ' . $where;
    if ($activeOnly) {
        $sql .= ' AND is_active = 1';
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function team_is_reseller_in_branch(int $rootId, int $candidateId, bool $includeSelf = true): bool
{
    return in_array($candidateId, team_reseller_branch_ids($rootId, $includeSelf), true);
}

function team_is_manager_in_branch(int $rootId, int $managerId): bool
{
    $resellerId = team_manager_reseller_id($managerId);
    return $resellerId !== null && team_is_reseller_in_branch($rootId, $resellerId, true);
}

function team_direct_manager_count(int $resellerId, ?int $excludeManagerId = null, bool $activeOnly = true): int
{
    $params = ['reseller_id' => $resellerId];
    $sql = 'SELECT COUNT(*) FROM managers WHERE reseller_id = :reseller_id';
    if ($activeOnly) {
        $sql .= ' AND is_active = 1';
    }
    if ($excludeManagerId) {
        $sql .= ' AND id <> :exclude_manager_id';
        $params['exclude_manager_id'] = $excludeManagerId;
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function team_branch_manager_count(int $resellerId, ?int $excludeManagerId = null, bool $activeOnly = true): int
{
    $branchIds = team_reseller_branch_ids($resellerId, true);
    [$where, $params] = team_sql_in_condition('reseller_id', $branchIds, 'team_branch_manager');
    $sql = 'SELECT COUNT(*) FROM managers WHERE ' . $where;
    if ($activeOnly) {
        $sql .= ' AND is_active = 1';
    }
    if ($excludeManagerId) {
        $sql .= ' AND id <> :exclude_manager_id';
        $params['exclude_manager_id'] = $excludeManagerId;
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function team_direct_leader_count(int $resellerId, ?int $excludeResellerId = null, bool $activeOnly = true): int
{
    $params = ['parent_reseller_id' => $resellerId];
    $sql = 'SELECT COUNT(*) FROM resellers WHERE parent_reseller_id = :parent_reseller_id';
    if ($activeOnly) {
        $sql .= ' AND is_active = 1';
    }
    if ($excludeResellerId) {
        $sql .= ' AND id <> :exclude_reseller_id';
        $params['exclude_reseller_id'] = $excludeResellerId;
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function team_branch_leader_count(int $resellerId, ?int $excludeResellerId = null, bool $activeOnly = true): int
{
    $branchIds = team_reseller_branch_ids($resellerId, false, $activeOnly);
    if ($excludeResellerId) {
        $branchIds = array_values(array_filter($branchIds, static fn(int $id): bool => $id !== $excludeResellerId));
    }

    return count($branchIds);
}

function team_branch_user_count(int $resellerId): int
{
    $branchIds = team_reseller_branch_ids($resellerId, true);
    [$where, $params] = team_sql_in_condition('eu.reseller_id', $branchIds, 'team_branch_user');
    $stmt = db()->prepare('SELECT COUNT(*) FROM end_users eu WHERE ' . $where . ' AND eu.merged_into_user_id IS NULL');
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function team_branch_summary(int $resellerId): array
{
    return [
        'direct_leaders' => team_direct_leader_count($resellerId),
        'branch_leaders' => team_branch_leader_count($resellerId),
        'direct_consultants' => team_direct_manager_count($resellerId),
        'branch_consultants' => team_branch_manager_count($resellerId),
        'branch_clients' => team_branch_user_count($resellerId),
    ];
}

function team_reseller_options_for_admin(array $admin, bool $includeSelf = true, ?int $excludeId = null): array
{
    $rows = team_reseller_rows();
    if (($admin['role'] ?? '') === 'superadmin') {
        $ids = array_keys($rows);
    } elseif (($admin['role'] ?? '') === 'reseller' && !empty($admin['reseller_id'])) {
        $ids = team_reseller_branch_ids((int)$admin['reseller_id'], $includeSelf);
    } else {
        $ids = [];
    }

    $options = [];
    foreach ($ids as $id) {
        if ($excludeId !== null && (int)$id === $excludeId) {
            continue;
        }
        if (!isset($rows[$id])) {
            continue;
        }
        $options[] = [
            'id' => (int)$id,
            'label' => team_reseller_label((int)$id),
        ];
    }

    usort($options, static fn(array $a, array $b): int => strnatcasecmp($a['label'], $b['label']));
    return $options;
}

function team_reseller_label(int $resellerId): string
{
    $rows = team_reseller_rows();
    $path = [];
    foreach (team_reseller_ancestor_ids($resellerId, true) as $id) {
        if (isset($rows[$id])) {
            $path[] = (string)$rows[$id]['name'];
        }
    }

    return $path ? implode(' / ', $path) : ('#' . $resellerId);
}

function team_limit_value(array $row, string $field, ?string $fallbackField = null): ?int
{
    if (isset($row[$field]) && $row[$field] !== null && $row[$field] !== '') {
        return (int)$row[$field];
    }

    if ($fallbackField !== null && isset($row[$fallbackField]) && $row[$fallbackField] !== null && $row[$fallbackField] !== '') {
        return (int)$row[$fallbackField];
    }

    return null;
}
