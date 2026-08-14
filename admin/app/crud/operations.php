<?php

require_once __DIR__ . '/../core/referral_codes.php';

function normalize_merge_text(?string $value): string
{
    $value = trim((string)$value);
    $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    $value = strtr($value, [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
        'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'i', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch',
        'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        'А' => 'a', 'Б' => 'b', 'В' => 'v', 'Г' => 'g', 'Д' => 'd', 'Е' => 'e', 'Ё' => 'e',
        'Ж' => 'zh', 'З' => 'z', 'И' => 'i', 'Й' => 'i', 'К' => 'k', 'Л' => 'l', 'М' => 'm',
        'Н' => 'n', 'О' => 'o', 'П' => 'p', 'Р' => 'r', 'С' => 's', 'Т' => 't', 'У' => 'u',
        'Ф' => 'f', 'Х' => 'h', 'Ц' => 'c', 'Ч' => 'ch', 'Ш' => 'sh', 'Щ' => 'sch',
        'Ъ' => '', 'Ы' => 'y', 'Ь' => '', 'Э' => 'e', 'Ю' => 'yu', 'Я' => 'ya',
    ]);

    return preg_replace('/[^a-z0-9]+/u', '', $value) ?? '';
}

function nullable_int_value(mixed $value): ?int
{
    return $value === null || $value === '' ? null : (int)$value;
}

function profile_template_id_for_module(string $moduleKey, int $recordId): ?int
{
    if (!in_array($moduleKey, ['managers', 'resellers'], true) || $recordId <= 0) {
        return null;
    }

    $ownerType = $moduleKey === 'managers' ? 'manager' : 'reseller';
    try {
        $profile = ensure_consultant_profile($ownerType, $recordId);
    } catch (Throwable $e) {
        return null;
    }

    $templateId = nullable_int_value($profile['template_id'] ?? null);
    if ($templateId) {
        return $templateId;
    }

    return null;
}

function sync_active_leads_assignment(int $endUserId, ?int $resellerId, ?int $managerId): int
{
    $stmt = db()->prepare(
        'UPDATE leads
         SET reseller_id = :reseller_id,
             manager_id = :manager_id
         WHERE end_user_id = :end_user_id
           AND status IN ("new", "contacted", "interested")'
    );
    $stmt->execute([
        'reseller_id' => $resellerId,
        'manager_id' => $managerId,
        'end_user_id' => $endUserId,
    ]);

    return $stmt->rowCount();
}

function sync_consultant_notifications_assignment(int $endUserId, ?int $resellerId, ?int $managerId): int
{
    $stmt = db()->prepare(
        'UPDATE IGNORE consultant_notifications
         SET reseller_id = :reseller_id,
             manager_id = :manager_id
         WHERE end_user_id = :end_user_id
           AND is_read = 0'
    );
    $stmt->execute([
        'reseller_id' => $resellerId,
        'manager_id' => $managerId,
        'end_user_id' => $endUserId,
    ]);

    return $stmt->rowCount();
}

function log_end_user_transfer(
    int $endUserId,
    ?int $oldResellerId,
    ?int $oldManagerId,
    ?int $newResellerId,
    ?int $newManagerId,
    array $admin,
    string $action,
    array $extra = []
): void {
    if ($oldResellerId === $newResellerId && $oldManagerId === $newManagerId) {
        return;
    }

    log_activity('admin', (int)$admin['id'], $action, 'end_users', $endUserId, $extra + [
        'old_reseller_id' => $oldResellerId,
        'old_manager_id' => $oldManagerId,
        'new_reseller_id' => $newResellerId,
        'new_manager_id' => $newManagerId,
    ]);
}

