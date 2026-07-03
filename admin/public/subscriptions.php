<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/permissions.php';

$admin = require_auth();
if ($admin['role'] !== 'superadmin') {
    http_response_code(403);
    exit('Access denied');
}

$title = 'Подписки лидеров';
$errors = [];
$success = $_GET['success'] ?? null;
$priceStmt = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = "leader_monthly_price" LIMIT 1');
$priceStmt->execute();
$fixedMonthlyPrice = trim((string)($priceStmt->fetchColumn() ?: ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $resellerId = (int)($_POST['reseller_id'] ?? 0);
    $status = (string)($_POST['status'] ?? 'pending');
    $startsAt = trim((string)($_POST['starts_at'] ?? ''));
    $endsAt = trim((string)($_POST['ends_at'] ?? ''));
    $note = trim((string)($_POST['payment_note'] ?? ''));

    if ($resellerId <= 0) {
        $errors[] = 'Выберите лидера.';
    }
    if (!in_array($status, ['pending', 'active', 'expired', 'suspended'], true)) {
        $errors[] = 'Некорректный статус.';
    }
    if ($status === 'active' && $endsAt === '') {
        $errors[] = 'Для активной подписки укажите дату окончания.';
    }
    if ($status === 'active' && $fixedMonthlyPrice === '') {
        $errors[] = 'Сначала задайте фиксированную стоимость места в разделе «Реквизиты документов».';
    }

    if (!$errors) {
        $stmt = db()->prepare(
            'INSERT INTO leader_subscriptions (
                reseller_id, status, starts_at, ends_at, monthly_price, payment_note, activated_by
             ) VALUES (
                :reseller_id, :status, :starts_at, :ends_at, :monthly_price, :payment_note, :activated_by
             )'
        );
        $stmt->execute([
            'reseller_id' => $resellerId,
            'status' => $status,
            'starts_at' => $startsAt !== '' ? str_replace('T', ' ', $startsAt) : null,
            'ends_at' => $endsAt !== '' ? str_replace('T', ' ', $endsAt) : null,
            'monthly_price' => $fixedMonthlyPrice !== '' ? $fixedMonthlyPrice : null,
            'payment_note' => $note !== '' ? $note : null,
            'activated_by' => $admin['id'],
        ]);
        log_activity('admin', (int)$admin['id'], 'create_leader_subscription', 'leader_subscriptions', (int)db()->lastInsertId());
        redirect('subscriptions.php?success=saved');
    }
}

$leaders = db()->query('SELECT id, name FROM resellers WHERE is_active = 1 ORDER BY name')->fetchAll();
$subscriptions = db()->query(
    'SELECT ls.*, r.name AS reseller_name, au.name AS activated_by_name
     FROM leader_subscriptions ls
     INNER JOIN resellers r ON r.id = ls.reseller_id
     LEFT JOIN admin_users au ON au.id = ls.activated_by
     ORDER BY ls.id DESC
     LIMIT 200'
)->fetchAll();

require __DIR__ . '/../app/views/layouts/header.php';
?>
<div class="toolbar"><h1>Подписки лидеров</h1></div>
<?php if ($success === 'saved'): ?><div class="notice success">Подписка сохранена.</div><?php endif; ?>
<?php foreach ($errors as $error): ?><div class="alert"><?= h($error) ?></div><?php endforeach; ?>

<section class="panel form-panel">
    <h2>Активировать или продлить место лидера</h2>
    <p class="cell-muted">Одно место соответствует одному кабинету лидера. Консультанты его команды отдельные места не расходуют.</p>
    <form method="post" class="crud-form">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <label class="field">
            <span>Лидер *</span>
            <select name="reseller_id" required>
                <option value="">Выберите</option>
                <?php foreach ($leaders as $leader): ?>
                    <option value="<?= (int)$leader['id'] ?>"><?= h((string)$leader['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field">
            <span>Статус *</span>
            <select name="status">
                <option value="active">Активна</option>
                <option value="pending">Ожидает оплаты</option>
                <option value="suspended">Приостановлена</option>
                <option value="expired">Истекла</option>
            </select>
        </label>
        <label class="field"><span>Начало</span><input type="datetime-local" name="starts_at" value="<?= h(date('Y-m-d\TH:i')) ?>"></label>
        <label class="field"><span>Окончание *</span><input type="datetime-local" name="ends_at"></label>
        <div class="field">
            <span>Фиксированная стоимость в месяц</span>
            <strong><?= $fixedMonthlyPrice !== '' ? h($fixedMonthlyPrice) . ' руб.' : 'Не задана' ?></strong>
        </div>
        <label class="field wide"><span>Примечание об оплате</span><textarea name="payment_note" rows="3"></textarea></label>
        <div class="form-actions"><button type="submit">Сохранить подписку</button></div>
    </form>
</section>

<section class="panel">
    <table class="data-table">
        <thead><tr><th>Лидер</th><th>Статус</th><th>Период</th><th>Стоимость</th><th>Примечание</th></tr></thead>
        <tbody>
        <?php foreach ($subscriptions as $item): ?>
            <tr>
                <td><?= h((string)$item['reseller_name']) ?></td>
                <td><span class="badge"><?= h((string)$item['status']) ?></span></td>
                <td><?= h((string)($item['starts_at'] ?: '—')) ?><br><?= h((string)($item['ends_at'] ?: '—')) ?></td>
                <td><?= $item['monthly_price'] !== null ? h((string)$item['monthly_price']) : '—' ?></td>
                <td><?= h((string)($item['payment_note'] ?: '—')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php require __DIR__ . '/../app/views/layouts/footer.php'; ?>
