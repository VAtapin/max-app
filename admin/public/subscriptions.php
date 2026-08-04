<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/permissions.php';
require_once __DIR__ . '/../app/core/team_tree.php';

$admin = require_auth();
if ($admin['role'] !== 'superadmin') {
    http_response_code(403);
    exit('Access denied');
}

$title = 'Оплата лидеров';
$errors = [];
$success = $_GET['success'] ?? null;

function subscription_status_labels(): array
{
    return [
        'pending' => 'Ожидает оплаты',
        'active' => 'Активна',
        'expired' => 'Истекла',
        'suspended' => 'Приостановлена',
    ];
}

function subscription_billing_basis_labels(): array
{
    return [
        'branch' => 'Вся ветка',
        'direct' => 'Только прямой уровень',
    ];
}

function setting_value(string $key, string $default = ''): string
{
    $stmt = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = :key LIMIT 1');
    $stmt->execute(['key' => $key]);
    $value = $stmt->fetchColumn();

    return $value === false || $value === null ? $default : (string)$value;
}

function save_setting(string $key, string $value, string $description): void
{
    $stmt = db()->prepare(
        'INSERT INTO settings (setting_key, setting_value, description)
         VALUES (:setting_key, :setting_value, :description)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), description = VALUES(description)'
    );
    $stmt->execute([
        'setting_key' => $key,
        'setting_value' => $value,
        'description' => $description,
    ]);
}

function money_text(?float $value): string
{
    return $value === null ? '—' : number_format($value, 2, ',', ' ') . ' руб.';
}

function subscription_parse_money(string $value): ?float
{
    $value = str_replace(',', '.', trim($value));
    if ($value === '') {
        return null;
    }

    return is_numeric($value) ? (float)$value : null;
}

function subscription_parse_limit(string $value): ?int
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    return ctype_digit($value) ? (int)$value : -1;
}

function subscription_period_text(array $item): string
{
    $startsAt = trim((string)($item['subscription_starts_at'] ?? $item['starts_at'] ?? ''));
    $endsAt = trim((string)($item['subscription_ends_at'] ?? $item['ends_at'] ?? ''));
    if ($startsAt === '' && $endsAt === '') {
        return '—';
    }

    return ($startsAt !== '' ? $startsAt : 'без даты') . ' - ' . ($endsAt !== '' ? $endsAt : 'без окончания');
}

function subscription_limit_text(?int $limit): string
{
    return $limit === null ? 'без лимита' : (string)$limit;
}

function subscription_limit_cell(int $used, ?int $limit): string
{
    return (string)$used . ' / ' . subscription_limit_text($limit);
}

function subscription_max_child_consultants(int $resellerId): int
{
    $max = 0;
    $childrenMap = team_children_map(true);
    foreach ($childrenMap[$resellerId] ?? [] as $childId) {
        $max = max($max, team_branch_manager_count((int)$childId));
    }

    return $max;
}

