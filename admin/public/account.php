<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/permissions.php';
require_once __DIR__ . '/../app/core/qrcode.php';

$admin = require_auth();
$title = 'Мои данные';
$errors = [];
$success = (string)($_GET['success'] ?? '');

function account_email_exists(string $email, int $exceptId): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM admin_users WHERE email = :email AND id <> :id');
    $stmt->execute(['email' => $email, 'id' => $exceptId]);

    return (int)$stmt->fetchColumn() > 0;
}

function account_sync_owner_profile(array $admin, array $payload): void
{
    if (($admin['role'] ?? '') === 'manager' && !empty($admin['manager_id'])) {
        $stmt = db()->prepare(
            'UPDATE managers
             SET name = :name, email = :email, phone = :phone,
                 telegram_id = :telegram_id, vk_id = :vk_id, max_id = :max_id
             WHERE id = :id'
        );
        $stmt->execute([
            'name' => $payload['name'],
            'email' => $payload['email'],
            'phone' => $payload['phone'],
            'telegram_id' => $payload['telegram_id'],
            'vk_id' => $payload['vk_id'],
            'max_id' => $payload['max_id'],
            'id' => (int)$admin['manager_id'],
        ]);
        return;
    }

    if (($admin['role'] ?? '') === 'reseller' && !empty($admin['reseller_id'])) {
        $stmt = db()->prepare(
            'UPDATE resellers
             SET name = :name, email = :email, phone = :phone
             WHERE id = :id'
        );
        $stmt->execute([
            'name' => $payload['name'],
            'email' => $payload['email'],
            'phone' => $payload['phone'],
            'id' => (int)$admin['reseller_id'],
        ]);
    }
}

