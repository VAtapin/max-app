<?php

function user_promotion_allowed(array $admin): bool
{
    return in_array((string)$admin['role'], ['superadmin', 'reseller'], true);
}

function user_promotion_can_promote_row(array $row, array $admin): bool
{
    if (!user_promotion_allowed($admin)) {
        return false;
    }
    if (!empty($row['merged_into_user_id'])) {
        return false;
    }

    return scoped_end_user_exists((int)($row['id'] ?? 0), $admin);
}

function user_promotion_full_name(array $user): string
{
    $name = trim(implode(' ', array_filter([
        trim((string)($user['first_name'] ?? '')),
        trim((string)($user['last_name'] ?? '')),
    ])));
    if ($name !== '') {
        return $name;
    }

    $username = trim((string)($user['username'] ?? ''));
    return $username !== '' ? $username : ('Клиент #' . (int)($user['id'] ?? 0));
}

function user_promotion_platform_field(string $platform): ?string
{
    return match (strtolower(trim($platform))) {
        'telegram', 'tg' => 'telegram_id',
        'max' => 'max_id',
        'vk', 'vkontakte' => 'vk_id',
        default => null,
    };
}

function user_promotion_platform_identities(array $user): array
{
    $result = [];
    $add = static function (?string $platform, mixed $platformUserId) use (&$result): void {
        $field = $platform !== null ? user_promotion_platform_field($platform) : null;
        $value = trim((string)$platformUserId);
        if ($field && $value !== '' && empty($result[$field])) {
            $result[$field] = $value;
        }
    };

    $add((string)($user['platform'] ?? ''), $user['platform_user_id'] ?? null);
    foreach (user_platform_accounts((int)($user['id'] ?? 0)) as $account) {
        $add((string)($account['platform'] ?? ''), $account['platform_user_id'] ?? null);
    }

    return $result;
}

function user_promotion_referral_code_exists(string $code): bool
{
    if ($code === '') {
        return false;
    }

    foreach ([
        'SELECT id FROM resellers WHERE referral_code = :code LIMIT 1',
        'SELECT id FROM managers WHERE referral_code = :code LIMIT 1',
        'SELECT id FROM admin_users WHERE referral_code = :code LIMIT 1',
    ] as $sql) {
        $stmt = db()->prepare($sql);
        $stmt->execute(['code' => $code]);
        if ($stmt->fetchColumn()) {
            return true;
        }
    }

    return false;
}

function user_promotion_unique_referral_code(array $user): string
{
    $name = normalize_merge_text(user_promotion_full_name($user));
    $base = normalize_referral_slug('SWPRO_' . ($name !== '' ? $name : ('USER_' . (int)$user['id'])));
    if ($base === '' || $base === 'SWPRO') {
        $base = 'SWPRO_USER_' . (int)$user['id'];
    }

    $candidate = $base;
    for ($i = 2; user_promotion_referral_code_exists($candidate); $i++) {
        $candidate = $base . '_' . $i;
    }

    return $candidate;
}

function user_promotion_staff_conflict(array $platformIds, string $email, string $referralCode): ?string
{
    if ($email !== '') {
        foreach ([
            'managers' => 'консультант',
            'resellers' => 'лидер',
            'admin_users' => 'пользователь админки',
        ] as $table => $label) {
            $stmt = db()->prepare("SELECT id FROM {$table} WHERE email = :email LIMIT 1");
            $stmt->execute(['email' => $email]);
            $existingId = $stmt->fetchColumn();
            if ($existingId) {
                return 'Email уже используется: ' . $label . ' #' . (int)$existingId . '.';
            }
        }
    }

    if ($referralCode !== '' && user_promotion_referral_code_exists($referralCode)) {
        return 'Реферальный код уже используется.';
    }

    foreach (['telegram_id', 'max_id', 'vk_id'] as $field) {
        $value = trim((string)($platformIds[$field] ?? ''));
        if ($value === '') {
            continue;
        }

        foreach ([
            'managers' => 'консультант',
            'admin_users' => 'пользователь админки',
        ] as $table => $label) {
            $stmt = db()->prepare("SELECT id FROM {$table} WHERE {$field} = :value LIMIT 1");
            $stmt->execute(['value' => $value]);
            $existingId = $stmt->fetchColumn();
            if ($existingId) {
                return strtoupper(str_replace('_id', '', $field)) . ' уже используется: ' . $label . ' #' . (int)$existingId . '.';
            }
        }
    }

    return null;
}

