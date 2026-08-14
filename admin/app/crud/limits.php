<?php

function manager_reseller_id(int $managerId): ?int
{
    return team_manager_reseller_id($managerId);
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

    $errors = [];
    $leader = team_reseller_row($resellerId);
    if ($leader) {
        $directLimit = team_limit_value($leader, 'direct_manager_limit', 'manager_limit');
        if ($directLimit !== null) {
            $directActive = team_direct_manager_count($resellerId, $recordId);
            if ($directActive + 1 > $directLimit) {
                $errors[] = 'У лидера "' . team_reseller_label($resellerId) . '" заполнен лимит прямых консультантов: ' . $directActive . ' из ' . $directLimit . '.';
            }
        }
    }

    foreach (team_reseller_ancestor_ids($resellerId, true) as $ancestorId) {
        $ancestor = team_reseller_row($ancestorId);
        if (!$ancestor) {
            continue;
        }

        $branchLimit = team_limit_value($ancestor, 'branch_manager_limit', 'manager_limit');
        if ($branchLimit !== null) {
            $branchActive = team_branch_manager_count($ancestorId, $recordId);
            if ($branchActive + 1 > $branchLimit) {
                $errors[] = 'У лидера "' . team_reseller_label($ancestorId) . '" заполнен лимит консультантов всей ветки: ' . $branchActive . ' из ' . $branchLimit . '.';
            }
        }

        $parent = team_reseller_row($ancestorId);
        $parentId = !empty($parent['parent_reseller_id']) ? (int)$parent['parent_reseller_id'] : null;
        if ($parentId) {
            $parentRow = team_reseller_row($parentId);
            $perChildLimit = $parentRow ? team_limit_value($parentRow, 'per_child_manager_limit') : null;
            if ($perChildLimit !== null) {
                $childBranchActive = team_branch_manager_count($ancestorId, $recordId);
                if ($childBranchActive + 1 > $perChildLimit) {
                    $errors[] = 'У лидера "' . team_reseller_label($parentId) . '" заполнен лимит консультантов на дочернего лидера "' . team_reseller_label($ancestorId) . '": ' . $childBranchActive . ' из ' . $perChildLimit . '.';
                }
            }
        }
    }

    return array_values(array_unique($errors));
}

function validate_leader_limit_payload(string $moduleKey, array $payload, ?int $recordId = null): array
{
    if ($moduleKey !== 'resellers') {
        return [];
    }

    $errors = [];
    $isActive = (int)($payload['is_active'] ?? 0) === 1;
    $parentId = nullable_int_value($payload['parent_reseller_id'] ?? null);

    if ($isActive && $parentId) {
        $parent = team_reseller_row($parentId);
        if ($parent) {
            $directLimit = team_limit_value($parent, 'direct_leader_limit');
            if ($directLimit !== null) {
                $directActive = team_direct_leader_count($parentId, $recordId);
                if ($directActive + 1 > $directLimit) {
                    $errors[] = 'У вышестоящего лидера "' . team_reseller_label($parentId) . '" заполнен лимит прямых лидеров: ' . $directActive . ' из ' . $directLimit . '.';
                }
            }
        }

        foreach (team_reseller_ancestor_ids($parentId, true) as $ancestorId) {
            $ancestor = team_reseller_row($ancestorId);
            if (!$ancestor) {
                continue;
            }

            $branchLimit = team_limit_value($ancestor, 'branch_leader_limit');
            if ($branchLimit !== null) {
                $branchActive = team_branch_leader_count($ancestorId, $recordId);
                if ($branchActive + 1 > $branchLimit) {
                    $errors[] = 'У лидера "' . team_reseller_label($ancestorId) . '" заполнен лимит лидеров всей ветки: ' . $branchActive . ' из ' . $branchLimit . '.';
                }
            }
        }
    }

    if ($recordId) {
        $checks = [
            'direct_leader_limit' => ['count' => team_direct_leader_count($recordId), 'label' => 'прямых лидеров'],
            'branch_leader_limit' => ['count' => team_branch_leader_count($recordId), 'label' => 'лидеров всей ветки'],
            'direct_manager_limit' => ['count' => team_direct_manager_count($recordId), 'label' => 'прямых консультантов'],
            'branch_manager_limit' => ['count' => team_branch_manager_count($recordId), 'label' => 'консультантов всей ветки'],
            'manager_limit' => ['count' => team_direct_manager_count($recordId), 'label' => 'прямых консультантов'],
        ];

        foreach ($checks as $field => $check) {
            $limit = nullable_int_value($payload[$field] ?? null);
            if ($limit !== null && $check['count'] > $limit) {
                $errors[] = 'Нельзя поставить лимит ' . $limit . ' для ' . $check['label'] . ': сейчас уже ' . $check['count'] . '.';
            }
        }
    }

    return array_values(array_unique($errors));
}

