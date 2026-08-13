<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/permissions.php';
require_once __DIR__ . '/../app/core/subscription_plans.php';
require_once __DIR__ . '/../app/core/workspace_billing.php';
require_once __DIR__ . '/../app/core/table_ui.php';

$admin = require_auth();
if (($admin['role'] ?? '') !== 'superadmin') {
    http_response_code(403);
    exit('Access denied');
}

$title = 'Бухгалтерия';
$errors = [];
$success = $_GET['success'] ?? null;

function accounting_status_badge(?string $status, ?string $paidAt): string
{
    if ($paidAt) {
        return '<span class="badge badge-sent">Оплачено</span>';
    }

    if ($status === null || $status === '') {
        return '<span class="badge badge-pending">Нет начисления</span>';
    }

    return '<span class="badge badge-pending">К оплате</span>';
}

function accounting_period(): array
{
    $start = new DateTimeImmutable('first day of this month 00:00:00');
    $end = new DateTimeImmutable('last day of this month 23:59:59');

    return [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'), $start->format('Ym')];
}

function accounting_plan_from_row(array $row): ?array
{
    if (empty($row['subscription_plan_id']) || trim((string)($row['plan_title'] ?? '')) === '') {
        return null;
    }

    return [
        'id' => (int)$row['subscription_plan_id'],
        'title' => (string)$row['plan_title'],
        'billing_mode' => (string)($row['plan_billing_mode'] ?? 'prepaid'),
        'billing_basis' => (string)($row['plan_billing_basis'] ?? 'branch'),
        'direct_leader_limit' => $row['plan_direct_leader_limit'],
        'branch_leader_limit' => $row['plan_branch_leader_limit'],
        'direct_consultant_limit' => $row['plan_direct_consultant_limit'],
        'branch_consultant_limit' => $row['plan_branch_consultant_limit'],
        'per_child_consultant_limit' => $row['plan_per_child_consultant_limit'],
        'price_per_leader' => $row['plan_price_per_leader'],
        'price_per_consultant' => $row['plan_price_per_consultant'],
        'fixed_monthly_price' => $row['plan_fixed_monthly_price'],
    ];
}

function accounting_current_invoice_id(int $resellerId, string $startsAt, string $endsAt): ?int
{
    $stmt = db()->prepare(
        'SELECT id
         FROM leader_subscriptions
         WHERE reseller_id = :reseller_id
           AND starts_at = :starts_at
           AND ends_at = :ends_at
         ORDER BY id DESC
         LIMIT 1'
    );
    $stmt->execute([
        'reseller_id' => $resellerId,
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
    ]);
    $id = $stmt->fetchColumn();

    return $id ? (int)$id : null;
}

function accounting_save_invoice(int $resellerId, array $admin): int
{
    $plan = subscription_plan_for_reseller($resellerId, false);
    if (!$plan) {
        throw new RuntimeException('У лидера не выбрана подписка.');
    }

    $billing = subscription_plan_usage_amount($resellerId, $plan);
    if (!$billing) {
        throw new RuntimeException('Не удалось рассчитать подписку.');
    }

    [$startsAt, $endsAt, $periodCode] = accounting_period();
    $invoiceId = accounting_current_invoice_id($resellerId, $startsAt, $endsAt);
    $note = 'Начисление: ' . $billing['mode_label']
        . ', ' . $billing['basis_label']
        . ', лидеры ' . (int)$billing['leaders']
        . ', консультанты ' . (int)$billing['consultants']
        . '.';

    $params = [
        'reseller_id' => $resellerId,
        'subscription_plan_id' => (int)$plan['id'],
        'consultant_limit' => $plan['branch_consultant_limit'],
        'leader_limit' => $plan['branch_leader_limit'],
        'price_per_consultant' => $billing['price_per_consultant'] > 0 ? $billing['price_per_consultant'] : null,
        'price_per_leader' => $billing['price_per_leader'] > 0 ? $billing['price_per_leader'] : null,
        'amount_due' => $billing['amount_due'],
        'leader_amount_due' => $billing['leader_amount'],
        'billing_mode' => $billing['billing_mode'],
        'billing_basis' => $billing['basis'],
        'direct_leader_limit' => $plan['direct_leader_limit'],
        'branch_leader_limit' => $plan['branch_leader_limit'],
        'direct_consultant_limit' => $plan['direct_consultant_limit'],
        'branch_consultant_limit' => $plan['branch_consultant_limit'],
        'per_child_consultant_limit' => $plan['per_child_consultant_limit'],
        'status' => 'active',
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
        'monthly_price' => $billing['amount_due'],
        'invoice_number' => 'SWP-' . $periodCode . '-' . $resellerId,
        'payment_note' => $note,
        'activated_by' => (int)$admin['id'],
    ];

    if ($invoiceId) {
        $params['id'] = $invoiceId;
        $stmt = db()->prepare(
            'UPDATE leader_subscriptions
             SET subscription_plan_id = :subscription_plan_id,
                 reseller_id = :reseller_id,
                 consultant_limit = :consultant_limit,
                 leader_limit = :leader_limit,
                 price_per_consultant = :price_per_consultant,
                 price_per_leader = :price_per_leader,
                 amount_due = :amount_due,
                 leader_amount_due = :leader_amount_due,
                 billing_mode = :billing_mode,
                 billing_basis = :billing_basis,
                 direct_leader_limit = :direct_leader_limit,
                 branch_leader_limit = :branch_leader_limit,
                 direct_consultant_limit = :direct_consultant_limit,
                 branch_consultant_limit = :branch_consultant_limit,
                 per_child_consultant_limit = :per_child_consultant_limit,
                 status = :status,
                 starts_at = :starts_at,
                 ends_at = :ends_at,
                 monthly_price = :monthly_price,
                 invoice_number = :invoice_number,
                 payment_note = :payment_note,
                 activated_by = :activated_by
             WHERE id = :id'
        );
        $stmt->execute($params);
        log_activity('admin', (int)$admin['id'], 'update_leader_invoice', 'leader_subscriptions', $invoiceId);
        return $invoiceId;
    }

    $stmt = db()->prepare(
        'INSERT INTO leader_subscriptions (
            reseller_id, subscription_plan_id, consultant_limit, leader_limit,
            price_per_consultant, price_per_leader, amount_due, leader_amount_due,
            billing_mode, billing_basis, direct_leader_limit, branch_leader_limit,
            direct_consultant_limit, branch_consultant_limit, per_child_consultant_limit,
            status, starts_at, ends_at, monthly_price, invoice_number, payment_note, activated_by
         ) VALUES (
            :reseller_id, :subscription_plan_id, :consultant_limit, :leader_limit,
            :price_per_consultant, :price_per_leader, :amount_due, :leader_amount_due,
            :billing_mode, :billing_basis, :direct_leader_limit, :branch_leader_limit,
            :direct_consultant_limit, :branch_consultant_limit, :per_child_consultant_limit,
            :status, :starts_at, :ends_at, :monthly_price, :invoice_number, :payment_note, :activated_by
         )'
    );
    $stmt->execute($params);
    $newId = (int)db()->lastInsertId();
    log_activity('admin', (int)$admin['id'], 'create_leader_invoice', 'leader_subscriptions', $newId);

    return $newId;
}

