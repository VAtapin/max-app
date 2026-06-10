<?php

require_once __DIR__ . '/../admin/app/core/db.php';
require_once __DIR__ . '/../admin/app/core/helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function input_json(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function require_platform_user(): array
{
    $data = input_json();
    $platform = $_GET['platform'] ?? $_POST['platform'] ?? $data['platform'] ?? null;
    $platformUserId = $_GET['platform_user_id'] ?? $_POST['platform_user_id'] ?? $data['platform_user_id'] ?? null;

    if (!$platform || !$platformUserId) {
        json_response(['error' => 'platform and platform_user_id are required'], 422);
    }

    $stmt = db()->prepare(
        'SELECT u.*
         FROM platform_accounts pa
         JOIN end_users u ON u.id = pa.end_user_id
         WHERE pa.platform = :platform AND pa.platform_user_id = :platform_user_id
         LIMIT 1'
    );
    $stmt->execute([
        'platform' => $platform,
        'platform_user_id' => $platformUserId,
    ]);
    $user = $stmt->fetch();

    if (!$user) {
        $legacyStmt = db()->prepare('SELECT * FROM end_users WHERE platform = :platform AND platform_user_id = :platform_user_id LIMIT 1');
        $legacyStmt->execute([
            'platform' => $platform,
            'platform_user_id' => $platformUserId,
        ]);
        $user = $legacyStmt->fetch();
        if ($user) {
            ensure_platform_account((int)$user['id'], $platform, (string)$platformUserId, $user['username'] ?? null);
        }
    }

    if (!$user) {
        json_response(['error' => 'user not found'], 404);
    }

    return $user;
}

function create_or_get_user(array $data): array
{
    $platform = $data['platform'] ?? 'vk';
    $platformUserId = (string)($data['platform_user_id'] ?? '');
    if ($platformUserId === '') {
        json_response(['error' => 'platform_user_id is required'], 422);
    }

    $stmt = db()->prepare(
        'SELECT u.*
         FROM platform_accounts pa
         JOIN end_users u ON u.id = pa.end_user_id
         WHERE pa.platform = :platform AND pa.platform_user_id = :platform_user_id
         LIMIT 1'
    );
    $stmt->execute(['platform' => $platform, 'platform_user_id' => $platformUserId]);
    $existing = $stmt->fetch();
    if ($existing) {
        $touch = db()->prepare('UPDATE end_users SET last_activity_at = NOW() WHERE id = :id');
        $touch->execute(['id' => $existing['id']]);
        return $existing;
    }

    $legacyStmt = db()->prepare('SELECT * FROM end_users WHERE platform = :platform AND platform_user_id = :platform_user_id LIMIT 1');
    $legacyStmt->execute(['platform' => $platform, 'platform_user_id' => $platformUserId]);
    $legacyUser = $legacyStmt->fetch();
    if ($legacyUser) {
        ensure_platform_account((int)$legacyUser['id'], $platform, $platformUserId, $data['username'] ?? null);
        $touch = db()->prepare('UPDATE end_users SET last_activity_at = NOW() WHERE id = :id');
        $touch->execute(['id' => $legacyUser['id']]);
        return $legacyUser;
    }

    $resellerId = null;
    $managerId = null;
    $referralCode = $data['referral_code'] ?? null;

    if ($referralCode) {
        $managerStmt = db()->prepare('SELECT id, reseller_id FROM managers WHERE referral_code = :code AND is_active = 1 LIMIT 1');
        $managerStmt->execute(['code' => $referralCode]);
        $manager = $managerStmt->fetch();

        if ($manager) {
            $managerId = (int)$manager['id'];
            $resellerId = $manager['reseller_id'] ? (int)$manager['reseller_id'] : null;
        } else {
            $resellerStmt = db()->prepare('SELECT id FROM resellers WHERE referral_code = :code AND is_active = 1 LIMIT 1');
            $resellerStmt->execute(['code' => $referralCode]);
            $reseller = $resellerStmt->fetch();
            $resellerId = $reseller ? (int)$reseller['id'] : null;
        }
    }

    $insert = db()->prepare(
        'INSERT INTO end_users (reseller_id, manager_id, platform, platform_user_id, username, first_name, last_name, referral_code_used, last_activity_at)
         VALUES (:reseller_id, :manager_id, :platform, :platform_user_id, :username, :first_name, :last_name, :referral_code_used, NOW())'
    );
    $insert->execute([
        'reseller_id' => $resellerId,
        'manager_id' => $managerId,
        'platform' => $platform,
        'platform_user_id' => $platformUserId,
        'username' => $data['username'] ?? null,
        'first_name' => $data['first_name'] ?? null,
        'last_name' => $data['last_name'] ?? null,
        'referral_code_used' => $referralCode,
    ]);

    $userId = (int)db()->lastInsertId();

    $account = db()->prepare(
        'INSERT INTO platform_accounts (end_user_id, platform, platform_user_id, username)
         VALUES (:end_user_id, :platform, :platform_user_id, :username)'
    );
    $account->execute([
        'end_user_id' => $userId,
        'platform' => $platform,
        'platform_user_id' => $platformUserId,
        'username' => $data['username'] ?? null,
    ]);

    $log = db()->prepare(
        'INSERT INTO activity_logs (actor_type, actor_id, action, entity_type, entity_id, details)
         VALUES ("system", NULL, "create_user", "end_users", :entity_id, :details)'
    );
    $log->execute([
        'entity_id' => $userId,
        'details' => json_encode(['platform' => $platform, 'referral_code' => $referralCode], JSON_UNESCAPED_UNICODE),
    ]);

    if ($referralCode) {
        $registration = db()->prepare(
            'UPDATE referral_links
             SET registrations_count = registrations_count + 1
             WHERE referral_code = :referral_code AND platform = :platform'
        );
        $registration->execute(['referral_code' => $referralCode, 'platform' => $platform]);
    }

    $created = db()->prepare('SELECT * FROM end_users WHERE id = :id LIMIT 1');
    $created->execute(['id' => $userId]);
    return $created->fetch();
}

function ensure_platform_account(int $endUserId, string $platform, string $platformUserId, ?string $username = null): void
{
    $stmt = db()->prepare(
        'INSERT INTO platform_accounts (end_user_id, platform, platform_user_id, username)
         VALUES (:end_user_id, :platform, :platform_user_id, :username)
         ON DUPLICATE KEY UPDATE end_user_id = VALUES(end_user_id), username = VALUES(username)'
    );
    $stmt->execute([
        'end_user_id' => $endUserId,
        'platform' => $platform,
        'platform_user_id' => $platformUserId,
        'username' => $username,
    ]);
}
