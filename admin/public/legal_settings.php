<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/permissions.php';

$admin = require_auth();
if ($admin['role'] !== 'superadmin') {
    http_response_code(403);
    exit('Access denied');
}

$title = 'Оператор персональных данных';
$fields = [
    'legal_operator_name' => 'Наименование организации или ФИО оператора',
    'legal_operator_status' => 'Правовой статус',
    'legal_operator_inn' => 'ИНН',
    'legal_operator_address' => 'Адрес',
    'legal_operator_email' => 'Email для обращений по персональным данным',
    'legal_operator_phone' => 'Телефон',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $stmt = db()->prepare(
        'INSERT INTO settings (setting_key, setting_value)
         VALUES (:setting_key, :setting_value)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    foreach ($fields as $key => $label) {
        $stmt->execute([
            'setting_key' => $key,
            'setting_value' => trim((string)($_POST[$key] ?? '')),
        ]);
    }
    log_activity('admin', (int)$admin['id'], 'update_legal_settings', 'settings');
    redirect('legal_settings.php?success=saved');
}

$settings = [];
$stmt = db()->query('SELECT setting_key, setting_value FROM settings');
foreach ($stmt->fetchAll() as $row) {
    $settings[(string)$row['setting_key']] = (string)($row['setting_value'] ?? '');
}

require __DIR__ . '/../app/views/layouts/header.php';
?>
<div class="toolbar"><h1>Оператор персональных данных</h1></div>
<?php if (($_GET['success'] ?? '') === 'saved'): ?><div class="notice success">Реквизиты сохранены.</div><?php endif; ?>
<section class="panel form-panel">
    <p class="cell-muted">Это единые реквизиты ИП — владельца платформы SWPro.ru. Они подставляются во все политики и согласия независимо от реферального кода и выбранного лидера.</p>
    <form method="post" class="crud-form">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <?php foreach ($fields as $key => $label): ?>
            <label class="field">
                <span><?= h($label) ?></span>
                <input name="<?= h($key) ?>" value="<?= h((string)($settings[$key] ?? '')) ?>">
            </label>
        <?php endforeach; ?>
        <div class="form-actions"><button type="submit">Сохранить</button></div>
    </form>
</section>
<?php require __DIR__ . '/../app/views/layouts/footer.php'; ?>