$baseLeaderPrice = setting_value('leader_price_per_leader', '500');
$baseConsultantPrice = setting_value('leader_price_per_consultant', '300');
$paymentTerms = setting_value(
    'leader_payment_terms',
    'Оплата подтверждается администратором вручную. Онлайн-касса на первом этапе не подключена.'
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $postAction = (string)($_POST['action'] ?? 'save_subscription');

    if ($postAction === 'save_billing_settings') {
        $leaderPrice = subscription_parse_money((string)($_POST['leader_price_per_leader'] ?? ''));
        $consultantPrice = subscription_parse_money((string)($_POST['leader_price_per_consultant'] ?? ''));
        $terms = trim((string)($_POST['leader_payment_terms'] ?? ''));
        if ($leaderPrice === null || $leaderPrice <= 0) {
            $errors[] = 'Укажите цену за лидера больше нуля.';
        }
        if ($consultantPrice === null || $consultantPrice <= 0) {
            $errors[] = 'Укажите цену за консультанта больше нуля.';
        }
        if ($terms === '') {
            $errors[] = 'Укажите короткие условия оплаты.';
        }

        if (!$errors) {
            save_setting('leader_price_per_leader', number_format((float)$leaderPrice, 2, '.', ''), 'Базовая ежемесячная стоимость одного дочернего лидера');
            save_setting('leader_price_per_consultant', number_format((float)$consultantPrice, 2, '.', ''), 'Базовая ежемесячная стоимость одного консультанта в команде лидера');
            save_setting('leader_payment_terms', $terms, 'Короткая подсказка для бухгалтерской панели лидеров');
            log_activity('admin', (int)$admin['id'], 'update_leader_billing_settings', 'settings');
            redirect('subscriptions.php?success=settings_saved');
        }
    }

    if ($postAction === 'save_subscription') {
        $resellerId = (int)($_POST['reseller_id'] ?? 0);
        $status = (string)($_POST['status'] ?? 'pending');
        $billingBasis = (string)($_POST['billing_basis'] ?? 'branch');
        $startsAt = trim((string)($_POST['starts_at'] ?? ''));
        $endsAt = trim((string)($_POST['ends_at'] ?? ''));
        $paidAt = trim((string)($_POST['paid_at'] ?? ''));
        $invoiceNumber = trim((string)($_POST['invoice_number'] ?? ''));
        $paymentMethod = trim((string)($_POST['payment_method'] ?? ''));
        $note = trim((string)($_POST['payment_note'] ?? ''));
        $directLeaderLimit = subscription_parse_limit((string)($_POST['direct_leader_limit'] ?? ''));
        $branchLeaderLimit = subscription_parse_limit((string)($_POST['branch_leader_limit'] ?? ''));
        $directConsultantLimit = subscription_parse_limit((string)($_POST['direct_consultant_limit'] ?? ''));
        $branchConsultantLimit = subscription_parse_limit((string)($_POST['branch_consultant_limit'] ?? ''));
        $perChildConsultantLimit = subscription_parse_limit((string)($_POST['per_child_consultant_limit'] ?? ''));
        $pricePerLeader = subscription_parse_money((string)($_POST['price_per_leader'] ?? $baseLeaderPrice));
        $pricePerConsultant = subscription_parse_money((string)($_POST['price_per_consultant'] ?? $baseConsultantPrice));

        foreach ([
            'Лимит прямых лидеров' => $directLeaderLimit,
            'Лимит лидеров во всей ветке' => $branchLeaderLimit,
            'Лимит прямых консультантов' => $directConsultantLimit,
            'Лимит консультантов во всей ветке' => $branchConsultantLimit,
            'Лимит консультантов на дочернего лидера' => $perChildConsultantLimit,
        ] as $label => $limit) {
            if ($limit !== null && $limit < 0) {
                $errors[] = $label . ': укажите целое число или оставьте поле пустым.';
            }
        }
        if ($resellerId <= 0 || !team_reseller_row($resellerId)) {
            $errors[] = 'Выберите лидера.';
        }
        if (!isset(subscription_status_labels()[$status])) {
            $errors[] = 'Некорректный статус.';
        }
        if (!isset(subscription_billing_basis_labels()[$billingBasis])) {
            $errors[] = 'Некорректный способ расчёта.';
        }
        if ($pricePerLeader === null || $pricePerLeader <= 0) {
            $errors[] = 'Укажите цену за лидера больше нуля.';
        }
        if ($pricePerConsultant === null || $pricePerConsultant <= 0) {
            $errors[] = 'Укажите цену за консультанта больше нуля.';
        }
        if ($status === 'active' && $endsAt === '') {
            $errors[] = 'Для активной подписки укажите дату окончания.';
        }

        if ($resellerId > 0) {
            $summary = team_branch_summary($resellerId);
            if ($directLeaderLimit !== null && $summary['direct_leaders'] > $directLeaderLimit) {
                $errors[] = 'Лимит прямых лидеров меньше текущего количества: ' . $summary['direct_leaders'] . '.';
            }
            if ($branchLeaderLimit !== null && $summary['branch_leaders'] > $branchLeaderLimit) {
                $errors[] = 'Лимит лидеров во всей ветке меньше текущего количества: ' . $summary['branch_leaders'] . '.';
            }
            if ($directConsultantLimit !== null && $summary['direct_consultants'] > $directConsultantLimit) {
                $errors[] = 'Лимит прямых консультантов меньше текущего количества: ' . $summary['direct_consultants'] . '.';
            }
            if ($branchConsultantLimit !== null && $summary['branch_consultants'] > $branchConsultantLimit) {
                $errors[] = 'Лимит консультантов во всей ветке меньше текущего количества: ' . $summary['branch_consultants'] . '.';
            }
            if ($perChildConsultantLimit !== null) {
                $maxChildConsultants = subscription_max_child_consultants($resellerId);
                if ($maxChildConsultants > $perChildConsultantLimit) {
                    $errors[] = 'Лимит на дочернего лидера меньше текущего максимума у дочерних лидеров: ' . $maxChildConsultants . '.';
                }
            }
        }

        if (!$errors) {
            $billingLeaderLimit = $billingBasis === 'direct' ? $directLeaderLimit : $branchLeaderLimit;
            $billingConsultantLimit = $billingBasis === 'direct' ? $directConsultantLimit : $branchConsultantLimit;
            $leaderAmount = (float)($billingLeaderLimit ?? 0) * (float)$pricePerLeader;
            $consultantAmount = (float)($billingConsultantLimit ?? 0) * (float)$pricePerConsultant;
            $amount = $leaderAmount + $consultantAmount;
            $consultantLimit = $branchConsultantLimit ?? $directConsultantLimit;
            $leaderLimit = $branchLeaderLimit ?? $directLeaderLimit;

            db()->beginTransaction();
            try {
                $stmt = db()->prepare(
                    'INSERT INTO leader_subscriptions (
                        reseller_id, consultant_limit, leader_limit,
                        price_per_consultant, price_per_leader,
                        amount_due, leader_amount_due, billing_basis,
                        direct_leader_limit, branch_leader_limit,
                        direct_consultant_limit, branch_consultant_limit, per_child_consultant_limit,
                        status, starts_at, ends_at, monthly_price, paid_at,
                        invoice_number, payment_method, payment_note, activated_by
                     ) VALUES (
                        :reseller_id, :consultant_limit, :leader_limit,
                        :price_per_consultant, :price_per_leader,
                        :amount_due, :leader_amount_due, :billing_basis,
                        :direct_leader_limit, :branch_leader_limit,
                        :direct_consultant_limit, :branch_consultant_limit, :per_child_consultant_limit,
                        :status, :starts_at, :ends_at, :monthly_price, :paid_at,
                        :invoice_number, :payment_method, :payment_note, :activated_by
                     )'
                );
                $stmt->execute([
                    'reseller_id' => $resellerId,
                    'consultant_limit' => $consultantLimit,
                    'leader_limit' => $leaderLimit,
                    'price_per_consultant' => $pricePerConsultant,
                    'price_per_leader' => $pricePerLeader,
                    'amount_due' => $amount,
                    'leader_amount_due' => $leaderAmount,
                    'billing_basis' => $billingBasis,
                    'direct_leader_limit' => $directLeaderLimit,
                    'branch_leader_limit' => $branchLeaderLimit,
                    'direct_consultant_limit' => $directConsultantLimit,
                    'branch_consultant_limit' => $branchConsultantLimit,
                    'per_child_consultant_limit' => $perChildConsultantLimit,
                    'status' => $status,
                    'starts_at' => $startsAt !== '' ? str_replace('T', ' ', $startsAt) : null,
                    'ends_at' => $endsAt !== '' ? str_replace('T', ' ', $endsAt) : null,
                    'monthly_price' => $amount,
                    'paid_at' => $paidAt !== '' ? str_replace('T', ' ', $paidAt) : null,
                    'invoice_number' => $invoiceNumber !== '' ? $invoiceNumber : null,
                    'payment_method' => $paymentMethod !== '' ? $paymentMethod : null,
                    'payment_note' => $note !== '' ? $note : null,
                    'activated_by' => $admin['id'],
                ]);
                $subscriptionId = (int)db()->lastInsertId();

                $updateLimit = db()->prepare(
                    'UPDATE resellers
                     SET manager_limit = :manager_limit,
                         direct_leader_limit = :direct_leader_limit,
                         branch_leader_limit = :branch_leader_limit,
                         direct_manager_limit = :direct_manager_limit,
                         branch_manager_limit = :branch_manager_limit,
                         per_child_manager_limit = :per_child_manager_limit,
                         price_per_leader = :price_per_leader,
                         price_per_consultant = :price_per_consultant
                     WHERE id = :id'
                );
                $updateLimit->execute([
                    'manager_limit' => $consultantLimit,
                    'direct_leader_limit' => $directLeaderLimit,
                    'branch_leader_limit' => $branchLeaderLimit,
                    'direct_manager_limit' => $directConsultantLimit,
                    'branch_manager_limit' => $branchConsultantLimit,
                    'per_child_manager_limit' => $perChildConsultantLimit,
                    'price_per_leader' => $pricePerLeader,
                    'price_per_consultant' => $pricePerConsultant,
                    'id' => $resellerId,
                ]);

                log_activity('admin', (int)$admin['id'], 'create_leader_subscription', 'leader_subscriptions', $subscriptionId, [
                    'reseller_id' => $resellerId,
                    'leader_limit' => $leaderLimit,
                    'consultant_limit' => $consultantLimit,
                    'billing_basis' => $billingBasis,
                    'amount_due' => $amount,
                ]);
                db()->commit();
                redirect('subscriptions.php?success=saved');
            } catch (Throwable $e) {
                db()->rollBack();
                $errors[] = 'Не удалось сохранить подписку: ' . $e->getMessage();
            }
        }
    }
}

