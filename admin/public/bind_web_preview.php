<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/helpers.php';

header('Content-Type: application/json; charset=utf-8');

$admin = current_admin();
if (!$admin || !in_array((string)$admin['role'], ['reseller', 'manager'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Рабочий аккаунт не найден.'], JSON_UNESCAPED_UNICODE);
    exit;
}

verify_csrf();
$webUserId = trim((string)($_POST['web_user_id'] ?? ''));
if (!preg_match('/^web-[A-Za-z0-9-]{10,90}$/', $webUserId)) {
    http_response_code(422);
    echo json_encode(['error' => 'Некорректный идентификатор браузера.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ownerTable = $admin['role'] === 'manager' ? 'managers' : 'resellers';
$ownerId = $admin['role'] === 'manager' ? (int)$admin['manager_id'] : (int)$admin['reseller_id'];
$ownerStmt = db()->prepare("SELECT * FROM $ownerTable WHERE id = :id LIMIT 1");
$ownerStmt->execute(['id' => $ownerId]);
$owner = $ownerStmt->fetch();
if (!$owner || (int)$owner['is_active'] !== 1) {
    http_response_code(409);
    echo json_encode(['error' => 'Рабочий аккаунт отключён или не найден.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = db();
$pdo->beginTransaction();
try {
    $sourceEndUserId = (int)($owner['source_end_user_id'] ?? 0);
    if ($sourceEndUserId <= 0) {
        $webStmt = $pdo->prepare(
            'SELECT u.id
             FROM platform_accounts pa
             INNER JOIN end_users u ON u.id = pa.end_user_id
             WHERE pa.platform = "web" AND pa.platform_user_id = :web_user_id
               AND u.merged_into_user_id IS NULL
             LIMIT 1 FOR UPDATE'
        );
        $webStmt->execute(['web_user_id' => $webUserId]);
        $sourceEndUserId = (int)$webStmt->fetchColumn();

        if ($sourceEndUserId <= 0) {
            $legacyStmt = $pdo->prepare(
                'SELECT id FROM end_users
                 WHERE platform = "web" AND platform_user_id = :web_user_id
                   AND merged_into_user_id IS NULL
                 LIMIT 1 FOR UPDATE'
            );
            $legacyStmt->execute(['web_user_id' => $webUserId]);
            $sourceEndUserId = (int)$legacyStmt->fetchColumn();
        }

        $resellerId = $admin['role'] === 'manager' ? (int)($owner['reseller_id'] ?? 0) : $ownerId;
        $managerId = $admin['role'] === 'manager' ? $ownerId : null;
        if ($sourceEndUserId <= 0) {
            $insertUser = $pdo->prepare(
                'INSERT INTO end_users (
                    reseller_id, manager_id, platform, platform_user_id, first_name,
                    referral_code_used, client_stage, stage_updated_at, status, last_activity_at
                 ) VALUES (
                    :reseller_id, :manager_id, "web", :web_user_id, :first_name,
                    :referral_code, "partner", NOW(), "active", NOW()
                 )'
            );
            $insertUser->execute([
                'reseller_id' => $resellerId ?: null,
                'manager_id' => $managerId,
                'web_user_id' => $webUserId,
                'first_name' => (string)$admin['name'],
                'referral_code' => (string)($owner['referral_code'] ?? ''),
            ]);
            $sourceEndUserId = (int)$pdo->lastInsertId();
            $insertAccount = $pdo->prepare(
                'INSERT INTO platform_accounts (end_user_id, platform, platform_user_id, display_name)
                 VALUES (:end_user_id, "web", :web_user_id, :display_name)'
            );
            $insertAccount->execute([
                'end_user_id' => $sourceEndUserId,
                'web_user_id' => $webUserId,
                'display_name' => (string)$admin['name'],
            ]);
        } else {
            $updateUser = $pdo->prepare(
                'UPDATE end_users
                 SET reseller_id = :reseller_id, manager_id = :manager_id,
                     client_stage = "partner", stage_updated_at = NOW(), status = "active"
                 WHERE id = :id'
            );
            $updateUser->execute([
                'reseller_id' => $resellerId ?: null,
                'manager_id' => $managerId,
                'id' => $sourceEndUserId,
            ]);
        }

        $setSource = $pdo->prepare("UPDATE $ownerTable SET source_end_user_id = :end_user_id WHERE id = :id");
        $setSource->execute(['end_user_id' => $sourceEndUserId, 'id' => $ownerId]);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO admin_web_accounts (admin_user_id, web_user_id, last_seen_at, revoked_at)
         VALUES (:admin_user_id, :web_user_id, NOW(), NULL)
         ON DUPLICATE KEY UPDATE
           admin_user_id = VALUES(admin_user_id),
           last_seen_at = NOW(),
           revoked_at = NULL'
    );
    $stmt->execute([
        'admin_user_id' => (int)$admin['id'],
        'web_user_id' => $webUserId,
    ]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Не удалось привязать браузер к рабочему аккаунту.'], JSON_UNESCAPED_UNICODE);
    exit;
}

log_activity('admin', (int)$admin['id'], 'bind_web_preview', 'admin_users', (int)$admin['id'], [
    'web_user_id_hash' => hash('sha256', $webUserId),
]);

echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
