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
    $stmt = db()->prepare('SELECT * FROM admin_users WHERE email = :email AND is_active = 1 LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['admin_user_id'] = (int)$user['id'];
    log_activity('admin', (int)$user['id'], 'login', 'admin_users', (int)$user['id']);

    return true;
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
