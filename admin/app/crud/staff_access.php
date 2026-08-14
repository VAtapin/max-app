<?php

function manager_admin_access(int $managerId): ?array
{
    if ($managerId <= 0) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT id, name, email, phone, telegram_id, max_id, vk_id, referral_code, is_active
         FROM admin_users
         WHERE role = "manager" AND manager_id = :manager_id
         ORDER BY id DESC
         LIMIT 1'
    );
    $stmt->execute(['manager_id' => $managerId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function reseller_admin_access(int $resellerId): ?array
{
    if ($resellerId <= 0) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT id, name, email, phone, telegram_id, max_id, vk_id, referral_code, is_active
         FROM admin_users
         WHERE role = "reseller" AND reseller_id = :reseller_id AND manager_id IS NULL
         ORDER BY id DESC
         LIMIT 1'
    );
    $stmt->execute(['reseller_id' => $resellerId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function can_manage_team_admin_access(string $moduleKey, array $admin, ?int $recordId = null): bool
{
    if (!in_array($moduleKey, ['managers', 'resellers'], true)) {
        return false;
    }

    if ($admin['role'] === 'superadmin') {
        return true;
    }

    if ($admin['role'] !== 'reseller') {
        return false;
    }

    if ($moduleKey === 'managers') {
        return true;
    }

    if (!$recordId) {
        return true;
    }

    return (int)($admin['reseller_id'] ?? 0) !== $recordId;
}

function admin_access_extra_payload(array $entityPayload, array $post, ?array $existing = null): array
{
    $existing ??= [];
    $referralCode = trim((string)($post['admin_referral_code'] ?? ($entityPayload['referral_code'] ?? ($existing['referral_code'] ?? ''))));
    $referralCode = $referralCode !== '' ? normalize_referral_slug($referralCode) : null;

    $stringOrNull = static function (mixed $value): ?string {
        $value = trim((string)$value);
        return $value !== '' ? $value : null;
    };

    return [
        'phone' => $stringOrNull($post['admin_phone'] ?? ($entityPayload['phone'] ?? ($existing['phone'] ?? ''))),
        'telegram_id' => $stringOrNull($post['admin_telegram_id'] ?? ($entityPayload['telegram_id'] ?? ($existing['telegram_id'] ?? ''))),
        'max_id' => $stringOrNull($post['admin_max_id'] ?? ($entityPayload['max_id'] ?? ($existing['max_id'] ?? ''))),
        'vk_id' => $stringOrNull($post['admin_vk_id'] ?? ($entityPayload['vk_id'] ?? ($existing['vk_id'] ?? ''))),
        'referral_code' => $referralCode,
    ];
}

function save_reseller_admin_access(int $resellerId, array $resellerPayload, array $post, array &$errors): void
{
    $email = trim((string)($post['admin_email'] ?? ''));
    $password = (string)($post['admin_password'] ?? '');
    $isActive = isset($post['admin_is_active']) ? 1 : 0;
    $existing = reseller_admin_access($resellerId);

    if ($email === '' && $password === '' && !$existing) {
        return;
    }

    if ($email === '') {
        $errors[] = app_text('admin_access.email_required');
        return;
    }

    if (!$existing && $password === '') {
        $errors[] = app_text('admin_access.password_required');
        return;
    }

    if ($existing) {
        $extra = admin_access_extra_payload($resellerPayload, $post, $existing);
        $params = [
            'id' => (int)$existing['id'],
            'name' => $resellerPayload['name'] ?? $email,
            'email' => $email,
            'phone' => $extra['phone'],
            'telegram_id' => $extra['telegram_id'],
            'max_id' => $extra['max_id'],
            'vk_id' => $extra['vk_id'],
            'referral_code' => $extra['referral_code'],
            'reseller_id' => $resellerId,
            'is_active' => $isActive,
        ];
        $passwordSql = '';
        if ($password !== '') {
            $passwordSql = ', password_hash = :password_hash';
            $params['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $stmt = db()->prepare(
            'UPDATE admin_users
             SET name = :name, email = :email, phone = :phone, telegram_id = :telegram_id, max_id = :max_id,
                 vk_id = :vk_id, referral_code = :referral_code, reseller_id = :reseller_id, manager_id = NULL,
                 is_active = :is_active' . $passwordSql . '
             WHERE id = :id'
        );
        $stmt->execute($params);
        return;
    }

    $extra = admin_access_extra_payload($resellerPayload, $post);
    $stmt = db()->prepare(
        'INSERT INTO admin_users (
            role, reseller_id, manager_id, name, email, password_hash, phone, telegram_id, max_id, vk_id, referral_code, is_active
         ) VALUES (
            "reseller", :reseller_id, NULL, :name, :email, :password_hash, :phone, :telegram_id, :max_id, :vk_id, :referral_code, :is_active
         )'
    );
    $stmt->execute([
        'reseller_id' => $resellerId,
        'name' => $resellerPayload['name'] ?? $email,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'phone' => $extra['phone'],
        'telegram_id' => $extra['telegram_id'],
        'max_id' => $extra['max_id'],
        'vk_id' => $extra['vk_id'],
        'referral_code' => $extra['referral_code'],
        'is_active' => $isActive,
    ]);
}

function save_manager_admin_access(int $managerId, array $managerPayload, array $post, array &$errors): void
{
    $email = trim((string)($post['admin_email'] ?? ''));
    $password = (string)($post['admin_password'] ?? '');
    $isActive = isset($post['admin_is_active']) ? 1 : 0;
    $existing = manager_admin_access($managerId);

    if ($email === '' && $password === '' && !$existing) {
        return;
    }

    if ($email === '') {
        $errors[] = app_text('admin_access.email_required');
        return;
    }

    if (!$existing && $password === '') {
        $errors[] = app_text('admin_access.password_required');
        return;
    }

    $resellerId = $managerPayload['reseller_id'] !== null && $managerPayload['reseller_id'] !== ''
        ? (int)$managerPayload['reseller_id']
        : null;

    if ($existing) {
        $extra = admin_access_extra_payload($managerPayload, $post, $existing);
        $params = [
            'id' => (int)$existing['id'],
            'name' => $managerPayload['name'] ?? $email,
            'email' => $email,
            'phone' => $extra['phone'],
            'telegram_id' => $extra['telegram_id'],
            'max_id' => $extra['max_id'],
            'vk_id' => $extra['vk_id'],
            'referral_code' => $extra['referral_code'],
            'reseller_id' => $resellerId,
            'manager_id' => $managerId,
            'is_active' => $isActive,
        ];
        $passwordSql = '';
        if ($password !== '') {
            $passwordSql = ', password_hash = :password_hash';
            $params['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $stmt = db()->prepare(
            'UPDATE admin_users
             SET name = :name, email = :email, phone = :phone, telegram_id = :telegram_id, max_id = :max_id,
                 vk_id = :vk_id, referral_code = :referral_code, reseller_id = :reseller_id, manager_id = :manager_id,
                 is_active = :is_active' . $passwordSql . '
             WHERE id = :id'
        );
        $stmt->execute($params);
        return;
    }

    $extra = admin_access_extra_payload($managerPayload, $post);
    $stmt = db()->prepare(
        'INSERT INTO admin_users (
            role, reseller_id, manager_id, name, email, password_hash, phone, telegram_id, max_id, vk_id, referral_code, is_active
         ) VALUES (
            "manager", :reseller_id, :manager_id, :name, :email, :password_hash, :phone, :telegram_id, :max_id, :vk_id, :referral_code, :is_active
         )'
    );
    $stmt->execute([
        'reseller_id' => $resellerId,
        'manager_id' => $managerId,
        'name' => $managerPayload['name'] ?? $email,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'phone' => $extra['phone'],
        'telegram_id' => $extra['telegram_id'],
        'max_id' => $extra['max_id'],
        'vk_id' => $extra['vk_id'],
        'referral_code' => $extra['referral_code'],
        'is_active' => $isActive,
    ]);
}
