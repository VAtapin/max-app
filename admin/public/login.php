<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/qrcode.php';

$error = null;
$resetStatus = (string)($_GET['status'] ?? '');
$step = 'login';
$pending2fa = pending_2fa_admin();
$pendingSetup = pending_2fa_setup_admin();
if ($pending2fa) {
    $step = '2fa';
} elseif ($pendingSetup) {
    $step = 'setup_2fa';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? 'login');

    if ($action === 'verify_2fa') {
        if (verify_pending_admin_2fa((string)($_POST['code'] ?? ''))) {
            redirect('index.php');
        }
        $error = 'Неверный код подтверждения.';
        $step = '2fa';
    } elseif ($action === 'confirm_2fa_setup') {
        if (confirm_pending_admin_2fa_setup((string)($_POST['code'] ?? ''))) {
            redirect('index.php');
        }
        $error = 'Неверный код. Проверьте секрет в приложении-аутентификаторе.';
        $step = 'setup_2fa';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $loginStatus = start_admin_login($email, $password);
        if ($loginStatus === 'ok') {
            redirect('index.php');
        }
        if ($loginStatus === '2fa') {
            redirect('login.php?step=2fa');
        }
        if ($loginStatus === 'setup_2fa') {
            redirect('login.php?step=setup_2fa');
        }
        $error = app_text('auto.k_370c97feae9b');
    }
}

$pending2fa = pending_2fa_admin();
$pendingSetup = pending_2fa_setup_admin();
if ($pending2fa) {
    $step = '2fa';
} elseif ($pendingSetup) {
    $step = 'setup_2fa';
} elseif (!in_array($step, ['login', '2fa', 'setup_2fa'], true)) {
    $step = 'login';
}
$setupSecret = $step === 'setup_2fa' && $pendingSetup ? pending_2fa_setup_secret() : '';
$setupUri = $setupSecret !== '' && $pendingSetup ? admin_totp_uri($pendingSetup, $setupSecret) : '';
$setupQr = '';
if ($setupUri !== '') {
    try {
        $setupQr = qr_code_svg_data_uri($setupUri);
    } catch (Throwable $e) {
        $error = 'Не удалось создать QR-код 2FA: ' . $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h(app_text('auto.k_07205a06c301')) ?></title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="theme-color" content="#0b4f86">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= (int)filemtime(__DIR__ . '/assets/css/style.css') ?>">
</head>
<body class="login-page">
    <form method="post" class="login-card">
        <?php if ($step !== 'setup_2fa'): ?>
            <h1><?= $step === 'login' ? h(app_text('auto.k_53407b712a93')) : 'Подтверждение входа' ?></h1>
        <?php endif; ?>
        <?php if ($resetStatus === 'reset'): ?>
            <div class="notice success">Пароль успешно обновлён. Войдите с новым паролем.</div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert"><?= h($error) ?></div>
        <?php endif; ?>
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <?php if ($step === '2fa'): ?>
            <input type="hidden" name="action" value="verify_2fa">
            <p class="cell-muted">Введите 6-значный код из приложения-аутентификатора.</p>
            <label>Код 2FA</label>
            <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,8}" required autofocus>
            <button type="submit">Войти</button>
        <?php elseif ($step === 'setup_2fa'): ?>
            <input type="hidden" name="action" value="confirm_2fa_setup">
            <h1 class="two-factor-connect-title">Подключить приложение-аутентификатор</h1>
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
                <img class="two-factor-qr-image login-two-factor-qr-image" src="<?= h($setupQr) ?>" alt="QR-код для настройки 2FA">
            <?php endif; ?>
            <details class="two-factor-manual">
                <summary>Не получается отсканировать QR-код</summary>
                <p>В приложении-аутентификаторе выберите ввод ключа вручную и укажите этот секретный ключ:</p>
                <code><?= h($setupSecret) ?></code>
            </details>
            <label>Код 2FA</label>
            <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,8}" required autofocus>
            <button type="submit">Подтвердить и войти</button>
        <?php else: ?>
            <input type="hidden" name="action" value="login">
            <label>Email</label>
            <input type="email" name="email" value="<?= h((string)($_POST['email'] ?? '')) ?>" required autofocus>
            <label><?= h(app_text('auto.k_14f7c63cc128')) ?></label>
            <input type="password" name="password" required>
            <button type="submit"><?= h(app_text('auto.k_939e95a11ddd')) ?></button>
            <div class="forgot-password-link"><a href="forgot_password.php">Забыли пароль?</a></div>
        <?php endif; ?>
    </form>
</body>
</html>
