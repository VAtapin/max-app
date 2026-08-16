<?php

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/social_messaging.php';
require_once __DIR__ . '/lead_responses.php';

function admin_password_reset_token_ttl_seconds(): int
{
    return 3600;
}

function admin_password_reset_secret(): string
{
    $config = app_config();
    $db = (array)($config['db'] ?? []);

    return hash(
        'sha256',
        'admin-password-reset|' . ($db['database'] ?? '') . '|' . ($db['username'] ?? '') . '|' . ($db['password'] ?? '')
    );
}

function admin_password_reset_hash_token(string $token): string
{
    return hash_hmac('sha256', $token, admin_password_reset_secret());
}

function admin_password_reset_find_by_email(string $email): ?array
{
    $stmt = db()->prepare(
        'SELECT id, name, email, telegram_id, vk_id
         FROM admin_users
         WHERE email = :email AND is_active = 1
         LIMIT 1'
    );
    $stmt->execute(['email' => mb_strtolower(trim($email), 'UTF-8')]);
    $admin = $stmt->fetch();

    return $admin ?: null;
}

function admin_password_reset_send_url(): string
{
    $publicUrl = rtrim((string)(app_config()['app']['public_url'] ?? ''), '/');
    if ($publicUrl === '' && !empty($_SERVER['HTTP_HOST'])) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $publicUrl = $scheme . '://' . $_SERVER['HTTP_HOST'];
    }

    return $publicUrl !== '' ? $publicUrl . '/admin/public/admin_reset_password.php?token=%s' : '/admin/public/admin_reset_password.php?token=%s';
}

function admin_password_reset_mask_email(?string $email): string
{
    $email = trim((string)$email);
    if (!str_contains($email, '@')) {
        return $email;
    }

    [$local, $domain] = explode('@', $email, 2);
    $local = trim($local);
    if ($local === '') {
        return $email;
    }

    $first = $local[0];
    $last = $local[strlen($local) - 1];
    return $first . '***' . $last . '@' . $domain;
}

function admin_password_reset_send_email(string $to, string $subject, string $message): bool
{
    $host = trim((string)($_SERVER['SERVER_NAME'] ?? ''));
    if ($host === '') {
        $host = 'localhost';
    }
    $fromEmail = 'noreply@' . $host;
    $fromName = (string)(app_config()['app']['name'] ?? 'SWPro');
    $headers = [
        'From: ' . mb_encode_mimeheader($fromName, 'UTF-8') . ' <' . $fromEmail . '>',
        'Reply-To: ' . $fromEmail,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];

    $sent = @mail(
        $to,
        mb_encode_mimeheader($subject, 'UTF-8'),
        $message,
        implode("\r\n", $headers)
    );

    return (bool)$sent;
}

