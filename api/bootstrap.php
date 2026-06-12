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

function platform_account_candidates(array $data): array
{
    $platform = normalize_platform((string)($data['platform'] ?? 'VK'));
    $platformUserId = (string)($data['platform_user_id'] ?? '');
    $username = $data['username'] ?? null;
    $firstName = $data['first_name'] ?? null;
    $lastName = $data['last_name'] ?? null;
    $displayName = trim((string)($data['display_name'] ?? trim((string)$firstName . ' ' . (string)$lastName)));
    if ($displayName === '') {
        $displayName = $username ?: null;
    }
    $accounts = [];

    if ($platformUserId !== '') {
        $accounts[] = [
            'platform' => $platform,
            'platform_user_id' => $platformUserId,
            'username' => $username,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'display_name' => $displayName,
        ];
    }

    $unique = [];
    foreach ($accounts as $account) {
        $key = $account['platform'] . ':' . $account['platform_user_id'];
        $unique[$key] = $account;
    }

    return array_values($unique);
}

function vk_referer_params(): array
{
    $referer = (string)($_SERVER['HTTP_REFERER'] ?? '');
    if ($referer === '') {
        return [];
    }

    $query = parse_url($referer, PHP_URL_QUERY);
    if (!$query) {
        return [];
    }

    parse_str($query, $params);
    return is_array($params) ? $params : [];
}

function enrich_vk_ok_platform_data(array $data): array
{
    $params = vk_referer_params();
    if (!$params) {
        return $data;
    }

    $vkClient = (string)($params['vk_client'] ?? '');
    $vkPlatform = (string)($params['vk_platform'] ?? '');
    $okUserId = (string)($params['vk_ok_user_id'] ?? '');
    $isOk = $vkClient === 'ok' || str_contains($vkPlatform, 'ok') || $okUserId !== '';

    if (!$isOk || $okUserId === '') {
        return $data;
    }

    $data['platform'] = 'OK';
    $data['platform_user_id'] = $okUserId;
    $data['platform_meta'] = array_merge($data['platform_meta'] ?? [], [
        'vk_client' => $vkClient,
        'vk_platform' => $vkPlatform,
        'vk_app_id' => $params['vk_app_id'] ?? null,
        'vk_ok_app_id' => $params['vk_ok_app_id'] ?? null,
    ]);

    return $data;
}

function require_platform_user(?array $data = null): array
{
    $data = enrich_vk_ok_platform_data($data ?? input_json());
    $platform = normalize_platform($_GET['platform'] ?? $_POST['platform'] ?? $data['platform'] ?? null);
    $platformUserId = $_GET['platform_user_id'] ?? $_POST['platform_user_id'] ?? $data['platform_user_id'] ?? null;
    $authToken = $_GET['auth_token'] ?? $_POST['auth_token'] ?? $data['auth_token'] ?? null;

    if (!$platform || !$platformUserId) {
        json_response(['error' => 'platform and platform_user_id are required'], 422);
    }

    verify_platform_auth((string)$platform, (string)$platformUserId, $authToken ? (string)$authToken : null);

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
            ensure_platform_account((int)$user['id'], $platform, (string)$platformUserId, $user['username'] ?? null, $user['first_name'] ?? null, $user['last_name'] ?? null);
        }
    }

    if (!$user) {
        json_response(['error' => 'user not found'], 404);
    }

    if (empty($user['reseller_id']) && empty($user['manager_id'])) {
        json_response(['error' => 'referral code is required'], 403);
    }

    return $user;
}

function referral_binding(?string $referralCode): ?array
{
    $referralCode = normalize_referral_code($referralCode);
    if (!$referralCode) {
        return null;
    }

    $managerStmt = db()->prepare('SELECT id, reseller_id FROM managers WHERE referral_code = :code AND is_active = 1 LIMIT 1');
    $managerStmt->execute(['code' => $referralCode]);
    $manager = $managerStmt->fetch();

    if ($manager) {
        return [
            'manager_id' => (int)$manager['id'],
            'reseller_id' => $manager['reseller_id'] ? (int)$manager['reseller_id'] : null,
        ];
    }

    $resellerStmt = db()->prepare('SELECT id FROM resellers WHERE referral_code = :code AND is_active = 1 LIMIT 1');
    $resellerStmt->execute(['code' => $referralCode]);
    $reseller = $resellerStmt->fetch();

    if ($reseller) {
        return [
            'manager_id' => null,
            'reseller_id' => (int)$reseller['id'],
        ];
    }

    return null;
}

function normalize_referral_code(?string $referralCode): ?string
{
    $referralCode = trim((string)$referralCode);
    if ($referralCode === '') {
        return null;
    }

    if (str_starts_with($referralCode, 'ref_')) {
        $referralCode = substr($referralCode, 4);
    }

    return trim($referralCode) !== '' ? trim($referralCode) : null;
}

