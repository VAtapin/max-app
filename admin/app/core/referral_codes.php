<?php

require_once __DIR__ . '/db.php';

function referral_code_normalize(?string $value): ?string
{
    $value = strtoupper(trim((string)$value));
    if ($value === '') {
        return null;
    }
    if (str_starts_with(strtolower($value), 'ref_')) {
        $value = substr($value, 4);
    }
    $value = preg_replace('/\s+/', '-', $value) ?? '';
    $value = preg_replace('/[^A-Z0-9_-]/', '', $value) ?? '';
    $value = preg_replace('/[-_]{2,}/', '-', $value) ?? '';
    $value = trim($value, '-_');
    return $value !== '' ? $value : null;
}

function referral_code_binding(?string $value): ?array
{
    $code = referral_code_normalize($value);
    if (!$code) {
        return null;
    }

    $manager = db()->prepare(
        'SELECT id AS owner_id, id AS manager_id,
                reseller_id, referral_code AS current_referral_code
         FROM managers
         WHERE referral_code = :code AND is_active = 1
         LIMIT 1'
    );
    $manager->execute(['code' => $code]);
    $binding = $manager->fetch();
    if ($binding) {
        $binding['owner_type'] = 'manager';
        $binding['was_alias'] = false;
        return $binding;
    }

    $reseller = db()->prepare(
        'SELECT id AS owner_id, NULL AS manager_id,
                id AS reseller_id, referral_code AS current_referral_code
         FROM resellers
         WHERE referral_code = :code AND is_active = 1
         LIMIT 1'
    );
    $reseller->execute(['code' => $code]);
    $binding = $reseller->fetch();
    if ($binding) {
        $binding['owner_type'] = 'reseller';
        $binding['was_alias'] = false;
        return $binding;
    }

    $alias = db()->prepare(
        'SELECT owner_type, owner_id
         FROM referral_code_aliases
         WHERE referral_code = :code
         LIMIT 1'
    );
    $alias->execute(['code' => $code]);
    $aliasRow = $alias->fetch();
    if (!$aliasRow) {
        return null;
    }

    if ($aliasRow['owner_type'] === 'manager') {
        $stmt = db()->prepare(
            'SELECT id AS owner_id, id AS manager_id,
                    reseller_id, referral_code AS current_referral_code
             FROM managers
             WHERE id = :id AND is_active = 1
             LIMIT 1'
        );
    } else {
        $stmt = db()->prepare(
            'SELECT id AS owner_id, NULL AS manager_id,
                    id AS reseller_id, referral_code AS current_referral_code
             FROM resellers
             WHERE id = :id AND is_active = 1
             LIMIT 1'
        );
    }
    $stmt->execute(['id' => (int)$aliasRow['owner_id']]);
    $binding = $stmt->fetch();
    if (!$binding) {
        return null;
    }
    $binding['owner_type'] = (string)$aliasRow['owner_type'];
    $binding['was_alias'] = true;
    $binding['matched_referral_code'] = $code;
    return $binding;
}