function user_promotion_staff_module(string $type): ?string
{
    return match ($type) {
        'manager' => 'managers',
        'reseller' => 'resellers',
        default => null,
    };
}

function user_promotion_staff_scope_ok(string $type, int $id, array $admin): bool
{
    global $modules;

    $moduleKey = user_promotion_staff_module($type);
    if (!$moduleKey || !isset($modules[$moduleKey])) {
        return false;
    }

    return scoped_row_exists($moduleKey, $modules[$moduleKey], $id, $admin);
}

function user_promotion_staff_row(string $type, int $id, array $admin): array
{
    if (!user_promotion_staff_scope_ok($type, $id, $admin)) {
        throw new RuntimeException('Рабочий аккаунт не найден или недоступен.');
    }

    $table = $type === 'manager' ? 'managers' : 'resellers';
    $stmt = db()->prepare("SELECT * FROM {$table} WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Рабочий аккаунт не найден.');
    }

    return $row;
}

function user_promotion_staff_label(string $type, array $row): string
{
    $label = $type === 'manager' ? 'Консультант' : 'Лидер';
    return $label . ' #' . (int)$row['id'] . ' ' . trim((string)($row['name'] ?? ''));
}

function user_promotion_existing_access(string $type, int $id): ?array
{
    return $type === 'manager' ? manager_admin_access($id) : reseller_admin_access($id);
}

function user_promotion_add_search_condition(array &$conditions, array &$params, string $expression, string $param, mixed $value): void
{
    $value = trim((string)$value);
    if ($value === '') {
        return;
    }

    $conditions[] = $expression;
    $params[$param] = $value;
}