$leaderOptions = team_reseller_options_for_admin($admin);
$leaderRows = db()->query(
    'SELECT r.id, r.parent_reseller_id, r.name, r.email, r.phone, r.billing_name, r.billing_inn, r.billing_email,
            r.manager_limit, r.direct_leader_limit, r.branch_leader_limit,
            r.direct_manager_limit, r.branch_manager_limit, r.per_child_manager_limit,
            r.price_per_leader AS reseller_price_per_leader,
            r.price_per_consultant AS reseller_price_per_consultant,
            r.is_active,
            parent.name AS parent_name,
            ls.status AS subscription_status,
            ls.starts_at AS subscription_starts_at,
            ls.ends_at AS subscription_ends_at,
            ls.consultant_limit AS subscription_consultant_limit,
            ls.leader_limit AS subscription_leader_limit,
            ls.price_per_consultant,
            ls.price_per_leader,
            ls.amount_due,
            ls.leader_amount_due,
            ls.billing_basis,
            ls.paid_at,
            ls.invoice_number,
            ls.payment_method,
            ls.payment_note
     FROM resellers r
     LEFT JOIN resellers parent ON parent.id = r.parent_reseller_id
     LEFT JOIN (
        SELECT s.*
        FROM leader_subscriptions s
        INNER JOIN (
            SELECT reseller_id, MAX(id) AS latest_id
            FROM leader_subscriptions
            GROUP BY reseller_id
        ) latest ON latest.latest_id = s.id
     ) ls ON ls.reseller_id = r.id
     ORDER BY parent.name IS NULL DESC, parent.name, r.name ASC'
)->fetchAll();

