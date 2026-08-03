<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/qrcode.php';

$error = null;
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
$setupQr = $setupUri !== '' ? qr_code_svg_data_uri($setupUri) : '';
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
            <p class="cell-muted">Откройте Яндекс ID, 2FAS, Aegis, Microsoft Authenticator или другое приложение с поддержкой TOTP и отсканируйте QR-код.</p>
            <img class="two-factor-qr-image login-two-factor-qr-image" src="<?= h($setupQr) ?>" alt="QR-код для настройки 2FA">
            <details class="two-factor-manual">
                <summary>Не получается отсканировать QR-код</summary>
                <p>Введите этот секретный ключ вручную:</p>
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
        <?php endif; ?>
    </form>
</body>
</html>