function account_reload_admin(int $id): array
{
    $stmt = db()->prepare('SELECT * FROM admin_users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    return $row ?: [];
}

function account_current_password_valid(array $admin, string $password): bool
{
    return $password !== '' && password_verify($password, (string)$admin['password_hash']);
}

function account_two_factor_save_error(Throwable $e): string
{
    $message = $e->getMessage();
    if (stripos($message, 'two_factor_') !== false || stripos($message, 'Unknown column') !== false) {
        return 'Не удалось сохранить 2FA: на сервере не применены миграции двухфакторной защиты. Выполните миграции базы данных.';
    }

    return 'Не удалось сохранить 2FA: ' . $message;
}

function account_two_factor_verify(string $secret, string $code, array &$errors): bool
{
    try {
        return admin_totp_verify($secret, $code);
    } catch (Throwable $e) {
        $errors[] = 'Не удалось проверить код 2FA: ' . $e->getMessage();
        return false;
    }
}

function account_two_factor_qr(string $uri, array &$errors): string
{
    try {
        return qr_code_svg_data_uri($uri);
    } catch (Throwable $e) {
        $errors[] = 'Не удалось создать QR-код 2FA: ' . $e->getMessage();
        return '';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'profile') {
        $name = trim((string)($_POST['name'] ?? ''));
        $emailRaw = trim((string)($_POST['email'] ?? ''));
        $email = function_exists('mb_strtolower') ? mb_strtolower($emailRaw, 'UTF-8') : strtolower($emailRaw);
        $payload = [
            'name' => $name,
            'email' => $email,
            'phone' => trim((string)($_POST['phone'] ?? '')) ?: null,
            'telegram_id' => trim((string)($_POST['telegram_id'] ?? '')) ?: null,
            'vk_id' => trim((string)($_POST['vk_id'] ?? '')) ?: null,
            'max_id' => trim((string)($_POST['max_id'] ?? '')) ?: null,
        ];

        if ($name === '') {
            $errors[] = 'Укажите имя.';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Укажите корректный email.';
        } elseif (account_email_exists($email, (int)$admin['id'])) {
            $errors[] = 'Такой email уже используется.';
        }

        if (!$errors) {
            try {
                $stmt = db()->prepare(
                    'UPDATE admin_users
                     SET name = :name, email = :email, phone = :phone,
                         telegram_id = :telegram_id, vk_id = :vk_id, max_id = :max_id
                     WHERE id = :id'
                );
                $stmt->execute($payload + ['id' => (int)$admin['id']]);
                account_sync_owner_profile($admin, $payload);
                log_activity('admin', (int)$admin['id'], 'update_own_account', 'admin_users', (int)$admin['id']);
                redirect('account.php?success=profile');
            } catch (Throwable $e) {
                $errors[] = 'Не удалось сохранить данные: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'password') {
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');
        $passwordLength = function_exists('mb_strlen') ? mb_strlen($newPassword, 'UTF-8') : strlen($newPassword);

        if (!account_current_password_valid($admin, $currentPassword)) {
            $errors[] = 'Текущий пароль указан неверно.';
        }
        if ($passwordLength < 8) {
            $errors[] = 'Новый пароль должен быть не короче 8 символов.';
        }
        if ($newPassword !== $confirmPassword) {
            $errors[] = 'Пароли не совпадают.';
        }

        if (!$errors) {
            $stmt = db()->prepare('UPDATE admin_users SET password_hash = :password_hash WHERE id = :id');
            $stmt->execute([
                'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                'id' => (int)$admin['id'],
            ]);
            log_activity('admin', (int)$admin['id'], 'change_own_password', 'admin_users', (int)$admin['id']);
            redirect('account.php?success=password');
        }
    } elseif ($action === 'start_2fa') {
        $_SESSION['account_2fa_setup_secret'] = admin_totp_generate_secret();
        redirect('account.php?two_factor=setup');
    } elseif ($action === 'confirm_2fa') {
        $secret = (string)($_SESSION['account_2fa_setup_secret'] ?? '');
        $code = (string)($_POST['code'] ?? '');

        if ($secret === '') {
            $errors[] = 'Сначала начните настройку 2FA.';
        }
        if ($secret !== '' && !account_two_factor_verify($secret, $code, $errors)) {
            $errors[] = 'Неверный код 2FA.';
        }

        if (!$errors) {
            try {
                $stmt = db()->prepare(
                    'UPDATE admin_users
                     SET two_factor_enabled = 1, two_factor_secret = :secret, two_factor_confirmed_at = NOW()
                     WHERE id = :id'
                );
                $stmt->execute([
                    'secret' => $secret,
                    'id' => (int)$admin['id'],
                ]);
                unset($_SESSION['account_2fa_setup_secret']);
                log_activity('admin', (int)$admin['id'], 'enable_own_2fa', 'admin_users', (int)$admin['id']);
                redirect('account.php?success=2fa_enabled');
            } catch (Throwable $e) {
                $errors[] = account_two_factor_save_error($e);
            }
        }
    } elseif ($action === 'disable_2fa') {
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $code = (string)($_POST['code'] ?? '');

        if ((int)($admin['two_factor_required'] ?? 0) === 1) {
            $errors[] = '2FA обязательна для этой учётной записи. Отключить её может только супер-админ.';
        }
        if (!account_current_password_valid($admin, $currentPassword)) {
            $errors[] = 'Текущий пароль указан неверно.';
        }
        if (admin_two_factor_ready($admin) && !account_two_factor_verify((string)$admin['two_factor_secret'], $code, $errors)) {
            $errors[] = 'Неверный код 2FA.';
        }

        if (!$errors) {
            try {
                $stmt = db()->prepare(
                    'UPDATE admin_users
                     SET two_factor_enabled = 0, two_factor_secret = NULL, two_factor_confirmed_at = NULL
                     WHERE id = :id'
                );
                $stmt->execute(['id' => (int)$admin['id']]);
                unset($_SESSION['account_2fa_setup_secret']);
                log_activity('admin', (int)$admin['id'], 'disable_own_2fa', 'admin_users', (int)$admin['id']);
                redirect('account.php?success=2fa_disabled');
            } catch (Throwable $e) {
                $errors[] = account_two_factor_save_error($e);
            }
        }
    }
}

$admin = account_reload_admin((int)$admin['id']) ?: $admin;
$setupMode = (string)($_GET['two_factor'] ?? '') === 'setup';
if ($setupMode && empty($_SESSION['account_2fa_setup_secret'])) {
    $_SESSION['account_2fa_setup_secret'] = admin_totp_generate_secret();
}
$setupSecret = $setupMode ? (string)($_SESSION['account_2fa_setup_secret'] ?? '') : '';
$setupUri = $setupSecret !== '' ? admin_totp_uri($admin, $setupSecret) : '';
$setupQr = $setupUri !== '' ? account_two_factor_qr($setupUri, $errors) : '';
$roleLabels = [
    'superadmin' => 'Супер-админ',
    'reseller' => 'Лидер',
    'manager' => 'Консультант',
];
$isRequired2fa = (int)($admin['two_factor_required'] ?? 0) === 1;
$isReady2fa = admin_two_factor_ready($admin);

require __DIR__ . '/../app/views/layouts/header.php';
?>
<div class="toolbar">
    <h1><?= h($title) ?></h1>
</div>

<?php if ($success === 'profile'): ?>
    <div class="notice success">Данные сохранены.</div>
<?php elseif ($success === 'password'): ?>
    <div class="notice success">Пароль изменён.</div>
<?php elseif ($success === '2fa_enabled'): ?>
    <div class="notice success">Двухфакторная защита включена.</div>
<?php elseif ($success === '2fa_disabled'): ?>
    <div class="notice success">Двухфакторная защита отключена.</div>
<?php endif; ?>
<?php foreach ($errors as $error): ?>
    <div class="alert"><?= h($error) ?></div>
<?php endforeach; ?>

<div class="account-grid">
    <section class="panel form-panel">
        <h2>Профиль</h2>
        <p class="cell-muted">Роль: <strong><?= h($roleLabels[(string)$admin['role']] ?? (string)$admin['role']) ?></strong></p>
        <form method="post" class="crud-form account-form">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="profile">
            <div class="admin-account-form-grid">
                <label class="field">
                    <span>Имя *</span>
                    <input type="text" name="name" value="<?= h((string)$admin['name']) ?>" autocomplete="name">
                </label>
                <label class="field">
                    <span>Email для входа *</span>
                    <input type="email" name="email" value="<?= h((string)$admin['email']) ?>" autocomplete="email">
                </label>
                <label class="field">
                    <span>Телефон</span>
                    <input type="text" name="phone" value="<?= h((string)($admin['phone'] ?? '')) ?>" autocomplete="tel">
                </label>
                <label class="field">
                    <span>Telegram ID</span>
                    <input type="text" name="telegram_id" value="<?= h((string)($admin['telegram_id'] ?? '')) ?>">
                </label>
                <label class="field">
                    <span>VK ID</span>
                    <input type="text" name="vk_id" value="<?= h((string)($admin['vk_id'] ?? '')) ?>">
                </label>
                <label class="field">
                    <span>MAX ID</span>
                    <input type="text" name="max_id" value="<?= h((string)($admin['max_id'] ?? '')) ?>">
                </label>
            </div>
            <div class="form-actions">
                <button type="submit">Сохранить данные</button>
            </div>
        </form>
    </section>

    <section class="panel form-panel">
        <h2>Пароль</h2>
        <form method="post" class="crud-form account-form">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="password">
            <label class="field">
                <span>Текущий пароль *</span>
                <input type="password" name="current_password" autocomplete="current-password">
            </label>
            <label class="field">
                <span>Новый пароль *</span>
                <input type="password" name="new_password" autocomplete="new-password">
            </label>
            <label class="field">
                <span>Повторите новый пароль *</span>
                <input type="password" name="confirm_password" autocomplete="new-password">
            </label>
            <div class="form-actions">
                <button type="submit">Изменить пароль</button>
            </div>
        </form>
    </section>
</div>

<section class="panel form-panel">
    <div class="two-factor-head">
        <div>
            <h2>Двухфакторная защита</h2>
            <p class="cell-muted">
                <?= $isRequired2fa
                    ? 'Для этой учётной записи 2FA обязательна.'
                    : 'Не обязательно, но лучше включить: это защищает админку, даже если пароль случайно узнают.' ?>
            </p>
        </div>
        <span class="badge <?= $isReady2fa ? 'badge-sent' : ($isRequired2fa ? 'badge-new' : '') ?>">
            <?= $isReady2fa ? 'Включена' : ($isRequired2fa ? 'Нужно настроить' : 'Выключена') ?>
        </span>
    </div>

    <?php if ($setupMode): ?>
        <div class="two-factor-connect">
            <h2>Подключить приложение-аутентификатор</h2>
            <div class="notice warning two-factor-warning">
                <strong>Важно: не сканируйте этот QR-код камерой телефона, Google Lens, Яндексом или поиском.</strong>
                Такие сервисы могут показать код только один раз и не сохранят его для следующего входа.
            </div>
            <div class="two-factor-instructions">
                <strong>Сначала установите и откройте приложение-аутентификатор:</strong>
                <ol>
                    <li>Установите 2FAS, Aegis, Microsoft Authenticator, Яндекс ID или другое приложение с поддержкой TOTP.</li>
                    <li>В самом приложении нажмите «Добавить аккаунт» и выберите сканирование QR-кода.</li>
                    <li>Только затем отсканируйте этот QR-код через приложение и введите показанный им код ниже.</li>
                </ol>
            </div>
            <?php if ($setupQr !== ''): ?>
                <img class="two-factor-qr-image" src="<?= h($setupQr) ?>" alt="QR-код для настройки 2FA">
            <?php endif; ?>
            <details class="two-factor-manual">
                <summary>Не получается отсканировать QR-код</summary>
                <p>В приложении-аутентификаторе выберите ввод ключа вручную и укажите этот секретный ключ:</p>
                <code><?= h($setupSecret) ?></code>
            </details>
            <form method="post" class="crud-form two-factor-confirm-form">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="confirm_2fa">
                <label class="field">
                    <span>Код из приложения *</span>
                    <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,8}">
                </label>
                <div class="form-actions">
                    <button type="submit">Подтвердить и подключить</button>
                    <a class="button secondary-button" href="account.php">Отмена</a>
                </div>
            </form>
        </div>
    <?php elseif (!$isReady2fa): ?>
        <p class="two-factor-recommendation">Рекомендуется подключить приложение-аутентификатор на телефоне. Это займет меньше минуты.</p>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="start_2fa">
            <button type="submit"><?= $isRequired2fa ? 'Настроить обязательную 2FA' : 'Включить 2FA' ?></button>
        </form>
    <?php else: ?>
        <?php if ($isRequired2fa): ?>
            <div class="notice">2FA обязательна для этой учётной записи. Отключить её может только супер-админ в разделе пользователей админки.</div>
        <?php else: ?>
            <form method="post" class="crud-form two-factor-disable-form">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="disable_2fa">
                <label class="field">
                    <span>Текущий пароль *</span>
                    <input type="password" name="current_password" autocomplete="current-password">
                </label>
                <label class="field">
                    <span>Код 2FA *</span>
                    <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,8}">
                </label>
                <div class="form-actions">
                    <button type="submit" class="danger-button">Отключить 2FA</button>
                </div>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../app/views/layouts/footer.php'; ?>
