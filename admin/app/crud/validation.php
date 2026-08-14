<?php

require_once __DIR__ . '/../core/referral_codes.php';

function validate_payload(array $fields, array $payload): array
{
    $errors = [];
    foreach ($fields as $name => $field) {
        if (!empty($field['virtual'])) {
            continue;
        }
        if (($field['required'] ?? false) && (($payload[$name] ?? null) === null || $payload[$name] === '')) {
            $errors[] = app_text('auto.k_2dc144adf452') . ($field['label'] ?? $name);
        }
        if (isset($field['options']) && ($payload[$name] ?? '') !== '' && !in_array($payload[$name], $field['options'], true)) {
            $errors[] = app_text('auto.k_337d46ded7e2') . ($field['label'] ?? $name);
        }
    }

    return $errors;
}

function validate_unique_payload(string $moduleKey, array $module, array $payload, ?int $recordId = null): array
{
    $uniqueFields = match ($moduleKey) {
        'resellers', 'managers' => [
            'referral_code' => 'Реферальный код',
        ],
        'site_templates' => [
            'slug' => 'Код шаблона',
        ],
        default => [],
    };

    $errors = [];
    foreach ($uniqueFields as $field => $label) {
        $value = trim((string)($payload[$field] ?? ''));
        if ($value === '') {
            continue;
        }

        if ($field === 'referral_code' && in_array($moduleKey, ['resellers', 'managers'], true)) {
            $conflict = staff_referral_code_conflict($value, $module['table'], $recordId);
            if ($conflict) {
                $errors[] = $label . ' "' . $value . '" уже используется: '
                    . $conflict['label'] . ' #' . (int)$conflict['id'] . '. Укажите другой код.';
            }
            continue;
        }

        $sql = "SELECT id FROM {$module['table']} WHERE `$field` = :value";
        $params = ['value' => $value];
        if ($recordId) {
            $sql .= ' AND id <> :id';
            $params['id'] = $recordId;
        }
        $sql .= ' LIMIT 1';

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $existingId = $stmt->fetchColumn();
        if ($existingId !== false) {
            $errors[] = $label . ' "' . $value . '" уже используется. Укажите другой код.';
        }
    }

    return $errors;
}

function staff_referral_code_conflict(string $code, ?string $exceptTable = null, ?int $exceptId = null): ?array
{
    $code = trim($code);
    if ($code === '') {
        return null;
    }

    foreach ([
        'resellers' => 'лидер',
        'managers' => 'консультант',
    ] as $table => $label) {
        $sql = "SELECT id FROM {$table} WHERE referral_code = :code";
        $params = ['code' => $code];
        if ($table === $exceptTable && $exceptId !== null) {
            $sql .= ' AND id <> :except_id';
            $params['except_id'] = $exceptId;
        }
        $sql .= ' LIMIT 1';

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $id = $stmt->fetchColumn();
        if ($id !== false) {
            return ['id' => (int)$id, 'label' => $label];
        }
    }

    $ownerType = $exceptTable === 'managers' ? 'manager' : ($exceptTable === 'resellers' ? 'reseller' : null);
    $aliasConflict = referral_code_alias_conflict($code, $ownerType, $exceptId);
    if ($aliasConflict) {
        return $aliasConflict;
    }

    return null;
}

function friendly_save_error(Throwable $e, array $payload): string
{
    $message = $e->getMessage();
    if (stripos($message, 'Referral code') !== false || stripos($message, 'Referral alias') !== false) {
        $code = trim((string)($payload['referral_code'] ?? ''));
        return $code !== ''
            ? 'Реферальный код "' . $code . '" уже использовался. Укажите другой код.'
            : 'Такой реферальный код уже использовался. Укажите другой код.';
    }
    $isDuplicate = ($e instanceof PDOException && (int)($e->errorInfo[1] ?? 0) === 1062)
        || stripos($message, 'Duplicate entry') !== false;

    if ($isDuplicate) {
        if (stripos($message, 'referral_code') !== false) {
            $code = trim((string)($payload['referral_code'] ?? ''));
            return $code !== ''
                ? 'Реферальный код "' . $code . '" уже используется. Укажите другой код.'
                : 'Такой реферальный код уже используется. Укажите другой код.';
        }

        if (stripos($message, 'slug') !== false) {
            $code = trim((string)($payload['slug'] ?? ''));
            return $code !== ''
                ? 'Код шаблона "' . $code . '" уже используется. Укажите другой код.'
                : 'Такой код шаблона уже используется. Укажите другой код.';
        }

        return 'Такая запись уже существует. Проверьте уникальные поля.';
    }

    return app_text('auto.k_02613f541f5f') . ' ' . $message;
}