function admin_password_reset_request(string $email): ?array
{
    $admin = admin_password_reset_find_by_email($email);
    if (!$admin) {
        return null;
    }

    $token = bin2hex(random_bytes(32));
    $tokenHash = admin_password_reset_hash_token($token);
    $adminId = (int)$admin['id'];
    $expiresAt = gmdate('Y-m-d H:i:s', time() + admin_password_reset_token_ttl_seconds());

    db()->prepare('DELETE FROM admin_password_resets WHERE admin_user_id = :admin_user_id')
        ->execute(['admin_user_id' => $adminId]);

    db()->prepare(
        'INSERT INTO admin_password_resets
            (admin_user_id, token_hash, expires_at, requested_ip, user_agent)
         VALUES
            (:admin_user_id, :token_hash, :expires_at, :requested_ip, :user_agent)'
    )->execute([
        'admin_user_id' => $adminId,
        'token_hash' => $tokenHash,
        'expires_at' => $expiresAt,
        'requested_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);

    $url = sprintf(admin_password_reset_send_url(), urlencode($token));
    $recipient = trim((string)($admin['name'] ?? $admin['email']));
    $message = "Сброс пароля админки SWPro\n\n"
        . "Учетная запись: " . $recipient . "\n"
        . "Ссылка для установки нового пароля (действует 1 час):\n"
        . $url . "\n\n"
        . "Если вы не запрашивали это изменение, просто проигнорируйте сообщение.";

    $sentChannels = [];
    $telegramId = preg_replace('/\\D+/', '', (string)($admin['telegram_id'] ?? ''));
    if ($telegramId !== '') {
        $telegramResult = send_telegram_text($telegramId, $message);
        if (!empty($telegramResult['ok'])) {
            $sentChannels['telegram'] = true;
            log_activity('admin', (int)$admin['id'], 'request_password_reset', 'admin_users', (int)$admin['id'], [
                'channel' => 'telegram',
            ]);
        } else {
            log_activity('admin', (int)$admin['id'], 'request_password_reset_channel_failed', 'admin_users', (int)$admin['id'], [
                'channel' => 'telegram',
                'error' => (string)($telegramResult['error'] ?? 'Не удалось отправить в Telegram'),
            ]);
        }
    }

    $vkId = trim((string)($admin['vk_id'] ?? ''));
    if ($vkId !== '') {
        $integration = messaging_default_integration('VK');
        if ($integration) {
            $vkResult = send_vk_community_message($integration, $vkId, $message);
            if (!empty($vkResult['ok'])) {
                $sentChannels['vk'] = true;
                log_activity('admin', (int)$admin['id'], 'request_password_reset', 'admin_users', (int)$admin['id'], [
                    'channel' => 'vk',
                ]);
            } else {
                log_activity('admin', (int)$admin['id'], 'request_password_reset_channel_failed', 'admin_users', (int)$admin['id'], [
                    'channel' => 'vk',
                    'error' => (string)($vkResult['error'] ?? 'Не удалось отправить в VK'),
                ]);
            }
        } else {
            log_activity('admin', (int)$admin['id'], 'request_password_reset_channel_failed', 'admin_users', (int)$admin['id'], [
                'channel' => 'vk',
                'error' => 'Нет активной интеграции для VK-сообщения',
            ]);
        }
    }

    $adminEmail = trim((string)($admin['email'] ?? ''));
    if ($adminEmail !== '') {
        $emailSubject = 'Сброс пароля админки SWPro';
        $emailSent = admin_password_reset_send_email($adminEmail, $emailSubject, $message);
        if ($emailSent) {
            $sentChannels['email'] = admin_password_reset_mask_email($adminEmail);
            log_activity('admin', (int)$admin['id'], 'request_password_reset', 'admin_users', (int)$admin['id'], [
                'channel' => 'email',
            ]);
        } else {
            log_activity('admin', (int)$admin['id'], 'request_password_reset_channel_failed', 'admin_users', (int)$admin['id'], [
                'channel' => 'email',
                'error' => 'Не удалось отправить email',
            ]);
        }
    }

    if (!$sentChannels) {
        return ['status' => 'error', 'error' => 'Не удалось отправить ссылку для восстановления пароля. Проверьте доступные способы связи или обратитесь к администратору.'];
    }

    return [
        'status' => 'ok',
        'channels' => array_keys($sentChannels),
        'email' => $adminEmail,
        'masked_email' => $sentChannels['email'] ?? null,
    ];
}

function admin_password_reset_token_data(string $token): ?array
{
    $stmt = db()->prepare(
        'SELECT apr.id, apr.admin_user_id, au.email, au.name
         FROM admin_password_resets apr
         INNER JOIN admin_users au ON au.id = apr.admin_user_id
         WHERE apr.token_hash = :token_hash
           AND apr.expires_at > UTC_TIMESTAMP()
           AND apr.used_at IS NULL
         LIMIT 1'
    );
    $stmt->execute(['token_hash' => admin_password_reset_hash_token($token)]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function admin_password_reset_apply(string $token, string $password): bool
{
    $info = admin_password_reset_token_data($token);
    if (!$info) {
        return false;
    }

    if (strlen($password) < 8) {
        return false;
    }

    $adminId = (int)$info['admin_user_id'];
    db()->prepare(
        'UPDATE admin_users
         SET password_hash = :password_hash
         WHERE id = :admin_user_id'
    )->execute([
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'admin_user_id' => $adminId,
    ]);

    db()->prepare('UPDATE admin_password_resets SET used_at = NOW() WHERE id = :id')
        ->execute(['id' => (int)$info['id']]);

    db()->prepare('DELETE FROM admin_password_resets WHERE admin_user_id = :admin_user_id')
        ->execute(['admin_user_id' => $adminId]);

    log_activity('admin', $adminId, 'reset_password', 'admin_users', $adminId, [
        'email' => (string)$info['email'],
        'name' => (string)$info['name'],
    ]);

    return true;
}