function user_promotion_existing_staff_candidates(array $user, array $admin): array
{
    if (!user_promotion_allowed($admin)) {
        return [];
    }

    $platformIds = user_promotion_platform_identities($user);
    $fullName = user_promotion_full_name($user);
    $firstName = trim((string)($user['first_name'] ?? ''));
    $lastName = trim((string)($user['last_name'] ?? ''));
    $email = trim((string)($user['email'] ?? ''));
    $phone = trim((string)($user['phone'] ?? ''));
    $candidates = [];

    $addCandidate = static function (string $type, array $row, string $reason = '') use (&$candidates, $admin): void {
        $id = (int)($row['id'] ?? 0);
        if ($id <= 0 || !user_promotion_staff_scope_ok($type, $id, $admin)) {
            return;
        }

        $key = $type . ':' . $id;
        if (isset($candidates[$key])) {
            return;
        }

        $details = [];
        if (!empty($row['email'])) {
            $details[] = (string)$row['email'];
        }
        if (!empty($row['phone'])) {
            $details[] = (string)$row['phone'];
        }
        if ($type === 'manager' && !empty($row['reseller_id'])) {
            $details[] = 'лидер: ' . team_reseller_label((int)$row['reseller_id']);
        }
        foreach (['telegram_id' => 'TG', 'vk_id' => 'VK', 'max_id' => 'MAX'] as $field => $label) {
            if (!empty($row[$field])) {
                $details[] = $label . ': ' . (string)$row[$field];
            }
        }
        if ($reason !== '') {
            $details[] = $reason;
        }

        $candidates[$key] = [
            'key' => $key,
            'type' => $type,
            'id' => $id,
            'label' => user_promotion_staff_label($type, $row),
            'details' => implode(' · ', array_values(array_unique(array_filter($details)))),
        ];
    };

    $managerConditions = [];
    $managerParams = [];
    user_promotion_add_search_condition($managerConditions, $managerParams, 'm.email = :manager_email', 'manager_email', $email);
    user_promotion_add_search_condition($managerConditions, $managerParams, 'm.phone = :manager_phone', 'manager_phone', $phone);
    user_promotion_add_search_condition($managerConditions, $managerParams, 'm.telegram_id = :manager_telegram_id', 'manager_telegram_id', $platformIds['telegram_id'] ?? '');
    user_promotion_add_search_condition($managerConditions, $managerParams, 'm.vk_id = :manager_vk_id', 'manager_vk_id', $platformIds['vk_id'] ?? '');
    user_promotion_add_search_condition($managerConditions, $managerParams, 'm.max_id = :manager_max_id', 'manager_max_id', $platformIds['max_id'] ?? '');
    user_promotion_add_search_condition($managerConditions, $managerParams, 'm.name = :manager_full_name', 'manager_full_name', $fullName);
    if ($lastName !== '') {
        $managerConditions[] = 'm.name LIKE :manager_last_name';
        $managerParams['manager_last_name'] = '%' . $lastName . '%';
    }
    if ($firstName !== '' && $lastName !== '') {
        $managerConditions[] = '(m.name LIKE :manager_first_name AND m.name LIKE :manager_last_name_pair)';
        $managerParams['manager_first_name'] = '%' . $firstName . '%';
        $managerParams['manager_last_name_pair'] = '%' . $lastName . '%';
    }
    if ($managerConditions) {
        $stmt = db()->prepare(
            'SELECT m.*
             FROM managers m
             WHERE (' . implode(' OR ', $managerConditions) . ')
             ORDER BY m.id DESC
             LIMIT 30'
        );
        $stmt->execute($managerParams);
        foreach ($stmt->fetchAll() as $row) {
            $addCandidate('manager', $row, 'похожий консультант');
        }
    }

    $resellerConditions = [];
    $resellerParams = [];
    user_promotion_add_search_condition($resellerConditions, $resellerParams, 'r.email = :reseller_email', 'reseller_email', $email);
    user_promotion_add_search_condition($resellerConditions, $resellerParams, 'r.phone = :reseller_phone', 'reseller_phone', $phone);
    user_promotion_add_search_condition($resellerConditions, $resellerParams, 'r.name = :reseller_full_name', 'reseller_full_name', $fullName);
    if ($lastName !== '') {
        $resellerConditions[] = 'r.name LIKE :reseller_last_name';
        $resellerParams['reseller_last_name'] = '%' . $lastName . '%';
    }
    if ($firstName !== '' && $lastName !== '') {
        $resellerConditions[] = '(r.name LIKE :reseller_first_name AND r.name LIKE :reseller_last_name_pair)';
        $resellerParams['reseller_first_name'] = '%' . $firstName . '%';
        $resellerParams['reseller_last_name_pair'] = '%' . $lastName . '%';
    }
    if ($resellerConditions) {
        $stmt = db()->prepare(
            'SELECT r.*
             FROM resellers r
             WHERE (' . implode(' OR ', $resellerConditions) . ')
             ORDER BY r.id DESC
             LIMIT 30'
        );
        $stmt->execute($resellerParams);
        foreach ($stmt->fetchAll() as $row) {
            $addCandidate('reseller', $row, 'похожий лидер');
        }
    }

    $adminConditions = [];
    $adminParams = [];
    user_promotion_add_search_condition($adminConditions, $adminParams, 'au.email = :admin_email', 'admin_email', $email);
    user_promotion_add_search_condition($adminConditions, $adminParams, 'au.phone = :admin_phone', 'admin_phone', $phone);
    user_promotion_add_search_condition($adminConditions, $adminParams, 'au.telegram_id = :admin_telegram_id', 'admin_telegram_id', $platformIds['telegram_id'] ?? '');
    user_promotion_add_search_condition($adminConditions, $adminParams, 'au.vk_id = :admin_vk_id', 'admin_vk_id', $platformIds['vk_id'] ?? '');
    user_promotion_add_search_condition($adminConditions, $adminParams, 'au.max_id = :admin_max_id', 'admin_max_id', $platformIds['max_id'] ?? '');
    if ($adminConditions) {
        $stmt = db()->prepare(
            'SELECT au.*
             FROM admin_users au
             WHERE au.role IN ("manager", "reseller")
               AND (' . implode(' OR ', $adminConditions) . ')
             ORDER BY au.id DESC
             LIMIT 30'
        );
        $stmt->execute($adminParams);
        foreach ($stmt->fetchAll() as $row) {
            if ((string)$row['role'] === 'manager' && !empty($row['manager_id'])) {
                try {
                    $staff = user_promotion_staff_row('manager', (int)$row['manager_id'], $admin);
                } catch (Throwable) {
                    continue;
                }
                $addCandidate('manager', $staff + [
                    'telegram_id' => $row['telegram_id'] ?? null,
                    'vk_id' => $row['vk_id'] ?? null,
                    'max_id' => $row['max_id'] ?? null,
                ], 'найден по доступу в админку');
            }
            if ((string)$row['role'] === 'reseller' && !empty($row['reseller_id'])) {
                try {
                    $staff = user_promotion_staff_row('reseller', (int)$row['reseller_id'], $admin);
                } catch (Throwable) {
                    continue;
                }
                $addCandidate('reseller', $staff + [
                    'telegram_id' => $row['telegram_id'] ?? null,
                    'vk_id' => $row['vk_id'] ?? null,
                    'max_id' => $row['max_id'] ?? null,
                ], 'найден по доступу в админку');
            }
        }
    }

    return array_slice(array_values($candidates), 0, 20);
}

