<?php

function detach_owner_content(string $ownerType, int $ownerId): void
{
    $updates = [
        'product_categories' => ['column' => 'is_active', 'value' => 0],
        'products' => ['column' => 'is_active', 'value' => 0],
        'tests' => ['column' => 'is_active', 'value' => 0],
        'site_templates' => ['column' => 'is_active', 'value' => 0],
        'content_posts' => ['column' => 'status', 'value' => 'hidden'],
        'broadcasts' => ['column' => 'status', 'value' => 'cancelled'],
    ];

    foreach ($updates as $table => $update) {
        $stmt = db()->prepare(
            "UPDATE {$table}
             SET {$update['column']} = :inactive_value
             WHERE owner_type = :owner_type AND owner_id = :owner_id"
        );
        $stmt->execute([
            'inactive_value' => $update['value'],
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
        ]);
    }
}

function delete_owner_service_records(string $ownerType, int $ownerId): void
{
    $defaultStmt = db()->prepare(
        'SELECT COUNT(*) FROM messaging_integrations
         WHERE owner_type = :owner_type AND owner_id = :owner_id AND is_default = 1'
    );
    $defaultStmt->execute(['owner_type' => $ownerType, 'owner_id' => $ownerId]);
    if ((int)$defaultStmt->fetchColumn() > 0) {
        throw new RuntimeException('Сначала назначьте другое стандартное сообщество VK.');
    }

    $stmt = db()->prepare('DELETE FROM referral_links WHERE owner_type = :owner_type AND owner_id = :owner_id');
    $stmt->execute(['owner_type' => $ownerType, 'owner_id' => $ownerId]);

    $stmt = db()->prepare('DELETE FROM messaging_integrations WHERE owner_type = :owner_type AND owner_id = :owner_id');
    $stmt->execute(['owner_type' => $ownerType, 'owner_id' => $ownerId]);

    $stmt = db()->prepare('DELETE FROM consultant_profiles WHERE owner_type = :owner_type AND owner_id = :owner_id');
    $stmt->execute(['owner_type' => $ownerType, 'owner_id' => $ownerId]);
}

function delete_crud_record(string $moduleKey, array $module, int $id, array $admin): void
{
    if ($moduleKey === 'integrations') {
        $defaultStmt = db()->prepare('SELECT is_default FROM messaging_integrations WHERE id = :id LIMIT 1');
        $defaultStmt->execute(['id' => $id]);
        if ((int)$defaultStmt->fetchColumn() === 1) {
            throw new RuntimeException('Сначала назначьте другое стандартное сообщество VK.');
        }
    }
    $pdo = db();
    $pdo->beginTransaction();

    try {
        if ($moduleKey === 'users') {
            $staffSource = $pdo->prepare(
                'SELECT (
                    EXISTS(SELECT 1 FROM resellers WHERE source_end_user_id = :reseller_user_id)
                    OR EXISTS(SELECT 1 FROM managers WHERE source_end_user_id = :manager_user_id)
                )'
            );
            $staffSource->execute(['reseller_user_id' => $id, 'manager_user_id' => $id]);
            if ((int)$staffSource->fetchColumn() === 1) {
                throw new RuntimeException('Эта запись связана с рабочим аккаунтом. Сначала отключите или перенесите рабочую связь.');
            }
            $stmt = $pdo->prepare('DELETE FROM end_users WHERE id = :id');
            $stmt->execute(['id' => $id]);
            log_activity('admin', (int)$admin['id'], 'delete_end_users', 'end_users', $id);
            $pdo->commit();
            return;
        }

        if (owned_content_delete_for_admin($moduleKey, $id, $admin)) {
            log_activity('admin', (int)$admin['id'], 'hide_owned_' . $module['table'], $module['table'], $id);
            $pdo->commit();
            return;
        }

        if ($moduleKey === 'content') {
            $stmt = $pdo->prepare('UPDATE content_posts SET status = "hidden" WHERE id = :id');
            $stmt->execute(['id' => $id]);
            log_activity('admin', (int)$admin['id'], 'hide_content_posts', 'content_posts', $id);
            $pdo->commit();
            return;
        }

        if ($moduleKey === 'site_templates') {
            $stmt = $pdo->prepare('UPDATE site_templates SET is_active = 0 WHERE id = :id');
            $stmt->execute(['id' => $id]);
            log_activity('admin', (int)$admin['id'], 'hide_site_templates', 'site_templates', $id);
            $pdo->commit();
            return;
        }

        if ($moduleKey === 'managers') {
            detach_owner_content('manager', $id);
            delete_owner_service_records('manager', $id);

            $stmt = $pdo->prepare('DELETE FROM admin_users WHERE role = "manager" AND manager_id = :manager_id');
            $stmt->execute(['manager_id' => $id]);

            $stmt = $pdo->prepare('DELETE FROM managers WHERE id = :id');
            $stmt->execute(['id' => $id]);
            log_activity('admin', (int)$admin['id'], 'delete_managers', 'managers', $id);
            $pdo->commit();
            return;
        }

        if ($moduleKey === 'resellers') {
            detach_owner_content('reseller', $id);
            delete_owner_service_records('reseller', $id);

            $stmt = $pdo->prepare('DELETE FROM admin_users WHERE role = "reseller" AND reseller_id = :reseller_id AND manager_id IS NULL');
            $stmt->execute(['reseller_id' => $id]);

            $stmt = $pdo->prepare('DELETE FROM resellers WHERE id = :id');
            $stmt->execute(['id' => $id]);
            log_activity('admin', (int)$admin['id'], 'delete_resellers', 'resellers', $id);
            $pdo->commit();
            return;
        }

        $stmt = $pdo->prepare("DELETE FROM {$module['table']} WHERE id = :id");
        $stmt->execute(['id' => $id]);
        log_activity('admin', (int)$admin['id'], 'delete_' . $module['table'], $module['table'], $id);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