$summary = [
    'leaders' => count($leaderRows),
    'active_subscriptions' => 0,
    'branch_leaders' => 0,
    'branch_consultants' => 0,
    'monthly_revenue' => 0.0,
    'pending' => 0,
    'problem' => 0,
];
$now = time();
foreach ($leaderRows as &$row) {
    $row['team_summary'] = team_branch_summary((int)$row['id']);
    $summary['branch_leaders'] += (int)$row['team_summary']['branch_leaders'];
    $summary['branch_consultants'] += (int)$row['team_summary']['branch_consultants'];
    $status = (string)($row['subscription_status'] ?? '');
    if ($status === 'pending') {
        $summary['pending']++;
    }
    $startsAt = !empty($row['subscription_starts_at']) ? strtotime((string)$row['subscription_starts_at']) : null;
    $endsAt = !empty($row['subscription_ends_at']) ? strtotime((string)$row['subscription_ends_at']) : null;
    $isActivePeriod = $status === 'active' && (!$startsAt || $startsAt <= $now) && (!$endsAt || $endsAt >= $now);
    if ($isActivePeriod) {
        $summary['active_subscriptions']++;
        $summary['monthly_revenue'] += (float)($row['amount_due'] ?? 0);
    } elseif ($status !== '' && $status !== 'pending') {
        $summary['problem']++;
    }
}
unset($row);