function attach_referral_if_missing(array $user, ?string $referralCode): array
{
    if (!empty($user['reseller_id']) || !empty($user['manager_id']) || !$referralCode) {
        return $user;
    }

    $binding = referral_binding($referralCode);
    if (!$binding) {
        return $user;
    }

    $stmt = db()->prepare(
        'UPDATE end_users
         SET reseller_id = :reseller_id, manager_id = :manager_id, referral_code_used = :referral_code
         WHERE id = :id AND reseller_id IS NULL AND manager_id IS NULL'
    );
    $stmt->execute([
        'reseller_id' => $binding['reseller_id'],
        'manager_id' => $binding['manager_id'],
        'referral_code' => $referralCode,
        'id' => $user['id'],
    ]);

    $updated = db()->prepare('SELECT * FROM end_users WHERE id = :id LIMIT 1');
    $updated->execute(['id' => $user['id']]);
    return $updated->fetch() ?: $user;
}

function default_manager_card(?string $platform): ?array
{
    $platform = normalize_platform($platform ?: 'web');
    $stmt = db()->prepare(
        'SELECT m.id AS manager_id, m.name AS manager_name, m.referral_code,
                r.name AS reseller_name
         FROM default_platform_managers dpm
         JOIN managers m ON m.id = dpm.manager_id
         LEFT JOIN resellers r ON r.id = m.reseller_id
         WHERE dpm.platform = :platform
           AND dpm.is_active = 1
           AND m.is_active = 1
         LIMIT 1'
    );
    $stmt->execute(['platform' => $platform]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    return [
        'platform' => $platform,
        'manager_id' => (int)$row['manager_id'],
        'manager_name' => $row['manager_name'],
        'reseller_name' => $row['reseller_name'],
        'referral_code' => $row['referral_code'],
    ];
}

function account_link_secret(): string
{
    $config = app_config();
    $botToken = (string)($config['integrations']['telegram_bot_token'] ?? getenv('TELEGRAM_BOT_TOKEN') ?: '');
    $dbPassword = (string)($config['db']['password'] ?? '');
    return hash('sha256', $botToken . '|' . $dbPassword . '|swpro-account-link');
}

function create_account_link_token(int $endUserId, int $ttlSeconds = 900): string
{
    $expiresAt = time() + $ttlSeconds;
    $payload = $endUserId . '|' . $expiresAt;
    $signature = substr(hash_hmac('sha256', $payload, account_link_secret()), 0, 20);
    return 'l_' . $endUserId . '_' . $expiresAt . '_' . $signature;
}

function parse_account_link_token(?string $token): ?int
{
    if (!$token) {
        return null;
    }

    if (str_starts_with($token, 'link_')) {
        $token = substr($token, 5);
    }

    $parts = explode('_', $token);
    if (count($parts) !== 4 || $parts[0] !== 'l') {
        return null;
    }

    [, $endUserId, $expiresAt, $signature] = $parts;
    if ((int)$endUserId <= 0 || (int)$expiresAt < time()) {
        return null;
    }

    $payload = $endUserId . '|' . $expiresAt;
    $expected = substr(hash_hmac('sha256', $payload, account_link_secret()), 0, 20);
    if (!hash_equals($expected, $signature)) {
        return null;
    }

    return (int)$endUserId;
}

function create_or_get_user(array $data): array
{
    $data = enrich_vk_ok_platform_data($data);
    $platform = normalize_platform($data['platform'] ?? 'VK');
    $platformUserId = (string)($data['platform_user_id'] ?? '');
    if ($platformUserId === '') {
        json_response(['error' => 'platform_user_id is required'], 422);
    }

    $accounts = platform_account_candidates($data);
    $linkTargetUserId = parse_account_link_token($data['link_token'] ?? null);

    $stmt = db()->prepare(
        'SELECT u.*
         FROM platform_accounts pa
         JOIN end_users u ON u.id = pa.end_user_id
         WHERE pa.platform = :platform AND pa.platform_user_id = :platform_user_id
         LIMIT 1'
    );
    foreach ($accounts as $account) {
        $stmt->execute([
            'platform' => $account['platform'],
            'platform_user_id' => $account['platform_user_id'],
        ]);
        $existing = $stmt->fetch();
        if ($existing) {
            ensure_platform_account((int)$existing['id'], $account['platform'], $account['platform_user_id'], $account['username'] ?? null, $account['first_name'] ?? null, $account['last_name'] ?? null, $account['display_name'] ?? null);
            $touch = db()->prepare('UPDATE end_users SET last_activity_at = NOW() WHERE id = :id');
            $touch->execute(['id' => $existing['id']]);
            return attach_referral_if_missing($existing, $data['referral_code'] ?? null);
        }
    }

    if ($linkTargetUserId) {
        $targetStmt = db()->prepare('SELECT * FROM end_users WHERE id = :id LIMIT 1');
        $targetStmt->execute(['id' => $linkTargetUserId]);
        $targetUser = $targetStmt->fetch();
        if ($targetUser) {
            ensure_platform_account((int)$targetUser['id'], $platform, $platformUserId, $data['username'] ?? null, $data['first_name'] ?? null, $data['last_name'] ?? null, null, false);
            $touch = db()->prepare('UPDATE end_users SET last_activity_at = NOW() WHERE id = :id');
            $touch->execute(['id' => $targetUser['id']]);
            return attach_referral_if_missing($targetUser, $data['referral_code'] ?? null);
        }
    }

    $legacyStmt = db()->prepare('SELECT * FROM end_users WHERE platform = :platform AND platform_user_id = :platform_user_id LIMIT 1');
    foreach ($accounts as $account) {
        $legacyStmt->execute([
            'platform' => $account['platform'],
            'platform_user_id' => $account['platform_user_id'],
        ]);
        $legacyUser = $legacyStmt->fetch();
        if ($legacyUser) {
            ensure_platform_account((int)$legacyUser['id'], $account['platform'], $account['platform_user_id'], $account['username'] ?? null, $account['first_name'] ?? null, $account['last_name'] ?? null, $account['display_name'] ?? null, false);
            $touch = db()->prepare('UPDATE end_users SET last_activity_at = NOW() WHERE id = :id');
            $touch->execute(['id' => $legacyUser['id']]);
            return attach_referral_if_missing($legacyUser, $data['referral_code'] ?? null);
        }
    }

    $resellerId = null;
    $managerId = null;
    $referralCode = normalize_referral_code($data['referral_code'] ?? null);

    $binding = referral_binding($referralCode);
    if ($binding) {
        $managerId = $binding['manager_id'];
        $resellerId = $binding['reseller_id'];
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

    foreach ($accounts as $account) {
        ensure_platform_account((int)$userId, $account['platform'], $account['platform_user_id'], $account['username'] ?? null, $account['first_name'] ?? null, $account['last_name'] ?? null, $account['display_name'] ?? null);
    }

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

function ensure_platform_account(
    int $endUserId,
    string $platform,
    string $platformUserId,
    ?string $username = null,
    ?string $firstName = null,
    ?string $lastName = null,
    ?string $displayName = null,
    bool $moveExisting = false
): void
{
    $displayName = trim((string)($displayName ?? trim((string)$firstName . ' ' . (string)$lastName)));
    if ($displayName === '') {
        $displayName = $username ?: null;
    }

    $sql = 'INSERT INTO platform_accounts (end_user_id, platform, platform_user_id, username, first_name, last_name, display_name)
            VALUES (:end_user_id, :platform, :platform_user_id, :username, :first_name, :last_name, :display_name)
            ON DUPLICATE KEY UPDATE username = VALUES(username), first_name = VALUES(first_name), last_name = VALUES(last_name), display_name = VALUES(display_name)';
    if ($moveExisting) {
        $sql = 'INSERT INTO platform_accounts (end_user_id, platform, platform_user_id, username, first_name, last_name, display_name)
                VALUES (:end_user_id, :platform, :platform_user_id, :username, :first_name, :last_name, :display_name)
                ON DUPLICATE KEY UPDATE end_user_id = VALUES(end_user_id), username = VALUES(username), first_name = VALUES(first_name), last_name = VALUES(last_name), display_name = VALUES(display_name)';
    }

    $stmt = db()->prepare($sql);
    $stmt->execute([
        'end_user_id' => $endUserId,
        'platform' => $platform,
        'platform_user_id' => $platformUserId,
        'username' => $username,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'display_name' => $displayName,
    ]);
}

function telegram_auth_token(string $platformUserId): ?string
{
    $config = app_config();
    $botToken = $config['integrations']['telegram_bot_token'] ?? getenv('TELEGRAM_BOT_TOKEN') ?: '';
    if ($botToken === '') {
        return null;
    }

    return hash_hmac('sha256', 'telegram:' . $platformUserId, $botToken);
}

function verify_platform_auth(string $platform, string $platformUserId, ?string $authToken): void
{
    if ($platform !== 'telegram') {
        return;
    }

    $expected = telegram_auth_token($platformUserId);
    if ($expected === null) {
        json_response(['error' => 'telegram auth token is not configured'], 500);
    }
    if (!$authToken || !hash_equals($expected, $authToken)) {
        json_response(['error' => 'telegram auth token is invalid'], 403);
    }
}


function client_owner_scope(array $user, string $alias = ''): array
{
    $prefix = $alias !== '' ? $alias . '.' : '';
    $parts = [$prefix . 'owner_type IS NULL'];
    $params = [];

    if (!empty($user['reseller_id'])) {
        $parts[] = '(' . $prefix . 'owner_type = "reseller" AND ' . $prefix . 'owner_id = :client_reseller_id)';
        $params['client_reseller_id'] = (int)$user['reseller_id'];
    }
    if (!empty($user['manager_id'])) {
        $parts[] = '(' . $prefix . 'owner_type = "manager" AND ' . $prefix . 'owner_id = :client_manager_id)';
        $params['client_manager_id'] = (int)$user['manager_id'];
    }

    return ['(' . implode(' OR ', $parts) . ')', $params];
}