function reseller_parent_id_for_limits(array $payload, ?int $recordId = null): ?int
{
    $parentId = nullable_int_value($payload['parent_reseller_id'] ?? null);
    if ($parentId || !$recordId) {
        return $parentId;
    }

    $stmt = db()->prepare('SELECT parent_reseller_id FROM resellers WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $recordId]);
    $storedParentId = $stmt->fetchColumn();

    return $storedParentId ? (int)$storedParentId : null;
}

function limit_field_value(array $payload, string $field): ?int
{
    $value = nullable_int_value($payload[$field] ?? null);
    return $value !== null && $value >= 0 ? $value : null;
}

function validate_child_limit_caps(string $moduleKey, array $payload, ?int $recordId = null): array
{
    if ($moduleKey !== 'resellers') {
        return [];
    }

    $parentId = reseller_parent_id_for_limits($payload, $recordId);
    if (!$parentId) {
        return [];
    }

    $parent = team_reseller_row($parentId);
    if (!$parent) {
        return [];
    }

    $errors = [];
    $childLimits = [
        'direct_leader_limit' => limit_field_value($payload, 'direct_leader_limit'),
        'branch_leader_limit' => limit_field_value($payload, 'branch_leader_limit'),
        'direct_manager_limit' => limit_field_value($payload, 'direct_manager_limit'),
        'branch_manager_limit' => limit_field_value($payload, 'branch_manager_limit'),
        'per_child_manager_limit' => limit_field_value($payload, 'per_child_manager_limit'),
    ];

    $directLeaderLimit = $childLimits['direct_leader_limit'];
    $branchLeaderLimit = $childLimits['branch_leader_limit'];
    if ($directLeaderLimit !== null && $branchLeaderLimit !== null && $directLeaderLimit > $branchLeaderLimit) {
        $errors[] = 'Лимит прямых лидеров не может быть больше лимита лидеров во всей ветке этого лидера.';
    }

    $directManagerLimit = $childLimits['direct_manager_limit'];
    $branchManagerLimit = $childLimits['branch_manager_limit'];
    if ($directManagerLimit !== null && $branchManagerLimit !== null && $directManagerLimit > $branchManagerLimit) {
        $errors[] = 'Лимит прямых консультантов не может быть больше лимита консультантов во всей ветке этого лидера.';
    }

    $parentDirectLeaderLimit = team_limit_value($parent, 'direct_leader_limit');
    if ($parentDirectLeaderLimit !== null && $directLeaderLimit !== null && $directLeaderLimit > $parentDirectLeaderLimit) {
        $errors[] = 'Лимит прямых лидеров нельзя поставить больше, чем у вышестоящего лидера "' . team_reseller_label($parentId) . '": максимум ' . $parentDirectLeaderLimit . '.';
    }

    $parentBranchLeaderLimit = team_limit_value($parent, 'branch_leader_limit');
    if ($parentBranchLeaderLimit !== null) {
        foreach (['direct_leader_limit' => 'Лимит прямых лидеров', 'branch_leader_limit' => 'Лимит лидеров во всей ветке'] as $field => $label) {
            $value = $childLimits[$field];
            if ($value !== null && $value > $parentBranchLeaderLimit) {
                $errors[] = $label . ' нельзя поставить больше лимита лидеров всей ветки вышестоящего лидера "' . team_reseller_label($parentId) . '": максимум ' . $parentBranchLeaderLimit . '.';
            }
        }
    }

    $parentBranchManagerLimit = team_limit_value($parent, 'branch_manager_limit', 'manager_limit');
    if ($parentBranchManagerLimit !== null) {
        foreach ([
            'direct_manager_limit' => 'Лимит прямых консультантов',
            'branch_manager_limit' => 'Лимит консультантов во всей ветке',
            'per_child_manager_limit' => 'Лимит консультантов на одного дочернего лидера',
        ] as $field => $label) {
            $value = $childLimits[$field];
            if ($value !== null && $value > $parentBranchManagerLimit) {
                $errors[] = $label . ' нельзя поставить больше лимита консультантов всей ветки вышестоящего лидера "' . team_reseller_label($parentId) . '": максимум ' . $parentBranchManagerLimit . '.';
            }
        }
    }

    $parentPerChildManagerLimit = team_limit_value($parent, 'per_child_manager_limit');
    if ($parentPerChildManagerLimit !== null) {
        foreach (['direct_manager_limit' => 'Лимит прямых консультантов', 'branch_manager_limit' => 'Лимит консультантов во всей ветке'] as $field => $label) {
            $value = $childLimits[$field];
            if ($value !== null && $value > $parentPerChildManagerLimit) {
                $errors[] = $label . ' для дочернего лидера нельзя поставить больше правила вышестоящего лидера "' . team_reseller_label($parentId) . '": максимум ' . $parentPerChildManagerLimit . '.';
            }
        }
    }

    return array_values(array_unique($errors));
}

function add_limit_field_cap(array &$caps, string $field, ?int $max, string $source): void
{
    if ($max === null) {
        return;
    }

    if (!isset($caps[$field]) || $max < (int)$caps[$field]['max']) {
        $caps[$field] = [
            'max' => $max,
            'source' => $source,
        ];
    }
}

function child_limit_field_caps(string $moduleKey, array $payload, ?int $recordId = null): array
{
    if ($moduleKey !== 'resellers') {
        return [];
    }

    $parentId = reseller_parent_id_for_limits($payload, $recordId);
    if (!$parentId) {
        return [];
    }

    $parent = team_reseller_row($parentId);
    if (!$parent) {
        return [];
    }

    $parentLabel = team_reseller_label($parentId);
    $caps = [];

    $parentDirectLeaderLimit = team_limit_value($parent, 'direct_leader_limit');
    add_limit_field_cap(
        $caps,
        'direct_leader_limit',
        $parentDirectLeaderLimit,
        'У вышестоящего лидера "' . $parentLabel . '" лимит прямых лидеров: ' . $parentDirectLeaderLimit . '.'
    );

    $parentBranchLeaderLimit = team_limit_value($parent, 'branch_leader_limit');
    foreach (['direct_leader_limit', 'branch_leader_limit'] as $field) {
        add_limit_field_cap(
            $caps,
            $field,
            $parentBranchLeaderLimit,
            'У вышестоящего лидера "' . $parentLabel . '" лимит лидеров во всей ветке: ' . $parentBranchLeaderLimit . '.'
        );
    }

    $parentBranchManagerLimit = team_limit_value($parent, 'branch_manager_limit', 'manager_limit');
    foreach (['direct_manager_limit', 'branch_manager_limit', 'per_child_manager_limit'] as $field) {
        add_limit_field_cap(
            $caps,
            $field,
            $parentBranchManagerLimit,
            'У вышестоящего лидера "' . $parentLabel . '" лимит консультантов во всей ветке: ' . $parentBranchManagerLimit . '.'
        );
    }

    $parentPerChildManagerLimit = team_limit_value($parent, 'per_child_manager_limit');
    foreach (['direct_manager_limit', 'branch_manager_limit'] as $field) {
        add_limit_field_cap(
            $caps,
            $field,
            $parentPerChildManagerLimit,
            'У вышестоящего лидера "' . $parentLabel . '" лимит консультантов на дочернего лидера: ' . $parentPerChildManagerLimit . '.'
        );
    }

    return $caps;
}

function create_limit_block_reasons(string $moduleKey, array $admin): array
{
    if (($admin['role'] ?? '') !== 'reseller' || empty($admin['reseller_id'])) {
        return [];
    }

    $resellerId = (int)$admin['reseller_id'];
    if ($moduleKey === 'resellers') {
        return validate_leader_limit_payload('resellers', [
            'parent_reseller_id' => $resellerId,
            'is_active' => 1,
        ]);
    }

    if ($moduleKey === 'managers') {
        return validate_manager_limit_payload('managers', [
            'reseller_id' => $resellerId,
            'is_active' => 1,
        ]);
    }

    return [];
}

function apply_role_defaults(string $moduleKey, array $payload, array $admin, ?int $recordId = null): array
{
    if (in_array($moduleKey, ['users', 'leads'], true) && !empty($payload['manager_id'])) {
        $payload['reseller_id'] = manager_reseller_id((int)$payload['manager_id']);
    }

    if ($admin['role'] === 'reseller' && $moduleKey === 'resellers') {
        if ($recordId) {
            unset($payload['parent_reseller_id']);
        } else {
            $payload['parent_reseller_id'] = $admin['reseller_id'];
        }
    }
    if ($admin['role'] === 'reseller' && in_array($moduleKey, ['managers', 'users', 'leads'], true)) {
        $resellerId = nullable_int_value($payload['reseller_id'] ?? null);
        if (!$resellerId || !team_is_reseller_in_branch((int)$admin['reseller_id'], $resellerId, true)) {
            $payload['reseller_id'] = $admin['reseller_id'];
        }
    }
    if ($admin['role'] === 'manager' && in_array($moduleKey, ['users', 'leads'], true)) {
        $payload['manager_id'] = $admin['manager_id'];
        $payload['reseller_id'] = $admin['reseller_id'];
    }
    if (in_array($moduleKey, owned_modules(), true) && $admin['role'] !== 'superadmin') {
        $payload['owner_type'] = $admin['role'];
        $payload['owner_id'] = $admin['role'] === 'reseller' ? $admin['reseller_id'] : $admin['manager_id'];
    }
    if ($moduleKey === 'site_templates') {
        if ($recordId) {
            unset($payload['owner_type'], $payload['owner_id'], $payload['source_template_id']);
        } elseif ($admin['role'] === 'reseller') {
            $payload['owner_type'] = 'reseller';
            $payload['owner_id'] = $admin['reseller_id'];
            $payload['source_template_id'] = null;
        } elseif ($admin['role'] === 'manager') {
            $payload['owner_type'] = 'manager';
            $payload['owner_id'] = $admin['manager_id'];
            $payload['source_template_id'] = null;
        } else {
            $payload['owner_type'] = null;
            $payload['owner_id'] = null;
            $payload['source_template_id'] = null;
        }
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
            $allowedTargets = [
                'own_clients',
                'branch_clients',
                'direct_consultants',
                'branch_consultants',
                'direct_leaders',
                'branch_leaders',
            ];
            $targetType = (string)($payload['target_type'] ?? '');
            $payload['target_type'] = in_array($targetType, $allowedTargets, true)
                ? $targetType
                : 'own_clients';
            $payload['audience_type'] = in_array($payload['target_type'], ['own_clients', 'branch_clients'], true)
                ? 'clients'
                : 'consultants';
            $payload['target_reseller_id'] = $admin['reseller_id'];
            $payload['target_manager_id'] = null;
        }
    }
    if ($moduleKey === 'content') {
        $payload['created_by'] = $admin['id'];
    }

    return $payload;
}

function broadcast_form_fields_for_admin(array $fields, array $admin): array
{
    if (($admin['role'] ?? '') === 'manager') {
        unset(
            $fields['audience_type'],
            $fields['target_type'],
            $fields['target_reseller_id'],
            $fields['target_manager_id']
        );
        return $fields;
    }

    if (($admin['role'] ?? '') === 'reseller') {
        $fields['target_type']['options'] = [
            'own_clients',
            'branch_clients',
            'direct_consultants',
            'branch_consultants',
            'direct_leaders',
            'branch_leaders',
        ];
        $fields['target_type']['default'] = 'own_clients';
        $fields['target_type']['hint'] = 'Доступны только получатели из вашей ветки.';
        unset(
            $fields['audience_type'],
            $fields['target_reseller_id'],
            $fields['target_manager_id']
        );
    }

    return $fields;
}
