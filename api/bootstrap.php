<?php

require_once __DIR__ . '/../admin/app/core/db.php';
require_once __DIR__ . '/../admin/app/core/helpers.php';
require_once __DIR__ . '/../admin/app/core/content_ownership.php';

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

    $staffPreview = staff_preview_user((string)$platform, (string)$platformUserId);
    if ($staffPreview) {
        return $staffPreview;
    }

    reject_staff_client_registration((string)$platform, (string)$platformUserId);

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

    if (isset($data['username']) || isset($data['first_name']) || isset($data['last_name']) || isset($data['display_name'])) {
        ensure_platform_account(
            (int)$user['id'],
            $platform,
            (string)$platformUserId,
            $data['username'] ?? null,
            $data['first_name'] ?? null,
            $data['last_name'] ?? null,
            $data['display_name'] ?? null
        );
    }

    if (empty($user['reseller_id']) && empty($user['manager_id'])) {
        json_response(['error' => 'referral_required'], 403);
    }

    $user['current_platform'] = $platform;
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
    $referralCode = strtoupper(trim((string)$referralCode));
    if ($referralCode === '') {
        return null;
    }

    if (str_starts_with(strtolower($referralCode), 'ref_')) {
        $referralCode = substr($referralCode, 4);
    }

    $referralCode = preg_replace('/\s+/', '-', $referralCode) ?? '';
    $referralCode = preg_replace('/[^A-Z0-9_-]/', '', $referralCode) ?? '';
    $referralCode = preg_replace('/[-_]{2,}/', '-', $referralCode) ?? '';
    $referralCode = trim($referralCode, '-_');

    return trim($referralCode) !== '' ? trim($referralCode) : null;
}

function normalize_external_platform_id(?string $value): string
{
    $value = trim((string)$value);
    if (preg_match('/^id(\d+)$/i', $value, $matches)) {
        return $matches[1];
    }

    return $value;
}

function staff_identity_for_platform(string $platform, string $platformUserId): ?array
{
    $platform = normalize_platform($platform);
    $platformUserId = normalize_external_platform_id($platformUserId);
    if ($platformUserId === '') {
        return null;
    }

    $field = match ($platform) {
        'telegram' => 'telegram_id',
        'VK' => 'vk_id',
        'MAX' => 'max_id',
        default => null,
    };

    if ($platform === 'web') {
        $stmt = db()->prepare(
            'SELECT au.*
             FROM admin_web_accounts awa
             INNER JOIN admin_users au ON au.id = awa.admin_user_id
             WHERE awa.web_user_id = :web_user_id
               AND awa.revoked_at IS NULL
               AND au.is_active = 1
             LIMIT 1'
        );
        $stmt->execute(['web_user_id' => $platformUserId]);
        $admin = $stmt->fetch();
        if ($admin) {
            $touch = db()->prepare('UPDATE admin_web_accounts SET last_seen_at = NOW() WHERE web_user_id = :web_user_id');
            $touch->execute(['web_user_id' => $platformUserId]);
            return $admin;
        }
        return null;
    }

    if ($field === null) {
        return null;
    }

    $adminStmt = db()->prepare("SELECT * FROM admin_users WHERE role IN ('reseller', 'manager') AND is_active = 1 AND $field IS NOT NULL AND $field <> '' AND REPLACE(LOWER($field), 'id', '') = :platform_user_id LIMIT 1");
    $adminStmt->execute(['platform_user_id' => strtolower($platformUserId)]);
    $admin = $adminStmt->fetch();
    return $admin ?: null;
}

function staff_platform_account_exists(string $platform, string $platformUserId): bool
{
    if (staff_identity_for_platform($platform, $platformUserId) !== null) {
        return true;
    }

    $platform = normalize_platform($platform);
    $platformUserId = strtolower(normalize_external_platform_id($platformUserId));
    $field = match ($platform) {
        'telegram' => 'telegram_id',
        'VK' => 'vk_id',
        'MAX' => 'max_id',
        default => null,
    };
    if ($field === null || $platformUserId === '') {
        return false;
    }

    $managerStmt = db()->prepare("SELECT COUNT(*) FROM managers WHERE $field IS NOT NULL AND $field <> '' AND REPLACE(LOWER($field), 'id', '') = :platform_user_id");
    $managerStmt->execute(['platform_user_id' => $platformUserId]);
    if ((int)$managerStmt->fetchColumn() > 0) {
        return true;
    }

    $adminStmt = db()->prepare("SELECT COUNT(*) FROM admin_users WHERE role = 'superadmin' AND $field IS NOT NULL AND $field <> '' AND REPLACE(LOWER($field), 'id', '') = :platform_user_id");
    $adminStmt->execute(['platform_user_id' => $platformUserId]);
    return (int)$adminStmt->fetchColumn() > 0;
}