function user_promotion_platform_conflict(string $type, int $id, string $field, string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    if (in_array($field, ['telegram_id', 'max_id', 'vk_id'], true)) {
        $stmt = db()->prepare("SELECT id FROM managers WHERE {$field} = :value LIMIT 1");
        $stmt->execute(['value' => $value]);
        $managerId = (int)$stmt->fetchColumn();
        if ($managerId > 0 && ($type !== 'manager' || $managerId !== $id)) {
            return strtoupper(str_replace('_id', '', $field)) . ' уже привязан к консультанту #' . $managerId . '.';
        }
    }

    $stmt = db()->prepare("SELECT id, role, manager_id, reseller_id FROM admin_users WHERE {$field} = :value LIMIT 1");
    $stmt->execute(['value' => $value]);
    $adminUser = $stmt->fetch();
    if (!$adminUser) {
        return null;
    }

    $sameManager = $type === 'manager'
        && (string)$adminUser['role'] === 'manager'
        && (int)($adminUser['manager_id'] ?? 0) === $id;
    $sameReseller = $type === 'reseller'
        && (string)$adminUser['role'] === 'reseller'
        && (int)($adminUser['reseller_id'] ?? 0) === $id;
    if (!$sameManager && !$sameReseller) {
        return strtoupper(str_replace('_id', '', $field)) . ' уже привязан к другому рабочему аккаунту.';
    }

    return null;
}