require __DIR__ . '/../app/views/layouts/header.php';
?>
<div class="toolbar"><h1>Оплата лидеров</h1></div>
<?php if ($success === 'saved'): ?><div class="notice success">Подписка сохранена, лимиты лидера обновлены.</div><?php endif; ?>
<?php if ($success === 'settings_saved'): ?><div class="notice success">Параметры оплаты сохранены.</div><?php endif; ?>
<?php foreach ($errors as $error): ?><div class="alert"><?= h($error) ?></div><?php endforeach; ?>

<section class="grid stats-grid">
    <article class="stat"><span>Лидеров</span><strong><?= (int)$summary['leaders'] ?></strong></article>
    <article class="stat"><span>Активных подписок</span><strong><?= (int)$summary['active_subscriptions'] ?></strong></article>
    <article class="stat"><span>Лидеров в ветках</span><strong><?= (int)$summary['branch_leaders'] ?></strong></article>
    <article class="stat"><span>Консультантов в ветках</span><strong><?= (int)$summary['branch_consultants'] ?></strong></article>
    <article class="stat"><span>План в месяц</span><strong><?= h(money_text((float)$summary['monthly_revenue'])) ?></strong></article>
    <article class="stat"><span>Ожидают/проблемы</span><strong><?= (int)($summary['pending'] + $summary['problem']) ?></strong></article>
</section>

<section class="panel form-panel">
    <h2>Тарифы и правила оплаты</h2>
    <p class="cell-muted">Базовые цены подставляются в новую подписку. Итог можно считать по прямому уровню лидера или по всей его ветке.</p>
    <form method="post" class="crud-form">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_billing_settings">
        <label class="field">
            <span>Цена за дочернего лидера в месяц, руб.</span>
            <input type="number" step="0.01" name="leader_price_per_leader" value="<?= h($baseLeaderPrice) ?>">
        </label>
        <label class="field">
            <span>Цена за консультанта в месяц, руб.</span>
            <input type="number" step="0.01" name="leader_price_per_consultant" value="<?= h($baseConsultantPrice) ?>">
        </label>
        <label class="field wide">
            <span>Короткие условия оплаты</span>
            <textarea name="leader_payment_terms" rows="3"><?= h($paymentTerms) ?></textarea>
        </label>
        <div class="form-actions"><button type="submit">Сохранить тарифы</button></div>
    </form>
</section>