function staff_preview_user(string $platform, string $platformUserId): ?array
{
    $admin = staff_identity_for_platform($platform, $platformUserId);
    if (!$admin) {
        return null;
    }

    $role = (string)($admin['role'] ?? '');
    $ownerTable = $role === 'manager' ? 'managers' : 'resellers';
    $ownerId = $role === 'manager' ? (int)($admin['manager_id'] ?? 0) : (int)($admin['reseller_id'] ?? 0);
    if ($ownerId <= 0) {
        return null;
    }

    $ownerStmt = db()->prepare("SELECT id, source_end_user_id, is_active FROM $ownerTable WHERE id = :id LIMIT 1");
    $ownerStmt->execute(['id' => $ownerId]);
    $owner = $ownerStmt->fetch();
    if (!$owner || (int)$owner['is_active'] !== 1) {
        return null;
    }

    $sourceEndUserId = (int)($owner['source_end_user_id'] ?? 0);
    if ($sourceEndUserId <= 0 && $platform !== 'web') {
        $sourceStmt = db()->prepare(
            'SELECT u.id
             FROM platform_accounts pa
             INNER JOIN end_users u ON u.id = pa.end_user_id
             WHERE pa.platform = :platform AND REPLACE(LOWER(pa.platform_user_id), "id", "") = :platform_user_id
               AND u.merged_into_user_id IS NULL
             LIMIT 1'
        );
        $sourceStmt->execute([
            'platform' => normalize_platform($platform),
            'platform_user_id' => strtolower(normalize_external_platform_id($platformUserId)),
        ]);
        $sourceEndUserId = (int)$sourceStmt->fetchColumn();
        if ($sourceEndUserId > 0) {
            $setSource = db()->prepare("UPDATE $ownerTable SET source_end_user_id = :end_user_id WHERE id = :id AND source_end_user_id IS NULL");
            $setSource->execute(['end_user_id' => $sourceEndUserId, 'id' => $ownerId]);
        }
    }

    if ($sourceEndUserId <= 0) {
        return null;
    }

    $userStmt = db()->prepare('SELECT * FROM end_users WHERE id = :id AND merged_into_user_id IS NULL LIMIT 1');
    $userStmt->execute(['id' => $sourceEndUserId]);
    $user = $userStmt->fetch();
    if (!$user) {
        return null;
    }

    if ($role === 'manager') {
        $managerStmt = db()->prepare('SELECT reseller_id FROM managers WHERE id = :id AND is_active = 1 LIMIT 1');
        $managerStmt->execute(['id' => $ownerId]);
        $user['manager_id'] = $ownerId;
        $user['reseller_id'] = (int)$managerStmt->fetchColumn() ?: null;
    } else {
        $user['manager_id'] = null;
        $user['reseller_id'] = $ownerId;
    }
    $user['current_platform'] = normalize_platform($platform);
    $user['staff_preview'] = true;
    $user['staff_role'] = $role;
    $user['staff_admin_user_id'] = (int)$admin['id'];
    return $user;
}

function reject_staff_client_registration(string $platform, string $platformUserId): void
{
    if (staff_platform_account_exists($platform, $platformUserId)) {
        json_response(['error' => 'staff_client_registration_blocked'], 403);
    }
}

