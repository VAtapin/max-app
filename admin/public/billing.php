<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/permissions.php';
require_once __DIR__ . '/../app/core/workspace_billing.php';

$admin = require_auth();
if (!can_manage('billing_self', $admin)) {
    http_response_code(403);
    exit('Access denied');
}
$title = 'Моя подписка';
$errors = [];
$success = $_GET['success'] ?? null;
$workspace = billing_workspace_for_admin($admin);
if (!$workspace) {
    http_response_code(404);
    exit('Для рабочего места не назначен тариф.');
}
billing_refresh_statuses();
$workspace = billing_workspace_for_admin($admin);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        if (($_POST['action'] ?? '') === 'create_invoice') {
            $invoice = billing_create_prepaid_invoice($workspace, (int)($_POST['months'] ?? 1));
            redirect('payment_checkout.php?invoice_id=' . (int)$invoice['id']);
        }
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

$plan = subscription_plan_row((int)$workspace['subscription_plan_id'], false);
$discounts = $workspace['billing_mode'] === 'prepaid' ? billing_period_discounts((int)$workspace['subscription_plan_id']) : [];
$invoices = billing_workspace_invoices((int)$workspace['id'], 50);
$branchInvoices = [];
if (($admin['role'] ?? '') === 'reseller'
    && billing_root_reseller_id((int)$admin['reseller_id']) === (int)$admin['reseller_id']) {
    $stmt = db()->prepare(
        'SELECT bi.*, CASE WHEN ws.subject_type = "reseller" THEN r.name ELSE m.name END AS subject_name,
                ws.unit_type
         FROM billing_invoices bi
         JOIN workspace_subscriptions ws ON ws.id = bi.workspace_subscription_id
         LEFT JOIN resellers r ON ws.subject_type = "reseller" AND r.id = ws.subject_id
         LEFT JOIN managers m ON ws.subject_type = "manager" AND m.id = ws.subject_id
         WHERE ws.root_reseller_id = :root_id AND ws.id <> :workspace_id
           AND bi.status IN ("pending","awaiting_confirmation","overdue")
         ORDER BY bi.due_at, bi.id'
    );
    $stmt->execute(['root_id' => (int)$admin['reseller_id'], 'workspace_id' => (int)$workspace['id']]);
    $branchInvoices = $stmt->fetchAll();
}
$unitLabels = ['base' => 'Базовая часть главного лидера', 'leader' => 'Рабочее место лидера', 'consultant' => 'Рабочее место консультанта'];
$statusLabels = ['active' => 'Оплачено', 'due' => 'Ожидается оплата', 'overdue' => 'Есть задолженность', 'suspended' => 'Приостановлено'];

require __DIR__ . '/../app/views/layouts/header.php';
?>
<div class="toolbar"><h1>Моя подписка</h1></div>
<?php foreach ($errors as $error): ?><div class="alert"><?= h($error) ?></div><?php endforeach; ?>
<?php if ($success === 'manual_sent'): ?><div class="notice success">Информация о переводе отправлена. После подтверждения срок обновится автоматически.</div><?php endif; ?>
<?php if ($success === 'paid'): ?><div class="notice success">Оплата подтверждена. Доступ продлён.</div><?php endif; ?>
<?php if ($success === 'processing'): ?><div class="notice">Платёж отправлен на проверку. Статус обновится автоматически после подтверждения платёжной системой.</div><?php endif; ?>

<section class="panel billing-overview <?= in_array($workspace['status'], ['overdue','suspended'], true) ? 'billing-overdue' : '' ?>">
    <div>
        <span class="eyebrow"><?= h((string)($plan['title'] ?? 'Подписка')) ?></span>
        <h2><?= h($unitLabels[$workspace['unit_type']] ?? 'Рабочее место') ?></h2>
        <p>Стоимость: <strong><?= h(subscription_money_text((float)$workspace['monthly_price'])) ?> в месяц</strong></p>
    </div>
    <div class="billing-status-card">
        <strong><?= h($statusLabels[$workspace['status']] ?? $workspace['status']) ?></strong>
        <?php if ($workspace['billing_mode'] === 'prepaid'): ?>
            <span>Оплачено до: <?= h((string)($workspace['paid_until'] ?: 'нет оплаты')) ?></span>
        <?php else: ?>
            <span>Расчёт по факту за календарный месяц</span>
            <small>Счёт формируется 1-го числа. Срок оплаты: <?= (int)($plan['payment_grace_days'] ?? 5) ?> дней.</small>
        <?php endif; ?>
    </div>
</section>