<section class="panel form-panel">
    <h2>Активировать или продлить лидера</h2>
    <p class="cell-muted"><?= h($paymentTerms) ?></p>
    <form method="post" class="crud-form">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_subscription">
        <label class="field">
            <span>Лидер *</span>
            <select name="reseller_id" required>
                <option value="">Выберите</option>
                <?php foreach ($leaderOptions as $leader): ?>
                    <option value="<?= (int)$leader['id'] ?>"><?= h($leader['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field">
            <span>Расчёт суммы *</span>
            <select name="billing_basis">
                <?php foreach (subscription_billing_basis_labels() as $value => $label): ?>
                    <option value="<?= h($value) ?>"><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field"><span>Лимит прямых лидеров</span><input type="number" min="0" name="direct_leader_limit" placeholder="без лимита"></label>
        <label class="field"><span>Лимит лидеров во всей ветке</span><input type="number" min="0" name="branch_leader_limit" placeholder="без лимита"></label>
        <label class="field"><span>Лимит прямых консультантов</span><input type="number" min="0" name="direct_consultant_limit" placeholder="без лимита"></label>
        <label class="field"><span>Лимит консультантов во всей ветке</span><input type="number" min="0" name="branch_consultant_limit" placeholder="без лимита"></label>
        <label class="field"><span>Консультантов на дочернего лидера</span><input type="number" min="0" name="per_child_consultant_limit" placeholder="без лимита"></label>
        <label class="field"><span>Цена за лидера, руб. *</span><input type="number" step="0.01" min="0.01" name="price_per_leader" value="<?= h($baseLeaderPrice) ?>" required></label>
        <label class="field"><span>Цена за консультанта, руб. *</span><input type="number" step="0.01" min="0.01" name="price_per_consultant" value="<?= h($baseConsultantPrice) ?>" required></label>
        <div class="field">
            <span>Расчёт за месяц</span>
            <strong id="subscription-amount-preview">—</strong>
        </div>
        <label class="field">
            <span>Статус *</span>
            <select name="status">
                <?php foreach (subscription_status_labels() as $status => $label): ?>
                    <option value="<?= h($status) ?>" <?= $status === 'active' ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field"><span>Начало</span><input type="datetime-local" name="starts_at" value="<?= h(date('Y-m-d\TH:i')) ?>"></label>
        <label class="field"><span>Окончание *</span><input type="datetime-local" name="ends_at"></label>
        <label class="field"><span>Дата оплаты</span><input type="datetime-local" name="paid_at"></label>
        <label class="field"><span>Номер счёта / документа</span><input name="invoice_number"></label>
        <label class="field"><span>Способ оплаты</span><input name="payment_method" placeholder="Перевод, счёт, наличные"></label>
        <label class="field wide"><span>Примечание об оплате</span><textarea name="payment_note" rows="3"></textarea></label>
        <div class="form-actions"><button type="submit">Сохранить подписку</button></div>
    </form>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.querySelector('form [name="billing_basis"]')?.closest('form');
            const preview = document.querySelector('#subscription-amount-preview');
            if (!form || !preview) return;
            const money = (value) => new Intl.NumberFormat('ru-RU', {style: 'currency', currency: 'RUB'}).format(value);
            const numberValue = (name) => Number(String(form.elements[name]?.value || '0').replace(',', '.')) || 0;
            const updatePreview = () => {
                const basis = form.elements.billing_basis?.value || 'branch';
                const leaderLimit = numberValue(basis === 'direct' ? 'direct_leader_limit' : 'branch_leader_limit');
                const consultantLimit = numberValue(basis === 'direct' ? 'direct_consultant_limit' : 'branch_consultant_limit');
                const amount = leaderLimit * numberValue('price_per_leader') + consultantLimit * numberValue('price_per_consultant');
                preview.textContent = amount > 0 ? money(amount) : '—';
            };
            ['billing_basis', 'direct_leader_limit', 'branch_leader_limit', 'direct_consultant_limit', 'branch_consultant_limit', 'price_per_leader', 'price_per_consultant']
                .forEach((name) => form.elements[name]?.addEventListener('input', updatePreview));
            updatePreview();
        });
    </script>
</section>