function user_promotion_sync_platform_ids(string $type, int $id, array $platformIds): void
{
    $fields = [];
    foreach (['telegram_id', 'max_id', 'vk_id'] as $field) {
        $value = trim((string)($platformIds[$field] ?? ''));
        if ($value === '') {
            continue;
        }
        $conflict = user_promotion_platform_conflict($type, $id, $field, $value);
        if ($conflict) {
            throw new RuntimeException($conflict);
        }
        $fields[$field] = $value;
    }
    if (!$fields) {
        return;
    }

    $sets = [];
    $params = ['id' => $id];
    foreach ($fields as $field => $value) {
        $sets[] = "{$field} = CASE WHEN {$field} IS NULL OR {$field} = '' THEN :{$field} ELSE {$field} END";
        $params[$field] = $value;
    }

    if ($type === 'manager') {
        $stmt = db()->prepare('UPDATE managers SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $stmt->execute($params);
    }

    $role = $type === 'manager' ? 'manager' : 'reseller';
    $ownerColumn = $type === 'manager' ? 'manager_id' : 'reseller_id';
    $stmt = db()->prepare(
        'UPDATE admin_users
         SET ' . implode(', ', $sets) . '
         WHERE role = :role AND ' . $ownerColumn . ' = :id'
    );
    $params['role'] = $role;
    $stmt->execute($params);
}

function user_promotion_admin_post_for_link(string $type, array $staff, array $platformIds, array $post, ?array $existing): array
{
    $existing ??= [];
    $value = static function (string $postKey, string $staffKey = '') use ($post, $staff, $existing): string {
        $staffKey = $staffKey !== '' ? $staffKey : $postKey;
        return trim((string)($post[$postKey] ?? ($existing[$staffKey] ?? ($staff[$staffKey] ?? ''))));
    };

    return [
        'admin_email' => $value('link_admin_email', 'email'),
        'admin_password' => (string)($post['link_admin_password'] ?? ''),
        'admin_is_active' => '1',
        'admin_phone' => $value('link_admin_phone', 'phone'),
        'admin_telegram_id' => trim((string)($platformIds['telegram_id'] ?? ($existing['telegram_id'] ?? ($staff['telegram_id'] ?? '')))),
        'admin_max_id' => trim((string)($platformIds['max_id'] ?? ($existing['max_id'] ?? ($staff['max_id'] ?? '')))),
        'admin_vk_id' => trim((string)($platformIds['vk_id'] ?? ($existing['vk_id'] ?? ($staff['vk_id'] ?? '')))),
        'admin_referral_code' => trim((string)($existing['referral_code'] ?? ($staff['referral_code'] ?? ''))),
    ];
}

function user_promotion_ensure_staff_access_for_link(string $type, int $id, array $staff, array $platformIds, array $post): void
{
    $existing = user_promotion_existing_access($type, $id);
    $adminPost = user_promotion_admin_post_for_link($type, $staff, $platformIds, $post, $existing);
    $errors = [];

    if ($type === 'manager') {
        save_manager_admin_access($id, $staff, $adminPost, $errors);
    } else {
        save_reseller_admin_access($id, $staff, $adminPost, $errors);
    }

    if ($errors) {
        throw new RuntimeException(implode(' ', $errors));
    }
}

function link_end_user_to_work_account(int $endUserId, array $post, array $admin): array
{
    if (!user_promotion_allowed($admin)) {
        throw new RuntimeException('Недостаточно прав для связывания клиента.');
    }
    if (!scoped_end_user_exists($endUserId, $admin)) {
        throw new RuntimeException('Клиент не найден или недоступен.');
    }

    $ref = trim((string)($post['existing_staff_ref'] ?? ''));
    if (!preg_match('/^(manager|reseller):(\d+)$/', $ref, $matches)) {
        throw new RuntimeException('Выберите рабочий аккаунт для связи.');
    }

    $type = $matches[1];
    $staffId = (int)$matches[2];
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare('SELECT * FROM end_users WHERE id = :id AND merged_into_user_id IS NULL LIMIT 1 FOR UPDATE');
        $stmt->execute(['id' => $endUserId]);
        $user = $stmt->fetch();
        if (!$user || !user_promotion_can_promote_row($user, $admin)) {
            throw new RuntimeException('Клиент не найден или недоступен.');
        }

        $staff = user_promotion_staff_row($type, $staffId, $admin);
        $platformIds = user_promotion_platform_identities($user);
        user_promotion_ensure_staff_access_for_link($type, $staffId, $staff, $platformIds, $post);
        user_promotion_sync_platform_ids($type, $staffId, $platformIds);

        $oldResellerId = nullable_int_value($user['reseller_id'] ?? null);
        $oldManagerId = nullable_int_value($user['manager_id'] ?? null);
        $oldStage = (string)($user['client_stage'] ?? 'new');
        $module = $type === 'manager' ? 'managers' : 'resellers';
        if ($type === 'manager') {
            $newResellerId = nullable_int_value($staff['reseller_id'] ?? null);
            $newManagerId = $staffId;
            if (!$newResellerId) {
                throw new RuntimeException('У выбранного консультанта не указан лидер.');
            }
            $update = $pdo->prepare(
                'UPDATE end_users
                 SET reseller_id = :reseller_id, manager_id = :manager_id, client_stage = "partner",
                     stage_updated_at = NOW(), status = "active"
                 WHERE id = :id'
            );
            $update->execute(['id' => $endUserId, 'reseller_id' => $newResellerId, 'manager_id' => $newManagerId]);
            $sourceUpdate = $pdo->prepare('UPDATE managers SET source_end_user_id = :end_user_id WHERE id = :id');
            $sourceUpdate->execute(['end_user_id' => $endUserId, 'id' => $staffId]);
        } else {
            $newResellerId = $staffId;
            $newManagerId = null;
            $update = $pdo->prepare(
                'UPDATE end_users
                 SET reseller_id = :reseller_id, manager_id = NULL, client_stage = "partner",
                     stage_updated_at = NOW(), status = "active"
                 WHERE id = :id'
            );
            $update->execute(['id' => $endUserId, 'reseller_id' => $newResellerId]);
            $sourceUpdate = $pdo->prepare('UPDATE resellers SET source_end_user_id = :end_user_id WHERE id = :id');
            $sourceUpdate->execute(['end_user_id' => $endUserId, 'id' => $staffId]);
        }

        sync_active_leads_assignment($endUserId, $newResellerId, $newManagerId);
        sync_consultant_notifications_assignment($endUserId, $newResellerId, $newManagerId);
        log_end_user_transfer($endUserId, $oldResellerId, $oldManagerId, $newResellerId, $newManagerId, $admin, 'end_user_linked_to_staff', [
            'staff_type' => $type,
            'staff_id' => $staffId,
        ]);

        if ($oldStage !== 'partner') {
            $history = $pdo->prepare(
                'INSERT INTO client_stage_history (end_user_id, previous_stage, new_stage, source, actor_id)
                 VALUES (:end_user_id, :previous_stage, "partner", :source, :actor_id)'
            );
            $history->execute([
                'end_user_id' => $endUserId,
                'previous_stage' => $oldStage,
                'source' => user_promotion_stage_source($admin),
                'actor_id' => (int)$admin['id'],
            ]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return ['module' => $module, 'id' => $staffId, 'label' => user_promotion_staff_label($type, $staff)];
}

function user_promotion_template_id(array $post, array $admin): ?int
{
    $templateId = nullable_int_value($post['promotion_template_id'] ?? null);
    return $templateId && site_template_row($templateId, $admin) ? $templateId : null;
}

function user_promotion_scoped_reseller(int $resellerId, array $admin): ?array
{
    if ($resellerId <= 0) {
        return null;
    }

    global $modules;
    if (!scoped_row_exists('resellers', $modules['resellers'], $resellerId, $admin)) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM resellers WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $resellerId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function user_promotion_reseller_id_for_consultant(array $user, array $post, array $admin): int
{
    if ($admin['role'] === 'reseller') {
        return (int)$admin['reseller_id'];
    }

    $selectedId = nullable_int_value($post['promotion_reseller_id'] ?? null)
        ?? nullable_int_value($user['reseller_id'] ?? null);
    if (!$selectedId || !user_promotion_scoped_reseller($selectedId, $admin)) {
        throw new RuntimeException('Выберите лидера для нового консультанта.');
    }

    return $selectedId;
}

function user_promotion_parent_reseller_id_for_leader(array $user, array $post, array $admin): ?int
{
    if ($admin['role'] === 'reseller') {
        return (int)$admin['reseller_id'];
    }

    $selectedId = nullable_int_value($post['promotion_parent_reseller_id'] ?? null);
    if (!$selectedId) {
        return null;
    }
    if (!user_promotion_scoped_reseller($selectedId, $admin)) {
        throw new RuntimeException('Выбранный вышестоящий лидер недоступен.');
    }

    return $selectedId;
}

function user_promotion_apply_profile(string $ownerType, int $ownerId, ?int $templateId): void
{
    $profile = ensure_consultant_profile($ownerType, $ownerId);
    $profileId = (int)($profile['id'] ?? 0);
    if ($profileId <= 0) {
        return;
    }
    if ($templateId) {
        site_template_apply_to_profile($profileId, $ownerType, $ownerId, $templateId);
        return;
    }
    if (!consultant_profile_inherits($profile)) {
        $parentProfile = consultant_parent_profile($ownerType, $ownerId);
        if ($parentProfile) {
            consultant_profile_reset_to_parent($profileId, (int)$parentProfile['id']);
        }
    }
}

function user_promotion_stage_source(array $admin): string
{
    return $admin['role'] === 'reseller' ? 'leader' : 'admin';
}

function promote_end_user_to_work_account(int $endUserId, array $post, array $admin): array
{
    if (!user_promotion_allowed($admin)) {
        throw new RuntimeException('Недостаточно прав для преобразования клиента.');
    }
    if (!scoped_end_user_exists($endUserId, $admin)) {
        throw new RuntimeException('Клиент не найден или недоступен.');
    }

    $target = (string)($post['promotion_target'] ?? '');
    if (!in_array($target, ['manager', 'reseller'], true)) {
        throw new RuntimeException('Выберите, кого создать: консультанта или лидера.');
    }

    $templateId = user_promotion_template_id($post, $admin);
    $pdo = db();
    $pdo->beginTransaction();
    $createdOwnerType = null;
    $createdOwnerId = 0;
    $module = $target === 'manager' ? 'managers' : 'resellers';
    $label = '';

    try {
        $stmt = $pdo->prepare('SELECT * FROM end_users WHERE id = :id AND merged_into_user_id IS NULL LIMIT 1 FOR UPDATE');
        $stmt->execute(['id' => $endUserId]);
        $user = $stmt->fetch();
        if (!$user || !user_promotion_can_promote_row($user, $admin)) {
            throw new RuntimeException('Клиент не найден или недоступен.');
        }

        $name = trim((string)($post['promotion_name'] ?? ''));
        $name = $name !== '' ? $name : user_promotion_full_name($user);
        $email = trim((string)($post['admin_email'] ?? ($user['email'] ?? '')));
        $password = (string)($post['admin_password'] ?? '');
        if ($email === '') {
            throw new RuntimeException('Укажите email для входа в админку.');
        }
        if ($password === '') {
            throw new RuntimeException('Укажите временный пароль для входа в админку.');
        }

        $phone = trim((string)($post['admin_phone'] ?? ($user['phone'] ?? '')));
        $platformIds = user_promotion_platform_identities($user);
        $referralCode = trim((string)($post['promotion_referral_code'] ?? ''));
        $referralCode = $referralCode !== '' ? normalize_referral_slug($referralCode) : user_promotion_unique_referral_code($user);
        if ($referralCode === '') {
            throw new RuntimeException('Не удалось сформировать реферальный код.');
        }

        $conflict = user_promotion_staff_conflict($platformIds, $email, $referralCode);
        if ($conflict) {
            throw new RuntimeException($conflict);
        }

        $oldResellerId = nullable_int_value($user['reseller_id'] ?? null);
        $oldManagerId = nullable_int_value($user['manager_id'] ?? null);
        $oldStage = (string)($user['client_stage'] ?? 'new');
        $adminPost = [
            'admin_email' => $email,
            'admin_password' => $password,
            'admin_is_active' => '1',
            'admin_phone' => $phone,
            'admin_telegram_id' => $platformIds['telegram_id'] ?? '',
            'admin_max_id' => $platformIds['max_id'] ?? '',
            'admin_vk_id' => $platformIds['vk_id'] ?? '',
            'admin_referral_code' => $referralCode,
        ];
        $accessErrors = [];

        if ($target === 'manager') {
            $resellerId = user_promotion_reseller_id_for_consultant($user, $post, $admin);
            $payload = [
                'reseller_id' => $resellerId,
                'name' => $name,
                'email' => $email,
                'phone' => $phone !== '' ? $phone : null,
                'telegram_id' => $platformIds['telegram_id'] ?? null,
                'max_id' => $platformIds['max_id'] ?? null,
                'vk_id' => $platformIds['vk_id'] ?? null,
                'referral_code' => $referralCode,
                'is_active' => 1,
            ];
            $limitErrors = validate_manager_limit_payload('managers', $payload, null);
            if ($limitErrors) {
                throw new RuntimeException(implode(' ', $limitErrors));
            }

            $insert = $pdo->prepare(
                'INSERT INTO managers (reseller_id, name, email, phone, telegram_id, max_id, vk_id, referral_code, is_active)
                 VALUES (:reseller_id, :name, :email, :phone, :telegram_id, :max_id, :vk_id, :referral_code, :is_active)'
            );
            $insert->execute($payload);
            $managerId = (int)$pdo->lastInsertId();
            $sourceUpdate = $pdo->prepare('UPDATE managers SET source_end_user_id = :end_user_id WHERE id = :id');
            $sourceUpdate->execute(['end_user_id' => $endUserId, 'id' => $managerId]);
            save_manager_admin_access($managerId, $payload, $adminPost, $accessErrors);
            if ($accessErrors) {
                throw new RuntimeException(implode(' ', $accessErrors));
            }

            $update = $pdo->prepare(
                'UPDATE end_users
                 SET reseller_id = :reseller_id, manager_id = :manager_id, client_stage = "partner",
                     stage_updated_at = NOW(), status = "active"
                 WHERE id = :id'
            );
            $update->execute(['id' => $endUserId, 'reseller_id' => $resellerId, 'manager_id' => $managerId]);
            $newResellerId = $resellerId;
            $newManagerId = $managerId;
            $createdOwnerType = 'manager';
            $createdOwnerId = $managerId;
            $label = 'консультант #' . $managerId;
        } else {
            $parentResellerId = user_promotion_parent_reseller_id_for_leader($user, $post, $admin);
            $payload = [
                'parent_reseller_id' => $parentResellerId,
                'name' => $name,
                'email' => $email,
                'phone' => $phone !== '' ? $phone : null,
                'referral_code' => $referralCode,
                'is_active' => 1,
            ];
            $limitErrors = validate_leader_limit_payload('resellers', $payload, null);
            if ($limitErrors) {
                throw new RuntimeException(implode(' ', $limitErrors));
            }

            $insert = $pdo->prepare(
                'INSERT INTO resellers (parent_reseller_id, name, email, phone, referral_code, is_active)
                 VALUES (:parent_reseller_id, :name, :email, :phone, :referral_code, :is_active)'
            );
            $insert->execute($payload);
            $resellerId = (int)$pdo->lastInsertId();
            $sourceUpdate = $pdo->prepare('UPDATE resellers SET source_end_user_id = :end_user_id WHERE id = :id');
            $sourceUpdate->execute(['end_user_id' => $endUserId, 'id' => $resellerId]);
            save_reseller_admin_access($resellerId, $payload, $adminPost, $accessErrors);
            if ($accessErrors) {
                throw new RuntimeException(implode(' ', $accessErrors));
            }

            $update = $pdo->prepare(
                'UPDATE end_users
                 SET reseller_id = :reseller_id, manager_id = NULL, client_stage = "partner",
                     stage_updated_at = NOW(), status = "active"
                 WHERE id = :id'
            );
            $update->execute(['id' => $endUserId, 'reseller_id' => $resellerId]);
            $newResellerId = $resellerId;
            $newManagerId = null;
            $createdOwnerType = 'reseller';
            $createdOwnerId = $resellerId;
            $label = 'лидер #' . $resellerId;
        }

        sync_active_leads_assignment($endUserId, $newResellerId, $newManagerId);
        sync_consultant_notifications_assignment($endUserId, $newResellerId, $newManagerId);
        log_end_user_transfer($endUserId, $oldResellerId, $oldManagerId, $newResellerId, $newManagerId, $admin, 'end_user_promoted', [
            'target' => $target,
            'created_owner_id' => $createdOwnerId,
        ]);

        if ($oldStage !== 'partner') {
            $history = $pdo->prepare(
                'INSERT INTO client_stage_history (end_user_id, previous_stage, new_stage, source, actor_id)
                 VALUES (:end_user_id, :previous_stage, "partner", :source, :actor_id)'
            );
            $history->execute([
                'end_user_id' => $endUserId,
                'previous_stage' => $oldStage,
                'source' => user_promotion_stage_source($admin),
                'actor_id' => (int)$admin['id'],
            ]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    if ($createdOwnerType && $createdOwnerId > 0) {
        try {
            user_promotion_apply_profile($createdOwnerType, $createdOwnerId, $templateId);
        } catch (Throwable) {
            // Рабочий аккаунт создан; мини-сайт можно восстановить вручную в админке.
        }
    }

    return ['module' => $module, 'id' => $createdOwnerId, 'label' => $label];
}
