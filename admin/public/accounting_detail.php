<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/workspace_billing.php';

$admin = require_auth();
if (($admin['role'] ?? '') !== 'superadmin') { http_response_code(403); exit('Access denied'); }
$rootId = (int)($_GET['root_id'] ?? $_POST['root_id'] ?? 0);
if ($rootId <= 0 || billing_root_reseller_id($rootId) !== $rootId) { http_response_code(404); exit('Главная ветка не найдена.'); }
$title = 'Детализация подписки';
$errors = [];
$success = $_GET['success'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $action = (string)($_POST['action'] ?? '');
        $workspaceId = (int)($_POST['workspace_id'] ?? 0);
        $scope = db()->prepare('SELECT * FROM workspace_subscriptions WHERE id = :id AND root_reseller_id = :root_id LIMIT 1');
        $scope->execute(['id' => $workspaceId, 'root_id' => $rootId]);
        $workspace = $scope->fetch();
        if (!$workspace) throw new RuntimeException('Рабочее место не найдено в этой ветке.');
        if ($action === 'extend') {
            $days = max(1, min(3660, (int)($_POST['days'] ?? 0)));
            $note = trim((string)($_POST['note'] ?? ''));
            if ($note === '') throw new RuntimeException('Укажите причину корректировки.');
            $base = !empty($workspace['paid_until']) && $workspace['paid_until'] >= date('Y-m-d') ? new DateTimeImmutable($workspace['paid_until']) : new DateTimeImmutable('today');
            $until = $base->modify('+' . $days . ' days')->format('Y-m-d');
            $stmt = db()->prepare('UPDATE workspace_subscriptions SET paid_until = :until, status = "active" WHERE id = :id');
            $stmt->execute(['until' => $until, 'id' => $workspaceId]);
            $stmt = db()->prepare('INSERT INTO billing_adjustments (workspace_subscription_id, adjustment_type, days, note, created_by) VALUES (:id, "extend", :days, :note, :admin)');
            $stmt->execute(['id' => $workspaceId, 'days' => $days, 'note' => $note, 'admin' => $admin['id']]);
            redirect('accounting_detail.php?root_id=' . $rootId . '&success=adjusted');
        }
        if ($action === 'writeoff') {
            $note = trim((string)($_POST['note'] ?? ''));
            if ($note === '') throw new RuntimeException('Укажите причину списания.');
            $stmt = db()->prepare('UPDATE billing_invoices SET status = "canceled" WHERE workspace_subscription_id = :id AND status IN ("pending","awaiting_confirmation","overdue")');
            $stmt->execute(['id' => $workspaceId]);
            $stmt = db()->prepare('INSERT INTO billing_adjustments (workspace_subscription_id, adjustment_type, note, created_by) VALUES (:id, "writeoff", :note, :admin)');
            $stmt->execute(['id' => $workspaceId, 'note' => $note, 'admin' => $admin['id']]);
            billing_refresh_statuses();
            redirect('accounting_detail.php?root_id=' . $rootId . '&success=adjusted');
        }
    } catch (Throwable $e) { $errors[] = $e->getMessage(); }
}