function accounting_rows(): array
{
    $sortMap = [
        'id' => '`id`',
        'name' => '`reseller_name`',
        'plan' => '`plan_title`',
        'status' => '`invoice_status`',
        'paid_at' => '`paid_at`',
        'is_active' => '`is_active`',
    ];
    $where = [];
    $params = [];

    $planFilter = (string)($_GET['plan_id'] ?? 'all');
    if ($planFilter === 'none') {
        $where[] = 'r.subscription_plan_id IS NULL';
    } elseif ($planFilter !== 'all' && ctype_digit($planFilter)) {
        $where[] = 'r.subscription_plan_id = :plan_id';
        $params['plan_id'] = (int)$planFilter;
    }

    $paymentFilter = (string)($_GET['payment'] ?? 'all');
    if ($paymentFilter === 'paid') {
        $where[] = 'ls.paid_at IS NOT NULL';
    } elseif ($paymentFilter === 'unpaid') {
        $where[] = 'r.subscription_plan_id IS NOT NULL AND (ls.id IS NULL OR ls.paid_at IS NULL)';
    } elseif ($paymentFilter === 'no_invoice') {
        $where[] = 'ls.id IS NULL';
    }

    $activityFilter = (string)($_GET['activity'] ?? 'active');
    if ($activityFilter === 'active') {
        $where[] = 'r.is_active = 1';
    } elseif ($activityFilter === 'inactive') {
        $where[] = 'r.is_active = 0';
    }

    $baseWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    return admin_table_paginated_rows(
        "SELECT r.id,
                r.name AS reseller_name,
                r.email,
                r.phone,
                r.referral_code,
                r.is_active,
                r.subscription_plan_id,
                sp.title AS plan_title,
                sp.slug AS plan_slug,
                sp.billing_mode AS plan_billing_mode,
                sp.billing_basis AS plan_billing_basis,
                sp.direct_leader_limit AS plan_direct_leader_limit,
                sp.branch_leader_limit AS plan_branch_leader_limit,
                sp.direct_consultant_limit AS plan_direct_consultant_limit,
                sp.branch_consultant_limit AS plan_branch_consultant_limit,
                sp.per_child_consultant_limit AS plan_per_child_consultant_limit,
                sp.price_per_leader AS plan_price_per_leader,
                sp.price_per_consultant AS plan_price_per_consultant,
                sp.fixed_monthly_price AS plan_fixed_monthly_price,
                ls.id AS invoice_id,
                ls.status AS invoice_status,
                ls.starts_at,
                ls.ends_at,
                ls.paid_at,
                ls.amount_due AS stored_amount_due,
                ls.invoice_number,
                ls.payment_note
         FROM resellers r
         LEFT JOIN subscription_plans sp ON sp.id = r.subscription_plan_id
         LEFT JOIN (
            SELECT s.*
            FROM leader_subscriptions s
            INNER JOIN (
                SELECT reseller_id, MAX(id) AS latest_id
                FROM leader_subscriptions
                GROUP BY reseller_id
            ) latest ON latest.latest_id = s.id
         ) ls ON ls.reseller_id = r.id
         $baseWhere",
        $params,
        $sortMap,
        ['id', 'reseller_name', 'email', 'phone', 'referral_code', 'plan_title', 'plan_slug', 'invoice_number'],
        'id',
        'desc'
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $postAction = (string)($_POST['action'] ?? '');
    try {
        if ($postAction === 'save_invoice') {
            accounting_save_invoice((int)($_POST['reseller_id'] ?? 0), $admin);
            redirect('accounting.php?success=invoice_saved');
        }
        if ($postAction === 'mark_paid') {
            $id = (int)($_POST['invoice_id'] ?? 0);
            $stmt = db()->prepare('UPDATE leader_subscriptions SET paid_at = NOW(), status = "active", activated_by = :admin_id WHERE id = :id');
            $stmt->execute(['id' => $id, 'admin_id' => (int)$admin['id']]);
            log_activity('admin', (int)$admin['id'], 'mark_leader_invoice_paid', 'leader_subscriptions', $id);
            redirect('accounting.php?success=paid');
        }
        if ($postAction === 'mark_unpaid') {
            $id = (int)($_POST['invoice_id'] ?? 0);
            $stmt = db()->prepare('UPDATE leader_subscriptions SET paid_at = NULL WHERE id = :id');
            $stmt->execute(['id' => $id]);
            log_activity('admin', (int)$admin['id'], 'mark_leader_invoice_unpaid', 'leader_subscriptions', $id);
            redirect('accounting.php?success=unpaid');
        }
        if ($postAction === 'confirm_manual_payment') {
            if (($admin['role'] ?? '') !== 'superadmin') throw new RuntimeException('Недостаточно прав.');
            $transactionId = (int)($_POST['transaction_id'] ?? 0);
            $stmt = db()->prepare(
                'SELECT pt.billing_invoice_id
                 FROM payment_transactions pt
                 JOIN payment_methods pm ON pm.id = pt.payment_method_id AND pm.method_type = "manual"
                 JOIN billing_invoices bi ON bi.id = pt.billing_invoice_id AND bi.status IN ("awaiting_confirmation","overdue")
                 WHERE pt.id = :id AND pt.status = "pending" LIMIT 1'
            );
            $stmt->execute(['id' => $transactionId]);
            $invoiceId = (int)$stmt->fetchColumn();
            if (!$invoiceId) throw new RuntimeException('Ручной платёж не найден или уже обработан.');
            billing_complete_invoice($invoiceId, $transactionId, (int)$admin['id']);
            log_activity('admin', (int)$admin['id'], 'confirm_manual_payment', 'payment_transactions', $transactionId);
            redirect('accounting.php?success=manual_paid');
        }
    } catch (Throwable $e) {
        $errors[] = 'Не удалось выполнить действие: ' . $e->getMessage();
    }
}

$listData = accounting_rows();
$rows = $listData['rows'];
$meta = $listData['meta'];
$plans = ['all' => 'Все подписки', 'none' => 'Без подписки'];
foreach (subscription_plan_options(false) as $planOption) {
    $plans[(string)$planOption['id']] = (string)$planOption['label'];
}

require __DIR__ . '/../app/views/layouts/header.php';
?>
<div class="toolbar">
    <h1>Бухгалтерия</h1>
    <a class="button secondary-button" href="subscriptions.php">Тарифы подписок</a>
</div>

<?php if ($success === 'invoice_saved'): ?><div class="notice success">Начисление сохранено.</div><?php endif; ?>
<?php if ($success === 'paid'): ?><div class="notice success">Оплата отмечена.</div><?php endif; ?>
<?php if ($success === 'unpaid'): ?><div class="notice success">Отметка оплаты снята.</div><?php endif; ?>
<?php if ($success === 'manual_paid'): ?><div class="notice success">Ручной платёж подтверждён, срок рабочего места обновлён.</div><?php endif; ?>
<?php foreach ($errors as $error): ?><div class="alert"><?= h($error) ?></div><?php endforeach; ?>

<section class="panel">
    <p class="cell-muted">
        Сумма считается по правилам выбранной подписки: за оплаченные места или по фактическому количеству активных людей.
    </p>
    <?= render_admin_table_tools($meta, [
        ['name' => 'plan_id', 'label' => 'Подписка', 'options' => $plans],
        ['name' => 'payment', 'label' => 'Оплата', 'options' => [
            'all' => 'Любая',
            'paid' => 'Оплачено',
            'unpaid' => 'К оплате',
            'no_invoice' => 'Нет начисления',
        ]],
        ['name' => 'activity', 'label' => 'Статус лидера', 'options' => [
            'all' => 'Любой',
            'active' => 'Активные',
            'inactive' => 'Отключённые',
        ]],
    ], [
        'reset_url' => 'accounting.php',
        'search_placeholder' => 'Лидер, email, телефон, реф. код, подписка',
    ]) ?>

    <div class="table-summary">Найдено записей: <?= (int)$meta['total'] ?></div>
    <?php if ($rows): ?>
        <table class="data-table responsive-table" data-module="accounting">
            <thead>
            <tr>
                <th><?= render_admin_sort_link('id', 'ID', $meta, ['id' => '`id`'], 'accounting.php') ?></th>
                <th><?= render_admin_sort_link('name', 'Лидер', $meta, ['name' => '`reseller_name`'], 'accounting.php') ?></th>
                <th><?= render_admin_sort_link('plan', 'Подписка', $meta, ['plan' => '`plan_title`'], 'accounting.php') ?></th>
                <th>Фактическое использование</th>
                <th>Расчёт</th>
                <th>К оплате</th>
                <th>Рабочие места</th>
                <th><?= render_admin_sort_link('paid_at', 'Оплата', $meta, ['paid_at' => '`paid_at`'], 'accounting.php') ?></th>
                <th>Действия</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <?php
                $plan = accounting_plan_from_row($row);
                $billing = $plan ? subscription_plan_usage_amount((int)$row['id'], $plan) : null;
                $summary = $billing['summary'] ?? team_branch_summary((int)$row['id']);
                $rootId = billing_root_reseller_id((int)$row['id']);
                $workspaceSummary = $rootId === (int)$row['id'] ? billing_root_summary($rootId) : null;
                ?>
                <tr>
                    <td data-label="ID"><?= (int)$row['id'] ?></td>
                    <td data-label="Лидер">
                        <strong><?= h((string)$row['reseller_name']) ?></strong>
                        <div class="cell-muted"><?= h(trim((string)$row['email'] . ' ' . (string)$row['phone'])) ?></div>
                        <div class="cell-muted"><?= h((string)$row['referral_code']) ?></div>
                    </td>
                    <td data-label="Подписка">
                        <?php if ($plan): ?>
                            <strong><?= h((string)$plan['title']) ?></strong>
                            <div class="cell-muted"><?= h(trim((string)($billing['mode_label'] ?? '') . ', ' . (string)($billing['basis_label'] ?? ''), ' ,')) ?></div>
                        <?php else: ?>
                            <span class="badge badge-pending">Нет подписки</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Фактическое использование">
                        <div class="compact-lines">
                            <span><strong>Лидеры 1-го уровня:</strong> <?= (int)$summary['direct_leaders'] ?></span>
                            <span><strong>Всего лидеров в ветке:</strong> <?= (int)$summary['branch_leaders'] ?></span>
                            <span><strong>Консультанты 1-го уровня:</strong> <?= (int)$summary['direct_consultants'] ?></span>
                            <span><strong>Всего консультантов в ветке:</strong> <?= (int)$summary['branch_consultants'] ?></span>
                        </div>
                    </td>
                    <td data-label="Расчёт">
                        <?php if ($billing): ?>
                            <?php $unitLabel = ($billing['billing_mode'] ?? 'prepaid') === 'prepaid' ? 'мест' : 'активных'; ?>
                            <div class="compact-lines">
                                <span><strong>База:</strong> <?= h(subscription_money_text($billing['base_amount'])) ?></span>
                                <span><strong>Лидеры:</strong> <?= (int)$billing['leaders'] ?> <?= h($unitLabel) ?> x <?= h(subscription_money_text($billing['price_per_leader'])) ?></span>
                                <span><strong>Консультанты:</strong> <?= (int)$billing['consultants'] ?> <?= h($unitLabel) ?> x <?= h(subscription_money_text($billing['price_per_consultant'])) ?></span>
                                <?php if (($billing['billing_mode'] ?? 'prepaid') === 'prepaid'): ?>
                                    <span class="cell-muted">Сейчас активны: лидеры <?= (int)$billing['active_leaders'] ?>, консультанты <?= (int)$billing['active_consultants'] ?></span>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td data-label="К оплате">
                        <strong><?= h($billing ? subscription_money_text($billing['amount_due']) : '—') ?></strong>
                        <?php if ($row['stored_amount_due'] !== null): ?>
                            <div class="cell-muted">Последнее начисление: <?= h(subscription_money_text((float)$row['stored_amount_due'])) ?></div>
                        <?php endif; ?>
                    </td>
                    <td data-label="Рабочие места">
                        <?php if ($workspaceSummary): ?>
                            <div class="compact-lines">
                                <span>Мест: <strong><?= $workspaceSummary['workspaces'] ?></strong></span>
                                <span>Оплачено: <strong><?= h(subscription_money_text($workspaceSummary['paid'])) ?></strong></span>
                                <span>Задолженность: <strong><?= h(subscription_money_text($workspaceSummary['debt'])) ?></strong></span>
                                <span>Должников: <strong><?= $workspaceSummary['debtors'] ?></strong></span>
                                <a href="accounting_detail.php?root_id=<?= $rootId ?>">Открыть детализацию</a>
                            </div>
                        <?php else: ?><span class="cell-muted">В составе главной ветки</span><?php endif; ?>
                    </td>
                    <td data-label="Оплата">
                        <?= accounting_status_badge($row['invoice_status'] ?? null, $row['paid_at'] ?? null) ?>
                        <?php if (!empty($row['invoice_number'])): ?><div class="cell-muted"><?= h((string)$row['invoice_number']) ?></div><?php endif; ?>
                        <?php if (!empty($row['starts_at']) || !empty($row['ends_at'])): ?>
                            <div class="cell-muted"><?= h(trim((string)$row['starts_at'] . ' - ' . (string)$row['ends_at'], ' -')) ?></div>
                        <?php endif; ?>
                    </td>
                    <td data-label="Действия" class="row-actions">
                        <?php if ($plan): ?>
                            <form method="post" class="inline-form">
                                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                <input type="hidden" name="action" value="save_invoice">
                                <input type="hidden" name="reseller_id" value="<?= (int)$row['id'] ?>">
                                <button type="submit" class="link-button">Сохранить начисление</button>
                            </form>
                        <?php endif; ?>
                        <?php if (!empty($row['invoice_id'])): ?>
                            <?php if (empty($row['paid_at'])): ?>
                                <form method="post" class="inline-form">
                                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="mark_paid">
                                    <input type="hidden" name="invoice_id" value="<?= (int)$row['invoice_id'] ?>">
                                    <button type="submit" class="link-button">Отметить оплачено</button>
                                </form>
                            <?php else: ?>
                                <form method="post" class="inline-form">
                                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="mark_unpaid">
                                    <input type="hidden" name="invoice_id" value="<?= (int)$row['invoice_id'] ?>">
                                    <button type="submit" class="link-button danger">Снять оплату</button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                        <a class="link-button" href="crud.php?module=resellers&action=edit&id=<?= (int)$row['id'] ?>">Карточка лидера</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?= render_admin_pagination($meta, 'accounting.php') ?>
    <?php else: ?>
        <div class="empty-state">Лидеры не найдены.</div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../app/views/layouts/footer.php'; ?>