function attach_referral_if_missing(array $user, ?string $referralCode): array
{
    $referralCode = normalize_referral_code($referralCode);
    if (!empty($user['staff_preview']) || !empty($user['onboarding_completed_at']) || !$referralCode) {
        return $user;
    }

    $binding = referral_binding($referralCode);
    if (!$binding) {
        return $user;
    }

    $stmt = db()->prepare(
        'UPDATE end_users
         SET reseller_id = :reseller_id, manager_id = :manager_id, referral_code_used = :referral_code
         WHERE id = :id AND onboarding_completed_at IS NULL AND merged_into_user_id IS NULL'
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

function fill_user_names_if_missing(array $user, array $data): array
{
    $firstName = trim((string)($data['first_name'] ?? ''));
    $lastName = trim((string)($data['last_name'] ?? ''));
    $currentFirstName = trim((string)($user['first_name'] ?? ''));
    $currentLastName = trim((string)($user['last_name'] ?? ''));
    $technicalPlaceholder = in_array((string)($user['platform'] ?? ''), ['VK', 'OK', 'web'], true)
        && in_array($currentFirstName, ['VK', 'Web'], true)
        && $currentLastName === 'User';
    $assignments = [];
    $params = ['id' => $user['id']];

    if ($technicalPlaceholder && $firstName === '' && $lastName === '') {
        $assignments[] = 'first_name = NULL';
        $assignments[] = 'last_name = NULL';
        $clearAccount = db()->prepare(
            'UPDATE platform_accounts
             SET first_name = NULL, last_name = NULL, display_name = NULL
             WHERE end_user_id = :end_user_id
               AND platform IN ("VK", "OK", "web")
               AND first_name IN ("VK", "Web")
               AND last_name = "User"'
        );
        $clearAccount->execute(['end_user_id' => $user['id']]);
    }
    if ($firstName !== '' && ($currentFirstName === '' || $technicalPlaceholder)) {
        $assignments[] = 'first_name = :first_name';
        $params['first_name'] = $firstName;
    }
    if ($lastName !== '' && ($currentLastName === '' || $technicalPlaceholder)) {
        $assignments[] = 'last_name = :last_name';
        $params['last_name'] = $lastName;
    }
    if (!$assignments) {
        return $user;
    }

    $update = db()->prepare('UPDATE end_users SET ' . implode(', ', $assignments) . ' WHERE id = :id');
    $update->execute($params);
    $updated = db()->prepare('SELECT * FROM end_users WHERE id = :id LIMIT 1');
    $updated->execute(['id' => $user['id']]);
    return $updated->fetch() ?: $user;
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

function append_query_param(string $url, string $name, string $value): string
{
    $separator = str_contains($url, '?') ? '&' : '?';
    return $url . $separator . rawurlencode($name) . '=' . rawurlencode($value);
}

function account_link_urls(string $token): array
{
    $config = app_config();
    $miniAppUrl = (string)($config['integrations']['mini_app_url'] ?? getenv('SWPRO_MINI_APP_URL') ?: '');
    if ($miniAppUrl === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $miniAppUrl = $host !== '' ? $scheme . '://' . $host . '/vk-mini-app/' : '../vk-mini-app/';
    }

    $telegramBot = trim((string)($config['integrations']['telegram_bot_username'] ?? getenv('TELEGRAM_BOT_USERNAME') ?: 'SWProAssistant_bot'));
    $vkAppId = trim((string)($config['integrations']['vk_app_id'] ?? getenv('VK_APP_ID') ?: ''));

    return [
        'mini_app' => $miniAppUrl ? append_query_param($miniAppUrl, 'link_token', $token) : '',
        'telegram' => $telegramBot !== '' ? 'https://t.me/' . rawurlencode(ltrim($telegramBot, '@')) . '?start=' . rawurlencode('link_' . $token) : '',
        'vk' => $vkAppId !== '' ? 'https://vk.com/app' . rawurlencode($vkAppId) . '#link_token=' . rawurlencode($token) : '',
    ];
}

function account_link_payload(array $user, int $ttlSeconds = 900): array
{
    $token = create_account_link_token((int)$user['id'], $ttlSeconds);
    return [
        'token' => $token,
        'expires_in' => $ttlSeconds,
        'links' => account_link_urls($token),
    ];
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

function account_suggestion_age(array $user): ?int
{
    if (!empty($user['birth_date'])) {
        try {
            $birthDate = new DateTimeImmutable((string)$user['birth_date']);
            $today = new DateTimeImmutable('today');
            return $birthDate <= $today ? $birthDate->diff($today)->y : null;
        } catch (Throwable) {
            return null;
        }
    }

    $age = (int)($user['age_years'] ?? 0);
    return $age > 0 ? $age : null;
}

function find_similar_account_suggestions(array $user, int $limit = 3): array
{
    $city = trim((string)($user['city'] ?? ''));
    $currentPlatform = normalize_platform((string)($user['current_platform'] ?? $user['platform'] ?? 'web'));
    $birthDate = trim((string)($user['birth_date'] ?? ''));
    $profileAge = account_suggestion_age($user);

    if ((int)($user['id'] ?? 0) <= 0 || $city === '' || ($birthDate === '' && $profileAge === null)) {
        return [];
    }

    $where = [
        'u.id <> :user_id',
        'u.merged_into_user_id IS NULL',
        'COALESCE(pa.platform, u.platform) <> :current_platform',
        'LOWER(TRIM(u.city)) = LOWER(TRIM(:city))',
    ];
    $params = [
        'user_id' => (int)$user['id'],
        'current_platform' => $currentPlatform,
        'city' => $city,
    ];

    if (!empty($user['manager_id'])) {
        $where[] = 'u.manager_id = :manager_id';
        $params['manager_id'] = (int)$user['manager_id'];
    } elseif (!empty($user['reseller_id'])) {
        $where[] = 'u.reseller_id = :reseller_id';
        $params['reseller_id'] = (int)$user['reseller_id'];
    } else {
        return [];
    }

    if ($birthDate !== '') {
        $ageFallback = $profileAge !== null ? ' OR (u.birth_date IS NULL AND u.age_years = :profile_age)' : '';
        $where[] = '(u.birth_date = :birth_date' . $ageFallback . ')';
        $params['birth_date'] = $birthDate;
        if ($profileAge !== null) {
            $params['profile_age'] = $profileAge;
        }
    } elseif ($profileAge !== null) {
        $where[] = 'u.birth_date IS NULL AND u.age_years = :profile_age';
        $params['profile_age'] = $profileAge;
    }

    $gender = (string)($user['gender'] ?? '');
    if ($gender !== '' && $gender !== 'prefer_not_to_say') {
        $where[] = '(u.gender = :gender OR u.gender IS NULL OR u.gender = "prefer_not_to_say")';
        $params['gender'] = $gender;
    }

    $limit = max(1, min(5, $limit));
    $stmt = db()->prepare(
        'SELECT COALESCE(pa.platform, u.platform) AS linked_platform, COUNT(DISTINCT u.id) AS matches_count
         FROM end_users u
         LEFT JOIN platform_accounts pa ON pa.end_user_id = u.id
         WHERE ' . implode(' AND ', $where) . '
         GROUP BY linked_platform
         ORDER BY FIELD(linked_platform, "telegram", "VK", "OK", "web", "MAX"), linked_platform
         LIMIT ' . $limit
    );
    $stmt->execute($params);

    $suggestions = [];
    foreach ($stmt->fetchAll() as $row) {
        $platform = normalize_platform((string)$row['linked_platform']);
        if ($platform === $currentPlatform || $platform === 'all') {
            continue;
        }
        $suggestions[] = [
            'platform' => $platform,
            'platform_label' => platform_label($platform),
            'matches' => (int)$row['matches_count'],
        ];
    }

    return $suggestions;
}

function link_existing_user_to_target(int $targetUserId, int $sourceUserId): ?array
{
    if ($targetUserId <= 0 || $sourceUserId <= 0 || $targetUserId === $sourceUserId) {
        return null;
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $targetStmt = $pdo->prepare('SELECT * FROM end_users WHERE id = :id AND merged_into_user_id IS NULL FOR UPDATE');
        $targetStmt->execute(['id' => $targetUserId]);
        $target = $targetStmt->fetch();

        $sourceStmt = $pdo->prepare('SELECT * FROM end_users WHERE id = :id AND merged_into_user_id IS NULL FOR UPDATE');
        $sourceStmt->execute(['id' => $sourceUserId]);
        $source = $sourceStmt->fetch();

        if (!$target || !$source) {
            $pdo->rollBack();
            return null;
        }

        $dedupeAutomation = $pdo->prepare(
            'DELETE source_log
             FROM automation_logs source_log
             INNER JOIN automation_logs target_log
               ON target_log.end_user_id = :target_id
              AND target_log.automation_type = source_log.automation_type
              AND target_log.context_key = source_log.context_key
              AND target_log.platform = source_log.platform
             WHERE source_log.end_user_id = :source_id'
        );
        $dedupeAutomation->execute(['target_id' => $targetUserId, 'source_id' => $sourceUserId]);

        foreach ([
            'platform_accounts' => 'end_user_id',
            'leads' => 'end_user_id',
            'user_test_sessions' => 'end_user_id',
            'recommendations' => 'end_user_id',
            'broadcast_logs' => 'end_user_id',
            'user_consents' => 'end_user_id',
            'client_stage_history' => 'end_user_id',
            'user_notifications' => 'end_user_id',
            'automation_logs' => 'end_user_id',
            'consultant_notifications' => 'end_user_id',
        ] as $table => $column) {
            $stmt = $pdo->prepare("UPDATE $table SET $column = :target_id WHERE $column = :source_id");
            $stmt->execute(['target_id' => $targetUserId, 'source_id' => $sourceUserId]);
        }

        $mergeFields = [
            'username',
            'first_name',
            'last_name',
            'gender',
            'birth_date',
            'age_years',
            'city',
            'phone',
            'email',
            'referral_code_used',
            'onboarding_completed_at',
            'referral_registered_at',
        ];
        $assignments = [];
        $params = ['target_id' => $targetUserId, 'source_id' => $sourceUserId];
        foreach ($mergeFields as $field) {
            if (($target[$field] ?? null) === null || trim((string)$target[$field]) === '') {
                if (($source[$field] ?? null) !== null && trim((string)$source[$field]) !== '') {
                    $assignments[] = "$field = :$field";
                    $params[$field] = $source[$field];
                }
            }
        }
        if (empty($target['reseller_id']) && empty($target['manager_id'])
            && (!empty($source['reseller_id']) || !empty($source['manager_id']))) {
            $assignments[] = 'reseller_id = :reseller_id';
            $assignments[] = 'manager_id = :manager_id';
            $params['reseller_id'] = $source['reseller_id'];
            $params['manager_id'] = $source['manager_id'];
        }
        if ($assignments) {
            $update = $pdo->prepare('UPDATE end_users SET ' . implode(', ', $assignments) . ' WHERE id = :target_id');
            $update->execute($params);
        }

        $moveResellerSource = $pdo->prepare('UPDATE resellers SET source_end_user_id = :target_id WHERE source_end_user_id = :source_id');
        $moveResellerSource->execute(['target_id' => $targetUserId, 'source_id' => $sourceUserId]);
        $moveManagerSource = $pdo->prepare('UPDATE managers SET source_end_user_id = :target_id WHERE source_end_user_id = :source_id');
        $moveManagerSource->execute(['target_id' => $targetUserId, 'source_id' => $sourceUserId]);

        $mark = $pdo->prepare('UPDATE end_users SET merged_into_user_id = :target_id, status = "unsubscribed" WHERE id = :source_id');
        $mark->execute(['target_id' => $targetUserId, 'source_id' => $sourceUserId]);

        $touch = $pdo->prepare('UPDATE end_users SET last_activity_at = NOW() WHERE id = :id');
        $touch->execute(['id' => $targetUserId]);

        $pdo->commit();

        $targetStmt = $pdo->prepare('SELECT * FROM end_users WHERE id = :id LIMIT 1');
        $targetStmt->execute(['id' => $targetUserId]);
        return $targetStmt->fetch() ?: null;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function create_or_get_user(array $data): array
{
    $data = enrich_vk_ok_platform_data($data);
    $platform = normalize_platform($data['platform'] ?? 'VK');
    $firstName = trim((string)($data['first_name'] ?? ''));
    $lastName = trim((string)($data['last_name'] ?? ''));
    if (
        in_array($platform, ['VK', 'OK', 'web'], true)
        && in_array($firstName, ['VK', 'Web'], true)
        && $lastName === 'User'
    ) {
        $data['first_name'] = null;
        $data['last_name'] = null;
        if (in_array(trim((string)($data['display_name'] ?? '')), ['VK User', 'Web User'], true)) {
            $data['display_name'] = null;
        }
    }
    $platformUserId = (string)($data['platform_user_id'] ?? '');
    if ($platformUserId === '') {
        json_response(['error' => 'platform_user_id is required'], 422);
    }

    if ($platform === 'telegram' && empty($data['platform_verified'])) {
        verify_platform_auth($platform, $platformUserId, isset($data['auth_token']) ? (string)$data['auth_token'] : null);
    }

    $staffPreview = staff_preview_user($platform, $platformUserId);
    if ($staffPreview) {
        return $staffPreview;
    }

    reject_staff_client_registration($platform, $platformUserId);

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
            if ($linkTargetUserId && (int)$existing['id'] !== $linkTargetUserId) {
                $linkedUser = link_existing_user_to_target($linkTargetUserId, (int)$existing['id']);
                if ($linkedUser) {
                    $linkedUser = fill_user_names_if_missing($linkedUser, $data);
                    return attach_referral_if_missing($linkedUser, $data['referral_code'] ?? null);
                }
            }
            ensure_platform_account((int)$existing['id'], $account['platform'], $account['platform_user_id'], $account['username'] ?? null, $account['first_name'] ?? null, $account['last_name'] ?? null, $account['display_name'] ?? null);
            $touch = db()->prepare('UPDATE end_users SET last_activity_at = NOW() WHERE id = :id');
            $touch->execute(['id' => $existing['id']]);
            $existing = fill_user_names_if_missing($existing, $data);
            return attach_referral_if_missing($existing, $data['referral_code'] ?? null);
        }
    }

    if ($linkTargetUserId) {
        $targetStmt = db()->prepare('SELECT * FROM end_users WHERE id = :id LIMIT 1');
        $targetStmt->execute(['id' => $linkTargetUserId]);
        $targetUser = $targetStmt->fetch();
        if ($targetUser) {
            ensure_platform_account((int)$targetUser['id'], $platform, $platformUserId, $data['username'] ?? null, $data['first_name'] ?? null, $data['last_name'] ?? null, $data['display_name'] ?? null, false);
            $touch = db()->prepare('UPDATE end_users SET last_activity_at = NOW() WHERE id = :id');
            $touch->execute(['id' => $targetUser['id']]);
            $targetUser = fill_user_names_if_missing($targetUser, $data);
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
            $legacyUser = fill_user_names_if_missing($legacyUser, $data);
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
    } else {
        $referralCode = null;
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
            ON DUPLICATE KEY UPDATE
              username = COALESCE(NULLIF(VALUES(username), ""), username),
              first_name = COALESCE(NULLIF(VALUES(first_name), ""), first_name),
              last_name = COALESCE(NULLIF(VALUES(last_name), ""), last_name),
              display_name = COALESCE(NULLIF(VALUES(display_name), ""), display_name)';
    if ($moveExisting) {
        $sql = 'INSERT INTO platform_accounts (end_user_id, platform, platform_user_id, username, first_name, last_name, display_name)
                VALUES (:end_user_id, :platform, :platform_user_id, :username, :first_name, :last_name, :display_name)
                ON DUPLICATE KEY UPDATE
                  end_user_id = VALUES(end_user_id),
                  username = COALESCE(NULLIF(VALUES(username), ""), username),
                  first_name = COALESCE(NULLIF(VALUES(first_name), ""), first_name),
                  last_name = COALESCE(NULLIF(VALUES(last_name), ""), last_name),
                  display_name = COALESCE(NULLIF(VALUES(display_name), ""), display_name)';
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


function client_owner_scope(array $user, string $alias = '', ?string $moduleKey = null): array
{
    $moduleKey ??= match ($alias) {
        'p' => 'products',
        't' => 'tests',
        'c', 'pc' => 'categories',
        default => '',
    };

    return owned_content_client_scope_condition($moduleKey, $user, $alias);
}

function client_material_owner_scope(array $user, string $alias = ''): array
{
    return owned_content_client_scope_condition('content', $user, $alias);
}