billing_sync_all_workspaces();
billing_refresh_statuses();
$rootStmt = db()->prepare('SELECT name FROM resellers WHERE id = :id');
$rootStmt->execute(['id' => $rootId]);
$rootName = (string)$rootStmt->fetchColumn();
$stmt = db()->prepare(
    'SELECT ws.*, sp.title AS plan_title,
        CASE WHEN ws.subject_type = "reseller" THEN r.name ELSE m.name END AS subject_name
     FROM workspace_subscriptions ws
     JOIN subscription_plans sp ON sp.id = ws.subscription_plan_id
     LEFT JOIN resellers r ON ws.subject_type = "reseller" AND r.id = ws.subject_id
     LEFT JOIN managers m ON ws.subject_type = "manager" AND m.id = ws.subject_id
     WHERE ws.root_reseller_id = :root_id ORDER BY FIELD(ws.unit_type,"base","leader","consultant"), subject_name'
);
$stmt->execute(['root_id' => $rootId]);
$workspaces = $stmt->fetchAll();
$pendingStmt = db()->prepare(
    'SELECT pt.*, bi.invoice_number, bi.amount_due, pm.title AS method_title,
        CASE WHEN ws.subject_type = "reseller" THEN r.name ELSE m.name END AS subject_name
     FROM payment_transactions pt
     JOIN billing_invoices bi ON bi.id = pt.billing_invoice_id
     JOIN workspace_subscriptions ws ON ws.id = bi.workspace_subscription_id
     JOIN payment_methods pm ON pm.id = pt.payment_method_id AND pm.method_type = "manual"
     LEFT JOIN resellers r ON ws.subject_type = "reseller" AND r.id = ws.subject_id
     LEFT JOIN managers m ON ws.subject_type = "manager" AND m.id = ws.subject_id
     WHERE ws.root_reseller_id = :root_id AND pt.status = "pending" ORDER BY pt.id DESC'
);
$pendingStmt->execute(['root_id' => $rootId]);
$pendingPayments = $pendingStmt->fetchAll();
require __DIR__ . '/../app/views/layouts/header.php';
?>
<div class="toolbar"><h1>Ветка: <?= h($rootName) ?></h1><a class="button secondary-button" href="accounting.php">К бухгалтерии</a></div>
<?php if ($success === 'adjusted'): ?><div class="notice success">Корректировка сохранена.</div><?php endif; ?>
<?php foreach ($errors as $error): ?><div class="alert"><?= h($error) ?></div><?php endforeach; ?>

<?php if ($pendingPayments): ?>
<section class="panel"><h2>Переводы, ожидающие подтверждения</h2>
<table class="data-table"><thead><tr><th>Плательщик</th><th>Счёт</th><th>Сумма</th><th>Комментарий</th><th>Квитанция</th><th></th></tr></thead><tbody>
<?php foreach ($pendingPayments as $payment): ?><tr>
<td><?= h((string)$payment['subject_name']) ?></td><td><?= h((string)$payment['invoice_number']) ?></td><td><?= h(subscription_money_text((float)$payment['amount_due'])) ?></td>
<td><?= h((string)$payment['payer_comment']) ?></td><td><?php if ($payment['receipt_path']): ?><a href="<?= h((string)$payment['receipt_path']) ?>" target="_blank" rel="noopener">Открыть</a><?php else: ?>—<?php endif; ?></td>
<td><form method="post" action="accounting.php"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="confirm_manual_payment"><input type="hidden" name="transaction_id" value="<?= (int)$payment['id'] ?>"><button type="submit">Подтвердить</button></form></td>
</tr><?php endforeach; ?></tbody></table></section>
<?php endif; ?>

<section class="panel"><h2>Рабочие места</h2>
<table class="data-table responsive-table"><thead><tr><th>Человек</th><th>Тип</th><th>Режим</th><th>Цена</th><th>Оплачено до</th><th>Статус</th><th>Корректировка</th></tr></thead><tbody>
<?php foreach ($workspaces as $workspace): ?><tr>
<td><strong><?= h((string)$workspace['subject_name']) ?></strong><br><span class="cell-muted"><?= h((string)$workspace['plan_title']) ?></span></td>
<td><?= h(['base'=>'База','leader'=>'Лидер','consultant'=>'Консультант'][$workspace['unit_type']] ?? $workspace['unit_type']) ?></td>
<td><?= $workspace['billing_mode'] === 'actual' ? 'По факту' : 'Предоплата' ?></td><td><?= h(subscription_money_text((float)$workspace['monthly_price'])) ?></td>
<td><?= h(app_date_ru($workspace['paid_until'] ?? null)) ?></td><td><?= h((string)$workspace['status']) ?></td>
<td><details><summary>Изменить</summary>
<form method="post" class="compact-form"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="root_id" value="<?= $rootId ?>"><input type="hidden" name="workspace_id" value="<?= (int)$workspace['id'] ?>"><input type="hidden" name="action" value="extend"><input type="number" min="1" name="days" value="30"><input name="note" placeholder="Причина" required><button>Продлить</button></form>
<form method="post" class="compact-form" onsubmit="return confirm('Списать все открытые счета этого рабочего места?')"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="root_id" value="<?= $rootId ?>"><input type="hidden" name="workspace_id" value="<?= (int)$workspace['id'] ?>"><input type="hidden" name="action" value="writeoff"><input name="note" placeholder="Причина" required><button class="danger-button">Списать долг</button></form>
</details></td></tr><?php endforeach; ?></tbody></table></section>
<?php require __DIR__ . '/../app/views/layouts/footer.php'; ?>