function validate_scope_payload(string $moduleKey, array $payload, array $admin, ?int $recordId = null): array
{
    $errors = [];
    if (in_array($moduleKey, ['managers', 'resellers'], true)) {
        $code = (string)($payload['referral_code'] ?? '');
        if ($code === '' || !preg_match('/^[A-Z0-9_-]{3,64}$/', $code)) {
            $errors[] = app_text('referrals.invalid_code');
        }
    }

    if ($moduleKey === 'site_templates') {
        $errors = array_merge($errors, site_template_validate_payload($payload));
    }

    if ($moduleKey === 'resellers') {
        foreach (['manager_limit', 'direct_leader_limit', 'branch_leader_limit', 'direct_manager_limit', 'branch_manager_limit', 'per_child_manager_limit'] as $field) {
            if (($payload[$field] ?? null) !== null && (int)$payload[$field] < 0) {
                $errors[] = 'Лимиты лидеров и консультантов не могут быть отрицательными.';
                break;
            }
        }
        foreach (['price_per_leader', 'price_per_consultant'] as $field) {
            if (($payload[$field] ?? null) !== null && (float)$payload[$field] < 0) {
                $errors[] = 'Цена не может быть отрицательной.';
                break;
            }
        }

        $parentId = nullable_int_value($payload['parent_reseller_id'] ?? null);
        if ($recordId && $parentId === $recordId) {
            $errors[] = 'Лидер не может быть вышестоящим сам для себя.';
        }
        if ($recordId && $parentId && team_is_reseller_in_branch($recordId, $parentId, false)) {
            $errors[] = 'Нельзя выбрать дочернего лидера как вышестоящего.';
        }
        if ($admin['role'] === 'reseller') {
            $rootId = (int)$admin['reseller_id'];
            if ($recordId) {
                if (!team_is_reseller_in_branch($rootId, $recordId, true)) {
                    $errors[] = 'Этот лидер не входит в вашу ветку.';
                }
                if ($parentId !== null && !team_is_reseller_in_branch($rootId, $parentId, true)) {
                    $errors[] = 'Вышестоящий лидер должен быть внутри вашей ветки.';
                }
            } elseif ($parentId === null) {
                $errors[] = 'Для лидера в вашей ветке нужно выбрать вышестоящего лидера.';
            } elseif (!team_is_reseller_in_branch($rootId, $parentId, true)) {
                $errors[] = 'Вышестоящий лидер должен быть внутри вашей ветки.';
            }
        }
    }

    if ($moduleKey === 'managers' && $admin['role'] === 'reseller') {
        $resellerId = nullable_int_value($payload['reseller_id'] ?? null);
        if ($resellerId && !team_is_reseller_in_branch((int)$admin['reseller_id'], $resellerId, true)) {
            $errors[] = 'Консультанта можно закрепить только за лидером внутри вашей ветки.';
        }
    }

    if (in_array($moduleKey, ['users', 'leads'], true) && !empty($payload['manager_id']) && !empty($payload['reseller_id'])) {
        $managerResellerId = team_manager_reseller_id((int)$payload['manager_id']);
        if ($managerResellerId === null) {
            $errors[] = app_text('auto.k_34b1bedb5064');
        } elseif ($managerResellerId !== (int)$payload['reseller_id']) {
            $errors[] = app_text('auto.k_34b1bedb5064');
        }
    }

    if (in_array($moduleKey, ['users', 'leads', 'broadcasts'], true) && $admin['role'] === 'reseller') {
        $rootId = (int)$admin['reseller_id'];
        $resellerId = nullable_int_value($payload['reseller_id'] ?? $payload['target_reseller_id'] ?? null);
        $managerId = nullable_int_value($payload['manager_id'] ?? $payload['target_manager_id'] ?? null);
        if ($resellerId && !team_is_reseller_in_branch($rootId, $resellerId, true)) {
            $errors[] = 'Выбранный лидер не входит в вашу ветку.';
        }
        if ($managerId && !team_is_manager_in_branch($rootId, $managerId)) {
            $errors[] = 'Выбранный консультант не входит в вашу ветку.';
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