<?php if ($workspace['billing_mode'] === 'prepaid'): ?>
<section class="panel">
    <h2>Продлить рабочее место</h2>
    <p class="cell-muted">Новый период всегда добавляется после уже оплаченного срока — дни не теряются.</p>
    <div class="billing-period-grid">
        <?php foreach ($discounts as $discount): ?>
            <?php
            $months = (int)$discount['months'];
            $base = round((float)$workspace['monthly_price'] * $months, 2);
            $saving = round($base * (float)$discount['discount_percent'] / 100, 2);
            $total = round($base - $saving, 2);
            ?>
            <form method="post" class="billing-period-card <?= (float)$discount['discount_percent'] > 0 ? 'featured' : '' ?>">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="create_invoice">
                <input type="hidden" name="months" value="<?= $months ?>">
                <?php if (!empty($discount['badge_text'])): ?><span class="billing-badge"><?= h((string)$discount['badge_text']) ?></span><?php endif; ?>
                <h3><?= $months ?> <?= $months === 1 ? 'месяц' : ($months < 5 ? 'месяца' : 'месяцев') ?></h3>
                <?php if ($saving > 0): ?>
                    <span class="billing-old-price"><?= h(subscription_money_text($base)) ?></span>
                    <strong><?= h(subscription_money_text($total)) ?></strong>
                    <small>Скидка <?= h((string)$discount['discount_percent']) ?>% · экономия <?= h(subscription_money_text($saving)) ?></small>
                <?php else: ?>
                    <strong><?= h(subscription_money_text($total)) ?></strong>
                <?php endif; ?>
                <button type="submit">Выбрать и оплатить</button>
            </form>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="panel">
    <h2>Счета и платежи</h2>
    <?php if ($invoices): ?>
    <table class="data-table responsive-table">
        <thead><tr><th>Счёт</th><th>Период</th><th>Расчёт</th><th>Сумма</th><th>Статус</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($invoices as $invoice): ?>
            <tr>
                <td><?= h((string)$invoice['invoice_number']) ?></td>
                <td><?= h((string)$invoice['period_start']) ?> — <?= h((string)$invoice['period_end']) ?></td>
                <td><?= $invoice['invoice_type'] === 'actual' ? 'По факту' : 'Предоплата' ?><?php if ((float)$invoice['discount_amount'] > 0): ?><br><span class="cell-muted">Скидка <?= h((string)$invoice['discount_percent']) ?>%</span><?php endif; ?></td>
                <td><strong><?= h(subscription_money_text((float)$invoice['amount_due'])) ?></strong></td>
                <td><span class="badge <?= $invoice['status'] === 'paid' ? 'badge-active' : ($invoice['status'] === 'overdue' ? 'badge-danger' : 'badge-pending') ?>"><?= h((string)$invoice['status']) ?></span><?php if ($invoice['due_at']): ?><br><span class="cell-muted">до <?= h((string)$invoice['due_at']) ?></span><?php endif; ?></td>
                <td><?php if ($invoice['status'] !== 'paid' && $invoice['status'] !== 'canceled'): ?><a class="button" href="payment_checkout.php?invoice_id=<?= (int)$invoice['id'] ?>">Оплатить</a><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?><div class="empty-state">Счетов пока нет.</div><?php endif; ?>
</section>

<?php if ($branchInvoices): ?>
<section class="panel">
    <h2>Открытые счета вашей ветки</h2>
    <p class="cell-muted">Каждый участник отвечает за своё рабочее место, но как главный лидер вы также можете оплатить любой из этих счетов.</p>
    <table class="data-table responsive-table"><thead><tr><th>Участник</th><th>Период</th><th>Сумма</th><th>Статус</th><th></th></tr></thead><tbody>
    <?php foreach ($branchInvoices as $invoice): ?><tr>
        <td><strong><?= h((string)$invoice['subject_name']) ?></strong><br><span class="cell-muted"><?= $invoice['unit_type'] === 'leader' ? 'Лидер' : 'Консультант' ?></span></td>
        <td><?= h((string)$invoice['period_start']) ?> — <?= h((string)$invoice['period_end']) ?></td>
        <td><?= h(subscription_money_text((float)$invoice['amount_due'])) ?></td><td><?= h((string)$invoice['status']) ?></td>
        <td><a class="button" href="payment_checkout.php?invoice_id=<?= (int)$invoice['id'] ?>">Оплатить</a></td>
    </tr><?php endforeach; ?></tbody></table>
</section>
<?php endif; ?>
<?php require __DIR__ . '/../app/views/layouts/footer.php'; ?>