function sync_user_assignment_from_lead(int $leadId, array $leadBefore, array $payload, array $admin): void
{
    $endUserId = nullable_int_value($payload['end_user_id'] ?? $leadBefore['end_user_id'] ?? null);
    if (!$endUserId) {
        return;
    }

    $oldLeadResellerId = nullable_int_value($leadBefore['reseller_id'] ?? null);
    $oldLeadManagerId = nullable_int_value($leadBefore['manager_id'] ?? null);
    $newResellerId = nullable_int_value($payload['reseller_id'] ?? null);
    $newManagerId = nullable_int_value($payload['manager_id'] ?? null);
    if ($newResellerId === null && $newManagerId === null) {
        return;
    }
    if ($oldLeadResellerId === $newResellerId && $oldLeadManagerId === $newManagerId) {
        return;
    }

    $stmt = db()->prepare('SELECT reseller_id, manager_id FROM end_users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $endUserId]);
    $userBefore = $stmt->fetch();
    if (!$userBefore) {
        return;
    }

    $oldUserResellerId = nullable_int_value($userBefore['reseller_id'] ?? null);
    $oldUserManagerId = nullable_int_value($userBefore['manager_id'] ?? null);

    $update = db()->prepare(
        'UPDATE end_users
         SET reseller_id = :reseller_id,
             manager_id = :manager_id
         WHERE id = :id'
    );
    $update->execute([
        'reseller_id' => $newResellerId,
        'manager_id' => $newManagerId,
        'id' => $endUserId,
    ]);

    $updatedLeads = sync_active_leads_assignment($endUserId, $newResellerId, $newManagerId);
    $updatedNotifications = sync_consultant_notifications_assignment($endUserId, $newResellerId, $newManagerId);

    log_end_user_transfer(
        $endUserId,
        $oldUserResellerId,
        $oldUserManagerId,
        $newResellerId,
        $newManagerId,
        $admin,
        'transfer_end_user_from_lead',
        [
            'lead_id' => $leadId,
            'updated_active_leads' => $updatedLeads,
            'updated_unread_notifications' => $updatedNotifications,
        ]
    );
}

function save_record(string $moduleKey, array $module, array $payload, ?int $id, array $admin): int
{
    $payload = apply_role_defaults($moduleKey, $payload, $admin, $id);
    $columns = array_keys($payload);

    if ($id) {
        $before = null;
        $referralCodeBefore = null;
        if (in_array($moduleKey, ['managers', 'resellers'], true)) {
            $referralStmt = db()->prepare("SELECT referral_code FROM {$module['table']} WHERE id = :id LIMIT 1");
            $referralStmt->execute(['id' => $id]);
            $referralCodeBefore = trim((string)$referralStmt->fetchColumn());
        }
        if ($moduleKey === 'users') {
            $beforeStmt = db()->prepare('SELECT reseller_id, manager_id, client_stage FROM end_users WHERE id = :id LIMIT 1');
            $beforeStmt->execute(['id' => $id]);
            $before = $beforeStmt->fetch();
        } elseif ($moduleKey === 'leads') {
            $beforeStmt = db()->prepare('SELECT end_user_id, reseller_id, manager_id FROM leads WHERE id = :id LIMIT 1');
            $beforeStmt->execute(['id' => $id]);
            $before = $beforeStmt->fetch();
        }

        $assignments = implode(', ', array_map(static fn($column) => "`$column` = :$column", $columns));
        $payload['id'] = $id;
        $stmt = db()->prepare("UPDATE {$module['table']} SET $assignments WHERE id = :id");
        $referralCodeAfter = trim((string)($payload['referral_code'] ?? $referralCodeBefore));
        $renamingReferral = $referralCodeBefore !== null
            && $referralCodeBefore !== ''
            && $referralCodeAfter !== ''
            && $referralCodeBefore !== $referralCodeAfter;
        if ($renamingReferral) {
            db()->beginTransaction();
        }
        try {
            if ($renamingReferral) {
                referral_code_prepare_rename(
                    $moduleKey === 'managers' ? 'manager' : 'reseller',
                    $id,
                    $referralCodeAfter
                );
            }
            $stmt->execute($payload);
            if ($renamingReferral) {
                referral_code_sync_rename(
                    $moduleKey === 'managers' ? 'manager' : 'reseller',
                    $id,
                    $referralCodeBefore,
                    $referralCodeAfter
                );
                db()->commit();
            }
        } catch (Throwable $e) {
            if ($renamingReferral && db()->inTransaction()) {
                db()->rollBack();
            }
            throw $e;
        }
        log_activity('admin', (int)$admin['id'], 'update_' . $module['table'], $module['table'], $id);

        if ($moduleKey === 'users' && $before) {
            $oldResellerId = $before['reseller_id'] !== null ? (int)$before['reseller_id'] : null;
            $oldManagerId = $before['manager_id'] !== null ? (int)$before['manager_id'] : null;
            $newResellerId = $payload['reseller_id'] !== null ? (int)$payload['reseller_id'] : null;
            $newManagerId = $payload['manager_id'] !== null ? (int)$payload['manager_id'] : null;
            if ($oldResellerId !== $newResellerId || $oldManagerId !== $newManagerId) {
                $updatedLeads = sync_active_leads_assignment($id, $newResellerId, $newManagerId);
                $updatedNotifications = sync_consultant_notifications_assignment($id, $newResellerId, $newManagerId);
                log_end_user_transfer(
                    $id,
                    $oldResellerId,
                    $oldManagerId,
                    $newResellerId,
                    $newManagerId,
                    $admin,
                    'transfer_end_user',
                    [
                        'updated_active_leads' => $updatedLeads,
                        'updated_unread_notifications' => $updatedNotifications,
                    ]
                );
            }
            $oldStage = (string)($before['client_stage'] ?? 'new');
            $newStage = (string)($payload['client_stage'] ?? $oldStage);
            if ($oldStage !== $newStage) {
                $touchStage = db()->prepare('UPDATE end_users SET stage_updated_at = NOW() WHERE id = :id');
                $touchStage->execute(['id' => $id]);
                $history = db()->prepare(
                    'INSERT INTO client_stage_history (
                        end_user_id, previous_stage, new_stage, source, actor_id
                     ) VALUES (
                        :end_user_id, :previous_stage, :new_stage, :source, :actor_id
                     )'
                );
                $history->execute([
                    'end_user_id' => $id,
                    'previous_stage' => $oldStage,
                    'new_stage' => $newStage,
                    'source' => $admin['role'] === 'manager' ? 'consultant' : ($admin['role'] === 'reseller' ? 'leader' : 'admin'),
                    'actor_id' => $admin['id'],
                ]);
            }
        }
        if ($moduleKey === 'leads' && $before) {
            sync_user_assignment_from_lead($id, $before, $payload, $admin);
        }

        return $id;
    }

    $columnSql = implode(', ', array_map(static fn($column) => "`$column`", $columns));
    $placeholderSql = implode(', ', array_map(static fn($column) => ":$column", $columns));
    $stmt = db()->prepare("INSERT INTO {$module['table']} ($columnSql) VALUES ($placeholderSql)");
    $stmt->execute($payload);
    $newId = (int)db()->lastInsertId();
    log_activity('admin', (int)$admin['id'], 'create_' . $module['table'], $module['table'], $newId);
    return $newId;
}
