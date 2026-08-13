<?php

require_once __DIR__ . '/subscription_plans.php';

function billing_root_reseller_id(int $resellerId): int
{
    $current = $resellerId;
    $seen = [];
    while ($current > 0 && !isset($seen[$current])) {
        $seen[$current] = true;
        $stmt = db()->prepare('SELECT parent_reseller_id FROM resellers WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $current]);
        $parent = (int)$stmt->fetchColumn();
        if ($parent <= 0) {
            return $current;
        }
        $current = $parent;
    }
    return $resellerId;
}

function billing_plan_for_reseller_branch(int $resellerId): ?array
{
    $current = $resellerId;
    $seen = [];
    while ($current > 0 && !isset($seen[$current])) {
        $seen[$current] = true;
        $stmt = db()->prepare('SELECT parent_reseller_id, subscription_plan_id FROM resellers WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $current]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        if (!empty($row['subscription_plan_id'])) {
            return subscription_plan_row((int)$row['subscription_plan_id'], false);
        }
        $current = (int)($row['parent_reseller_id'] ?? 0);
    }
    return null;
}

function billing_subject_context(string $subjectType, int $subjectId): ?array
{
    if ($subjectType === 'reseller') {
        $stmt = db()->prepare('SELECT id, parent_reseller_id, created_at, updated_at, is_active FROM resellers WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $subjectId]);
        $subject = $stmt->fetch();
        if (!$subject) {
            return null;
        }
        $resellerId = (int)$subject['id'];
        $rootId = billing_root_reseller_id($resellerId);
        $unitType = empty($subject['parent_reseller_id']) ? 'base' : 'leader';
    } elseif ($subjectType === 'manager') {
        $stmt = db()->prepare('SELECT id, reseller_id, created_at, updated_at, is_active FROM managers WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $subjectId]);
        $subject = $stmt->fetch();
        if (!$subject || empty($subject['reseller_id'])) {
            return null;
        }
        $resellerId = (int)$subject['reseller_id'];
        $rootId = billing_root_reseller_id($resellerId);
        $unitType = 'consultant';
    } else {
        return null;
    }

    $plan = billing_plan_for_reseller_branch($resellerId);
    if (!$plan) {
        return null;
    }
    $monthlyPrice = match ($unitType) {
        'base' => subscription_money_value($plan['fixed_monthly_price'] ?? null),
        'leader' => subscription_money_value($plan['price_per_leader'] ?? null),
        default => subscription_money_value($plan['price_per_consultant'] ?? null),
    };

    return [
        'subject_type' => $subjectType,
        'subject_id' => $subjectId,
        'root_reseller_id' => $rootId,
        'subscription_plan_id' => (int)$plan['id'],
        'unit_type' => $unitType,
        'billing_mode' => subscription_plan_billing_mode($plan),
        'monthly_price' => $monthlyPrice,
        // Existing workspaces must not receive retroactive invoices when this
        // billing subsystem is first deployed. Newly created staff are synced
        // on the same day, so CURRENT_DATE is also their correct first day.
        'active_from' => max(substr((string)$subject['created_at'], 0, 10), date('Y-m-d')),
        'inactive_at' => (int)$subject['is_active'] === 1 ? null : substr((string)($subject['updated_at'] ?? date('Y-m-d')), 0, 10),
        'plan' => $plan,
    ];
}

function billing_legacy_paid_until(int $rootResellerId): ?string
{
    $stmt = db()->prepare(
        'SELECT MAX(DATE(ends_at)) FROM leader_subscriptions
         WHERE reseller_id = :reseller_id AND paid_at IS NOT NULL'
    );
    $stmt->execute(['reseller_id' => $rootResellerId]);
    $value = $stmt->fetchColumn();
    return $value ? (string)$value : null;
}

function billing_sync_workspace(string $subjectType, int $subjectId): ?array
{
    $context = billing_subject_context($subjectType, $subjectId);
    if (!$context) {
        return null;
    }
    $paidUntil = $context['billing_mode'] === 'prepaid'
        ? billing_legacy_paid_until((int)$context['root_reseller_id'])
        : null;
    $stmt = db()->prepare(
        'INSERT INTO workspace_subscriptions
            (subject_type, subject_id, root_reseller_id, subscription_plan_id, unit_type,
             billing_mode, monthly_price, active_from, inactive_at, paid_until, status)
         VALUES
            (:subject_type, :subject_id, :root_reseller_id, :subscription_plan_id, :unit_type,
             :billing_mode, :monthly_price, :active_from, :inactive_at, :paid_until, "active")
         ON DUPLICATE KEY UPDATE
            root_reseller_id = VALUES(root_reseller_id),
            subscription_plan_id = VALUES(subscription_plan_id),
            unit_type = VALUES(unit_type),
            billing_mode = VALUES(billing_mode),
            monthly_price = VALUES(monthly_price),
            inactive_at = VALUES(inactive_at)'
    );
    $stmt->execute(array_diff_key($context, ['plan' => true]) + ['paid_until' => $paidUntil]);

    $get = db()->prepare('SELECT * FROM workspace_subscriptions WHERE subject_type = :type AND subject_id = :id LIMIT 1');
    $get->execute(['type' => $subjectType, 'id' => $subjectId]);
    return $get->fetch() ?: null;
}

function billing_sync_all_workspaces(): array
{
    $counts = ['resellers' => 0, 'managers' => 0];
    foreach (db()->query('SELECT id FROM resellers')->fetchAll() as $row) {
        if (billing_sync_workspace('reseller', (int)$row['id'])) {
            $counts['resellers']++;
        }
    }
    foreach (db()->query('SELECT id FROM managers')->fetchAll() as $row) {
        if (billing_sync_workspace('manager', (int)$row['id'])) {
            $counts['managers']++;
        }
    }
    return $counts;
}

function billing_workspace_for_admin(array $admin): ?array
{
    if (($admin['role'] ?? '') === 'reseller' && !empty($admin['reseller_id'])) {
        return billing_sync_workspace('reseller', (int)$admin['reseller_id']);
    }
    if (($admin['role'] ?? '') === 'manager' && !empty($admin['manager_id'])) {
        return billing_sync_workspace('manager', (int)$admin['manager_id']);
    }
    return null;
}

function billing_period_discounts(int $planId): array
{
    $stmt = db()->prepare(
        'SELECT * FROM subscription_period_discounts
         WHERE subscription_plan_id = :plan_id AND is_active = 1
         ORDER BY sort_order, months'
    );
    $stmt->execute(['plan_id' => $planId]);
    return $stmt->fetchAll();
}

function billing_invoice_number(): string
{
    return 'SWP-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function billing_create_prepaid_invoice(array $workspace, int $months): array
{
    if (($workspace['billing_mode'] ?? '') !== 'prepaid') {
        throw new InvalidArgumentException('Для этого рабочего места используется оплата по факту.');
    }
    $discounts = billing_period_discounts((int)$workspace['subscription_plan_id']);
    $discount = null;
    foreach ($discounts as $item) {
        if ((int)$item['months'] === $months) {
            $discount = $item;
            break;
        }
    }
    if (!$discount) {
        throw new InvalidArgumentException('Выбранный период оплаты недоступен.');
    }

    $today = new DateTimeImmutable('today');
    $paidUntil = !empty($workspace['paid_until']) ? new DateTimeImmutable((string)$workspace['paid_until']) : null;
    $start = $paidUntil && $paidUntil >= $today ? $paidUntil->modify('+1 day') : $today;
    $end = $start->modify('+' . $months . ' months')->modify('-1 day');
    $base = round((float)$workspace['monthly_price'] * $months, 2);
    $percent = (float)$discount['discount_percent'];
    $discountAmount = round($base * $percent / 100, 2);
    $total = round($base - $discountAmount, 2);

    $stmt = db()->prepare(
        'INSERT INTO billing_invoices
            (workspace_subscription_id, root_reseller_id, invoice_number, invoice_type,
             period_start, period_end, months, base_amount, discount_percent, discount_amount,
             amount_due, due_at, status)
         VALUES
            (:workspace_id, :root_id, :number, "prepaid", :period_start, :period_end, :months,
             :base_amount, :discount_percent, :discount_amount, :amount_due, NOW(), "pending")'
    );
    $stmt->execute([
        'workspace_id' => (int)$workspace['id'],
        'root_id' => (int)$workspace['root_reseller_id'],
        'number' => billing_invoice_number(),
        'period_start' => $start->format('Y-m-d'),
        'period_end' => $end->format('Y-m-d'),
        'months' => $months,
        'base_amount' => $base,
        'discount_percent' => $percent,
        'discount_amount' => $discountAmount,
        'amount_due' => $total,
    ]);
    return billing_invoice((int)db()->lastInsertId()) ?? [];
}

function billing_month_fraction(DateTimeImmutable $activeStart, DateTimeImmutable $activeEnd, DateTimeImmutable $monthStart): float
{
    $monthEnd = $monthStart->modify('last day of this month');
    $start = $activeStart > $monthStart ? $activeStart : $monthStart;
    $end = $activeEnd < $monthEnd ? $activeEnd : $monthEnd;
    if ($end < $start) {
        return 0.0;
    }
    $activeDays = (int)$start->diff($end)->days + 1;
    $monthDays = (int)$monthStart->format('t');
    return $activeDays / $monthDays;
}

function billing_generate_actual_invoices(?DateTimeImmutable $month = null): array
{
    billing_sync_all_workspaces();
    $month = ($month ?: new DateTimeImmutable('first day of last month'))->modify('first day of this month');
    $monthEnd = $month->modify('last day of this month');
    $created = 0;
    $skipped = 0;
    $rows = db()->query('SELECT ws.*, sp.payment_grace_days FROM workspace_subscriptions ws JOIN subscription_plans sp ON sp.id = ws.subscription_plan_id WHERE ws.billing_mode = "actual"')->fetchAll();
    foreach ($rows as $workspace) {
        $activeStart = new DateTimeImmutable((string)$workspace['active_from']);
        $activeEnd = !empty($workspace['inactive_at']) ? new DateTimeImmutable((string)$workspace['inactive_at']) : $monthEnd;
        $fraction = billing_month_fraction($activeStart, $activeEnd, $month);
        if ($fraction <= 0) {
            $skipped++;
            continue;
        }
        $amount = round((float)$workspace['monthly_price'] * $fraction, 2);
        if ($amount <= 0) {
            $skipped++;
            continue;
        }
        // Invoice appears on the 1st; with five payment days it is payable
        // through the 5th and becomes overdue on the 6th.
        $dueAt = $monthEnd->modify('+' . max(0, (int)$workspace['payment_grace_days']) . ' days')->setTime(23, 59, 59);
        $stmt = db()->prepare(
            'INSERT IGNORE INTO billing_invoices
                (workspace_subscription_id, root_reseller_id, invoice_number, invoice_type,
                 period_start, period_end, months, base_amount, amount_due, due_at, status)
             VALUES
                (:workspace_id, :root_id, :number, "actual", :period_start, :period_end,
                 1, :amount, :amount, :due_at, "pending")'
        );
        $stmt->execute([
            'workspace_id' => (int)$workspace['id'],
            'root_id' => (int)$workspace['root_reseller_id'],
            'number' => billing_invoice_number(),
            'period_start' => $month->format('Y-m-d'),
            'period_end' => $monthEnd->format('Y-m-d'),
            'amount' => $amount,
            'due_at' => $dueAt->format('Y-m-d H:i:s'),
        ]);
        $created += $stmt->rowCount();
    }
    billing_refresh_statuses();
    return ['period' => $month->format('Y-m'), 'created' => $created, 'skipped' => $skipped];
}

function billing_invoice(int $invoiceId): ?array
{
    $stmt = db()->prepare('SELECT * FROM billing_invoices WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $invoiceId]);
    return $stmt->fetch() ?: null;
}

function billing_refresh_statuses(): void
{
    db()->exec('UPDATE billing_invoices SET status = "overdue" WHERE status IN ("pending","awaiting_confirmation") AND due_at IS NOT NULL AND due_at < NOW() AND amount_paid < amount_due');
    db()->exec(
        'UPDATE workspace_subscriptions ws
         SET status = CASE
            WHEN ws.monthly_price <= 0 THEN "active"
            WHEN ws.billing_mode = "prepaid" AND (ws.paid_until IS NULL OR ws.paid_until < CURRENT_DATE) THEN "overdue"
            WHEN ws.billing_mode = "actual" AND EXISTS (
                SELECT 1 FROM billing_invoices bi
                WHERE bi.workspace_subscription_id = ws.id AND bi.status = "overdue"
            ) THEN "overdue"
            WHEN EXISTS (
                SELECT 1 FROM billing_invoices bi
                WHERE bi.workspace_subscription_id = ws.id AND bi.status IN ("pending","awaiting_confirmation")
            ) THEN "due"
            ELSE "active"
         END'
    );
}

function billing_complete_invoice(int $invoiceId, ?int $transactionId = null, ?int $adminId = null): void
{
    $invoice = billing_invoice($invoiceId);
    if (!$invoice || $invoice['status'] === 'paid') {
        return;
    }
    db()->beginTransaction();
    try {
        $stmt = db()->prepare('UPDATE billing_invoices SET amount_paid = amount_due, status = "paid", paid_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $invoiceId]);
        if ($transactionId) {
            $stmt = db()->prepare('UPDATE payment_transactions SET status = "succeeded", confirmed_by = :admin_id, confirmed_at = NOW() WHERE id = :id');
            $stmt->execute(['id' => $transactionId, 'admin_id' => $adminId]);
        }
        if ($invoice['invoice_type'] === 'prepaid') {
            $stmt = db()->prepare('UPDATE workspace_subscriptions SET paid_until = :paid_until, status = "active" WHERE id = :id');
            $stmt->execute(['paid_until' => $invoice['period_end'], 'id' => $invoice['workspace_subscription_id']]);
        } else {
            $stmt = db()->prepare('UPDATE workspace_subscriptions SET status = "active" WHERE id = :id');
            $stmt->execute(['id' => $invoice['workspace_subscription_id']]);
        }
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
    billing_refresh_statuses();
}

function billing_workspace_is_blocked(string $subjectType, int $subjectId): bool
{
    $workspace = billing_sync_workspace($subjectType, $subjectId);
    if (!$workspace) {
        return false;
    }
    if ((float)$workspace['monthly_price'] <= 0) {
        return false;
    }
    billing_refresh_statuses();
    $stmt = db()->prepare('SELECT status FROM workspace_subscriptions WHERE id = :id');
    $stmt->execute(['id' => $workspace['id']]);
    return in_array((string)$stmt->fetchColumn(), ['overdue', 'suspended'], true);
}

function billing_profile_is_blocked(array $profile): bool
{
    $type = (string)($profile['owner_type'] ?? '');
    $id = (int)($profile['owner_id'] ?? 0);
    return $id > 0 && billing_workspace_is_blocked($type, $id);
}

function billing_workspace_invoices(int $workspaceId, int $limit = 20): array
{
    $limit = max(1, min(100, $limit));
    $stmt = db()->prepare('SELECT * FROM billing_invoices WHERE workspace_subscription_id = :id ORDER BY id DESC LIMIT ' . $limit);
    $stmt->execute(['id' => $workspaceId]);
    return $stmt->fetchAll();
}

function billing_active_payment_methods(): array
{
    return db()->query('SELECT id, code, title, method_type, description, instructions, is_test FROM payment_methods WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();
}

function billing_root_summary(int $rootResellerId): array
{
    $stmt = db()->prepare(
        'SELECT
            COUNT(DISTINCT ws.id) AS workspaces,
            SUM(bi.amount_due) AS charged,
            SUM(bi.amount_paid) AS paid,
            SUM(CASE WHEN bi.status IN ("pending","awaiting_confirmation","overdue") THEN bi.amount_due - bi.amount_paid ELSE 0 END) AS debt,
            COUNT(DISTINCT CASE WHEN ws.status IN ("overdue","suspended") THEN ws.id END) AS debtors
         FROM workspace_subscriptions ws
         LEFT JOIN billing_invoices bi ON bi.workspace_subscription_id = ws.id
         WHERE ws.root_reseller_id = :root_id'
    );
    $stmt->execute(['root_id' => $rootResellerId]);
    $row = $stmt->fetch() ?: [];
    return [
        'workspaces' => (int)($row['workspaces'] ?? 0),
        'charged' => (float)($row['charged'] ?? 0),
        'paid' => (float)($row['paid'] ?? 0),
        'debt' => (float)($row['debt'] ?? 0),
        'debtors' => (int)($row['debtors'] ?? 0),
    ];
}