function referral_code_alias_conflict(string $value, ?string $ownerType = null, ?int $ownerId = null): ?array
{
    $code = referral_code_normalize($value);
    if (!$code) {
        return null;
    }
    $stmt = db()->prepare(
        'SELECT owner_type, owner_id
         FROM referral_code_aliases
         WHERE referral_code = :code
         LIMIT 1'
    );
    $stmt->execute(['code' => $code]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    if ($ownerType === (string)$row['owner_type'] && $ownerId === (int)$row['owner_id']) {
        return null;
    }
    return [
        'id' => (int)$row['owner_id'],
        'label' => $row['owner_type'] === 'manager' ? 'старый код консультанта' : 'старый код лидера',
    ];
}

function referral_code_sync_rename(string $ownerType, int $ownerId, string $oldCode, string $newCode): void
{
    $oldCode = (string)referral_code_normalize($oldCode);
    $newCode = (string)referral_code_normalize($newCode);
    if ($ownerId <= 0 || $oldCode === '' || $newCode === '' || $oldCode === $newCode) {
        return;
    }

    // A former alias may become current again for the same owner.
    $removeCurrentAlias = db()->prepare(
        'DELETE FROM referral_code_aliases
         WHERE referral_code = :code AND owner_type = :owner_type AND owner_id = :owner_id'
    );
    $removeCurrentAlias->execute([
        'code' => $newCode,
        'owner_type' => $ownerType,
        'owner_id' => $ownerId,
    ]);

    $alias = db()->prepare(
        'INSERT INTO referral_code_aliases (owner_type, owner_id, referral_code)
         VALUES (:owner_type, :owner_id, :referral_code)'
    );
    $alias->execute([
        'owner_type' => $ownerType,
        'owner_id' => $ownerId,
        'referral_code' => $oldCode,
    ]);

    if ($ownerType === 'manager') {
        $users = db()->prepare(
            'UPDATE end_users
             SET referral_code_used = :new_code
             WHERE manager_id = :owner_id AND referral_code_used = :old_code'
        );
    } else {
        $users = db()->prepare(
            'UPDATE end_users
             SET referral_code_used = :new_code
             WHERE reseller_id = :owner_id AND referral_code_used = :old_code'
        );
    }
    $users->execute(['new_code' => $newCode, 'old_code' => $oldCode, 'owner_id' => $ownerId]);

    // Preserve click/registration history. If this owner already had statistics
    // under the target code (for example after reverting a rename), merge them.
    $oldLinks = db()->prepare(
        'SELECT id, platform, clicks_count, registrations_count
         FROM referral_links
         WHERE owner_type = :owner_type AND owner_id = :owner_id AND referral_code = :old_code
         FOR UPDATE'
    );
    $oldLinks->execute([
        'old_code' => $oldCode,
        'owner_type' => $ownerType,
        'owner_id' => $ownerId,
    ]);
    foreach ($oldLinks->fetchAll() as $oldLink) {
        $target = db()->prepare(
            'SELECT id, owner_type, owner_id
             FROM referral_links
             WHERE referral_code = :new_code AND platform = :platform
             LIMIT 1
             FOR UPDATE'
        );
        $target->execute(['new_code' => $newCode, 'platform' => $oldLink['platform']]);
        $targetLink = $target->fetch();
        if ($targetLink) {
            if ((string)$targetLink['owner_type'] !== $ownerType || (int)$targetLink['owner_id'] !== $ownerId) {
                throw new RuntimeException('Новый реферальный код уже используется в статистике другой учётной записи.');
            }
            $merge = db()->prepare(
                'UPDATE referral_links
                 SET clicks_count = clicks_count + :clicks,
                     registrations_count = registrations_count + :registrations
                 WHERE id = :id'
            );
            $merge->execute([
                'clicks' => (int)$oldLink['clicks_count'],
                'registrations' => (int)$oldLink['registrations_count'],
                'id' => (int)$targetLink['id'],
            ]);
            $deleteOld = db()->prepare('DELETE FROM referral_links WHERE id = :id');
            $deleteOld->execute(['id' => (int)$oldLink['id']]);
            continue;
        }
        $move = db()->prepare('UPDATE referral_links SET referral_code = :new_code WHERE id = :id');
        $move->execute(['new_code' => $newCode, 'id' => (int)$oldLink['id']]);
    }

    $role = $ownerType === 'manager' ? 'manager' : 'reseller';
    $ownerColumn = $ownerType === 'manager' ? 'manager_id' : 'reseller_id';
    $admins = db()->prepare(
        "UPDATE admin_users
         SET referral_code = :new_code
         WHERE role = :role AND {$ownerColumn} = :owner_id"
    );
    $admins->execute(['new_code' => $newCode, 'role' => $role, 'owner_id' => $ownerId]);
}

function referral_code_prepare_rename(string $ownerType, int $ownerId, string $newCode): void
{
    $newCode = (string)referral_code_normalize($newCode);
    if ($ownerId <= 0 || $newCode === '') {
        return;
    }

    // Allows an owner to restore one of their own former codes. This must run
    // inside the same transaction before the entity UPDATE because DB triggers
    // reserve every alias globally.
    $stmt = db()->prepare(
        'DELETE FROM referral_code_aliases
         WHERE referral_code = :code AND owner_type = :owner_type AND owner_id = :owner_id'
    );
    $stmt->execute(['code' => $newCode, 'owner_type' => $ownerType, 'owner_id' => $ownerId]);
}
