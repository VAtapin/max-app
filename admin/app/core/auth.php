<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

$config = app_config();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name($config['app']['session_name']);
    session_start();
}

function current_admin(): ?array
{
    if (empty($_SESSION['admin_user_id'])) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM admin_users WHERE id = :id AND is_active = 1 LIMIT 1');
    $stmt->execute(['id' => $_SESSION['admin_user_id']]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function admin_totp_base32_encode(string $data): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = 0;
    $value = 0;
    $output = '';
    $length = strlen($data);

    for ($i = 0; $i < $length; $i++) {
        $value = ($value << 8) | ord($data[$i]);
        $bits += 8;
        while ($bits >= 5) {
            $output .= $alphabet[($value >> ($bits - 5)) & 31];
            $bits -= 5;
        }
    }

    if ($bits > 0) {
        $output .= $alphabet[($value << (5 - $bits)) & 31];
    }

    return $output;
}

function admin_totp_base32_decode(string $secret): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret) ?? '');
    $bits = 0;
    $value = 0;
    $output = '';
    $length = strlen($secret);

    for ($i = 0; $i < $length; $i++) {
        $index = strpos($alphabet, $secret[$i]);
        if ($index === false) {
            continue;
        }
        $value = ($value << 5) | $index;
        $bits += 5;
        if ($bits >= 8) {
            $bits -= 8;
            $output .= chr(($value >> $bits) & 255);
        }
    }

    return $output;
}

function admin_totp_generate_secret(): string
{
    return admin_totp_base32_encode(random_bytes(20));
}

function admin_totp_code(string $secret, ?int $time = null): string
{
    $key = admin_totp_base32_decode($secret);
    if ($key === '') {
        return '';
    }

    $counter = intdiv($time ?? time(), 30);
    $binaryCounter = pack('N2', intdiv($counter, 4294967296), $counter & 0xffffffff);
    $hash = hash_hmac('sha1', $binaryCounter, $key, true);
    $offset = ord($hash[19]) & 0x0f;
    $value = ((ord($hash[$offset]) & 0x7f) << 24)
        | ((ord($hash[$offset + 1]) & 0xff) << 16)
        | ((ord($hash[$offset + 2]) & 0xff) << 8)
        | (ord($hash[$offset + 3]) & 0xff);

    return str_pad((string)($value % 1000000), 6, '0', STR_PAD_LEFT);
}

function admin_totp_verify(string $secret, string $code, int $window = 1): bool
{
    $code = preg_replace('/\D+/', '', $code) ?? '';
    if (strlen($code) !== 6) {
        return false;
    }

    $now = time();
    for ($i = -$window; $i <= $window; $i++) {
        if (hash_equals(admin_totp_code($secret, $now + ($i * 30)), $code)) {
            return true;
        }
    }

    return false;
}

function admin_totp_uri(array $user, string $secret): string
{
    $issuer = app_config()['app']['name'] ?? 'SWPro';
    $account = (string)($user['email'] ?? $user['name'] ?? 'admin');

    return 'otpauth://totp/'
        . rawurlencode($issuer . ':' . $account)
        . '?secret=' . rawurlencode($secret)
        . '&issuer=' . rawurlencode($issuer)
        . '&digits=6&period=30';
}

function admin_two_factor_required(array $user): bool
{
    return (int)($user['two_factor_required'] ?? 0) === 1
        || (int)($user['two_factor_enabled'] ?? 0) === 1;
}

function admin_two_factor_ready(array $user): bool
{
    return admin_two_factor_required($user)
        && !empty($user['two_factor_secret'])
        && !empty($user['two_factor_confirmed_at']);
}

function complete_admin_login(array $user): void
{
    session_regenerate_id(true);
    unset($_SESSION['pending_2fa_admin_id'], $_SESSION['pending_2fa_setup_admin_id'], $_SESSION['pending_2fa_setup_secret']);
    $_SESSION['admin_user_id'] = (int)$user['id'];
    log_activity('admin', (int)$user['id'], 'login', 'admin_users', (int)$user['id']);
}