<section class="panel">
    <h2>Сводка по лидерам</h2>
    <table class="data-table">
        <thead>
        <tr>
            <th>Лидер</th>
            <th>Текущая ветка</th>
            <th>Выданные лимиты</th>
            <th>Последняя подписка</th>
            <th>Сумма</th>
            <th>Плательщик</th>
            <th>Действия</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($leaderRows as $item): ?>
            <?php
            $status = (string)($item['subscription_status'] ?? '');
            $statusLabel = $status !== '' ? (subscription_status_labels()[$status] ?? $status) : 'Нет подписки';
            $basis = (string)($item['billing_basis'] ?? 'branch');
            $amount = $item['amount_due'] !== null ? (float)$item['amount_due'] : null;
            $billing = trim((string)($item['billing_name'] ?? ''))
                ?: trim((string)($item['billing_email'] ?? ''))
                ?: '—';
            $team = $item['team_summary'];
            ?>
            <tr>
                <td>
                    <strong><?= h((string)$item['name']) ?></strong><br>
                    <span class="cell-muted"><?= h((string)($item['email'] ?: $item['phone'] ?: '—')) ?></span>
                    <?php if (!empty($item['parent_name'])): ?>
                        <br><span class="cell-muted">Над ним: <?= h((string)$item['parent_name']) ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    Лидеры: <?= (int)$team['direct_leaders'] ?> прямых / <?= (int)$team['branch_leaders'] ?> в ветке<br>
                    Консультанты: <?= (int)$team['direct_consultants'] ?> прямых / <?= (int)$team['branch_consultants'] ?> в ветке<br>
                    Клиенты ветки: <?= (int)$team['branch_clients'] ?>
                </td>
                <td>
                    Прямые лидеры: <?= h(subscription_limit_cell((int)$team['direct_leaders'], $item['direct_leader_limit'] !== null ? (int)$item['direct_leader_limit'] : null)) ?><br>
                    Лидеры ветки: <?= h(subscription_limit_cell((int)$team['branch_leaders'], $item['branch_leader_limit'] !== null ? (int)$item['branch_leader_limit'] : null)) ?><br>
                    Прямые консультанты: <?= h(subscription_limit_cell((int)$team['direct_consultants'], $item['direct_manager_limit'] !== null ? (int)$item['direct_manager_limit'] : null)) ?><br>
                    Консультанты ветки: <?= h(subscription_limit_cell((int)$team['branch_consultants'], $item['branch_manager_limit'] !== null ? (int)$item['branch_manager_limit'] : null)) ?>
                    <?php if ($item['per_child_manager_limit'] !== null): ?>
                        <br>На дочернего лидера: <?= (int)$item['per_child_manager_limit'] ?>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="<?= h(status_badge_class($statusLabel)) ?>"><?= h($statusLabel) ?></span><br>
                    <span class="cell-muted"><?= h(subscription_period_text($item)) ?></span><br>
                    <span class="cell-muted">Расчёт: <?= h(subscription_billing_basis_labels()[$basis] ?? $basis) ?></span>
                    <?php if (!empty($item['paid_at'])): ?>
                        <br><span class="cell-muted">Оплачено: <?= h((string)$item['paid_at']) ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?= h(money_text($amount)) ?><br>
                    <span class="cell-muted">
                        <?= $item['price_per_leader'] !== null ? h(money_text((float)$item['price_per_leader'])) . ' за лидера' : 'цена лидера не задана' ?>
                    </span><br>
                    <span class="cell-muted">
                        <?= $item['price_per_consultant'] !== null ? h(money_text((float)$item['price_per_consultant'])) . ' за консультанта' : 'цена консультанта не задана' ?>
                    </span>
                </td>
                <td>
                    <?= h($billing) ?>
                    <?php if (!empty($item['billing_inn'])): ?>
                        <br><span class="cell-muted">ИНН <?= h((string)$item['billing_inn']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($item['invoice_number'])): ?>
                        <br><span class="cell-muted">Документ: <?= h((string)$item['invoice_number']) ?></span>
                    <?php endif; ?>
                </td>
                <td class="row-actions">
                    <a class="link-button" href="crud.php?module=resellers&action=edit&id=<?= (int)$item['id'] ?>">Карточка лидера</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php require __DIR__ . '/../app/views/layouts/footer.php'; ?>
