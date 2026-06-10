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

    return $user;
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