function admin_login_user_by_email(string $email): ?array
{
    $stmt = db()->prepare('SELECT * FROM admin_users WHERE email = :email AND is_active = 1 LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function start_admin_login(string $email, string $password): string
{
    $user = admin_login_user_by_email($email);
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return 'failed';
    }

    if (admin_two_factor_ready($user)) {
        session_regenerate_id(true);
        unset($_SESSION['admin_user_id'], $_SESSION['pending_2fa_setup_admin_id'], $_SESSION['pending_2fa_setup_secret']);
        $_SESSION['pending_2fa_admin_id'] = (int)$user['id'];
        return '2fa';
    }

    if (admin_two_factor_required($user)) {
        session_regenerate_id(true);
        unset($_SESSION['admin_user_id'], $_SESSION['pending_2fa_admin_id']);
        $_SESSION['pending_2fa_setup_admin_id'] = (int)$user['id'];
        return 'setup_2fa';
    }

    complete_admin_login($user);
    return 'ok';
}

function pending_2fa_admin(): ?array
{
    if (empty($_SESSION['pending_2fa_admin_id'])) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM admin_users WHERE id = :id AND is_active = 1 LIMIT 1');
    $stmt->execute(['id' => (int)$_SESSION['pending_2fa_admin_id']]);
    $user = $stmt->fetch();

    return $user && admin_two_factor_ready($user) ? $user : null;
}

function pending_2fa_setup_admin(): ?array
{
    if (empty($_SESSION['pending_2fa_setup_admin_id'])) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM admin_users WHERE id = :id AND is_active = 1 LIMIT 1');
    $stmt->execute(['id' => (int)$_SESSION['pending_2fa_setup_admin_id']]);
    $user = $stmt->fetch();

    return $user && admin_two_factor_required($user) && !admin_two_factor_ready($user) ? $user : null;
}

function pending_2fa_setup_secret(): string
{
    if (empty($_SESSION['pending_2fa_setup_secret'])) {
        $_SESSION['pending_2fa_setup_secret'] = admin_totp_generate_secret();
    }

    return (string)$_SESSION['pending_2fa_setup_secret'];
}

function verify_pending_admin_2fa(string $code): bool
{
    $user = pending_2fa_admin();
    if (!$user || !admin_totp_verify((string)$user['two_factor_secret'], $code)) {
        return false;
    }

    complete_admin_login($user);
    return true;
}

function confirm_pending_admin_2fa_setup(string $code): bool
{
    $user = pending_2fa_setup_admin();
    if (!$user) {
        return false;
    }

    $secret = pending_2fa_setup_secret();
    if (!admin_totp_verify($secret, $code)) {
        return false;
    }

    $stmt = db()->prepare(
        'UPDATE admin_users
         SET two_factor_enabled = 1, two_factor_secret = :secret, two_factor_confirmed_at = NOW()
         WHERE id = :id'
    );
    $stmt->execute([
        'secret' => $secret,
        'id' => (int)$user['id'],
    ]);
    $user['two_factor_enabled'] = 1;
    $user['two_factor_secret'] = $secret;
    $user['two_factor_confirmed_at'] = date('Y-m-d H:i:s');
    complete_admin_login($user);

    return true;
}

function require_auth(): array
{
    $user = current_admin();
    if (!$user) {
        redirect('login.php');
    }

    if ($user['role'] !== 'superadmin' && admin_subscription_restricted($user)) {
        $current = basename((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
        if (!in_array($current, ['subscription_expired.php', 'logout.php'], true)) {
            redirect('subscription_expired.php');
        }
    }

    return $user;
}

function admin_subscription_restricted(array $user): bool
{
    $resellerId = (int)($user['reseller_id'] ?? 0);
    if ($resellerId <= 0 && !empty($user['manager_id'])) {
        $manager = db()->prepare('SELECT reseller_id FROM managers WHERE id = :id LIMIT 1');
        $manager->execute(['id' => $user['manager_id']]);
        $resellerId = (int)$manager->fetchColumn();
    }
    if ($resellerId <= 0) {
        return false;
    }

    try {
        $any = db()->prepare('SELECT COUNT(*) FROM leader_subscriptions WHERE reseller_id = :reseller_id');
        $any->execute(['reseller_id' => $resellerId]);
        if ((int)$any->fetchColumn() === 0) {
            return false;
        }

        $active = db()->prepare(
            'SELECT COUNT(*)
             FROM leader_subscriptions
             WHERE reseller_id = :reseller_id
               AND status = "active"
               AND (starts_at IS NULL OR starts_at <= NOW())
               AND (ends_at IS NULL OR ends_at >= NOW())'
        );
        $active->execute(['reseller_id' => $resellerId]);
        return (int)$active->fetchColumn() === 0;
    } catch (Throwable) {
        return false;
    }
}

function login_admin(string $email, string $password): bool
{
    return start_admin_login($email, $password) === 'ok';
}

function logout_admin(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function log_activity(?string $actorType, ?int $actorId, string $action, ?string $entityType = null, ?int $entityId = null, array $details = []): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO activity_logs (actor_type, actor_id, action, entity_type, entity_id, details)
             VALUES (:actor_type, :actor_id, :action, :entity_type, :entity_id, :details)'
        );
        $stmt->execute([
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
        ]);
    } catch (Throwable) {
        // Logging must never block the business action in the admin panel.
    }
}
