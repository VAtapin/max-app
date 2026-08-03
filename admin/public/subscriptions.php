<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/permissions.php';

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

function subscription_period_text(array $item): string
{
    $startsAt = trim((string)($item['subscription_starts_at'] ?? $item['starts_at'] ?? ''));
    $endsAt = trim((string)($item['subscription_ends_at'] ?? $item['ends_at'] ?? ''));
    if ($startsAt === '' && $endsAt === '') {
        return '—';
    }

    return ($startsAt !== '' ? $startsAt : 'без даты') . ' - ' . ($endsAt !== '' ? $endsAt : 'без окончания');
}

$basePrice = setting_value('leader_price_per_consultant', '300');
$paymentTerms = setting_value(
    'leader_payment_terms',
    'Оплата подтверждается администратором вручную. Онлайн-касса на первом этапе не подключена.'
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $postAction = (string)($_POST['action'] ?? 'save_subscription');

    if ($postAction === 'save_billing_settings') {
        $price = str_replace(',', '.', trim((string)($_POST['leader_price_per_consultant'] ?? '')));
        $terms = trim((string)($_POST['leader_payment_terms'] ?? ''));
        if ($price === '' || !is_numeric($price) || (float)$price <= 0) {
            $errors[] = 'Укажите цену за консультанта больше нуля.';
        }
        if ($terms === '') {
            $errors[] = 'Укажите короткие условия оплаты.';
        }

        if (!$errors) {
            save_setting('leader_price_per_consultant', number_format((float)$price, 2, '.', ''), 'Базовая ежемесячная стоимость одного консультанта в команде лидера');
            save_setting('leader_payment_terms', $terms, 'Короткая подсказка для бухгалтерской панели лидеров');
            log_activity('admin', (int)$admin['id'], 'update_leader_billing_settings', 'settings');
            redirect('subscriptions.php?success=settings_saved');
        }
    }

    if ($postAction === 'save_subscription') {
        $resellerId = (int)($_POST['reseller_id'] ?? 0);
        $status = (string)($_POST['status'] ?? 'pending');
        $startsAt = trim((string)($_POST['starts_at'] ?? ''));
        $endsAt = trim((string)($_POST['ends_at'] ?? ''));
        $paidAt = trim((string)($_POST['paid_at'] ?? ''));
        $invoiceNumber = trim((string)($_POST['invoice_number'] ?? ''));
        $paymentMethod = trim((string)($_POST['payment_method'] ?? ''));
        $note = trim((string)($_POST['payment_note'] ?? ''));
        $consultantLimit = trim((string)($_POST['consultant_limit'] ?? ''));
        $pricePerConsultant = str_replace(',', '.', trim((string)($_POST['price_per_consultant'] ?? $basePrice)));

        if ($resellerId <= 0) {
            $errors[] = 'Выберите лидера.';
        }
        if (!isset(subscription_status_labels()[$status])) {
            $errors[] = 'Некорректный статус.';
        }
        if ($consultantLimit === '' || (int)$consultantLimit <= 0) {
            $errors[] = 'Укажите лимит консультантов для этого периода.';
        }
        if ($pricePerConsultant === '' || !is_numeric($pricePerConsultant) || (float)$pricePerConsultant <= 0) {
            $errors[] = 'Укажите цену за консультанта больше нуля.';
        }
        if ($status === 'active' && $endsAt === '') {
            $errors[] = 'Для активной подписки укажите дату окончания.';
        }

        if ($resellerId > 0 && $consultantLimit !== '' && (int)$consultantLimit > 0) {
            $activeManagers = db()->prepare('SELECT COUNT(*) FROM managers WHERE reseller_id = :reseller_id AND is_active = 1');
            $activeManagers->execute(['reseller_id' => $resellerId]);
            $activeCount = (int)$activeManagers->fetchColumn();
            if ($activeCount > (int)$consultantLimit) {
                $errors[] = 'Лимит меньше текущего количества активных консультантов: ' . $activeCount . '.';
            }
        }

        if (!$errors) {
            $limit = (int)$consultantLimit;
            $price = (float)$pricePerConsultant;
            $amount = $limit * $price;

            db()->beginTransaction();
            try {
                $stmt = db()->prepare(
                    'INSERT INTO leader_subscriptions (
                        reseller_id, consultant_limit, price_per_consultant, amount_due,
                        status, starts_at, ends_at, monthly_price, paid_at,
                        invoice_number, payment_method, payment_note, activated_by
                     ) VALUES (
                        :reseller_id, :consultant_limit, :price_per_consultant, :amount_due,
                        :status, :starts_at, :ends_at, :monthly_price, :paid_at,
                        :invoice_number, :payment_method, :payment_note, :activated_by
                     )'
                );
                $stmt->execute([
                    'reseller_id' => $resellerId,
                    'consultant_limit' => $limit,
                    'price_per_consultant' => $price,
                    'amount_due' => $amount,
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

                $updateLimit = db()->prepare('UPDATE resellers SET manager_limit = :manager_limit WHERE id = :id');
                $updateLimit->execute(['manager_limit' => $limit, 'id' => $resellerId]);

                log_activity('admin', (int)$admin['id'], 'create_leader_subscription', 'leader_subscriptions', $subscriptionId, [
                    'reseller_id' => $resellerId,
                    'consultant_limit' => $limit,
                    'price_per_consultant' => $price,
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

$leaders = db()->query('SELECT id, name, manager_limit FROM resellers WHERE is_active = 1 ORDER BY name')->fetchAll();
$leaderRows = db()->query(
    'SELECT r.id, r.name, r.email, r.phone, r.billing_name, r.billing_inn, r.billing_email,
            r.manager_limit, r.is_active,
            (SELECT COUNT(*) FROM managers m WHERE m.reseller_id = r.id) AS managers_count,
            (SELECT COUNT(*) FROM managers m WHERE m.reseller_id = r.id AND m.is_active = 1) AS active_managers_count,
            (SELECT COUNT(*) FROM end_users eu WHERE eu.reseller_id = r.id) AS users_count,
            ls.status AS subscription_status,
            ls.starts_at AS subscription_starts_at,
            ls.ends_at AS subscription_ends_at,
            ls.consultant_limit AS subscription_consultant_limit,
            ls.price_per_consultant,
            ls.amount_due,
            ls.paid_at,
            ls.invoice_number,
            ls.payment_method,
            ls.payment_note
     FROM resellers r
     LEFT JOIN (
        SELECT s.*
        FROM leader_subscriptions s
        INNER JOIN (
            SELECT reseller_id, MAX(id) AS latest_id
            FROM leader_subscriptions
            GROUP BY reseller_id
        ) latest ON latest.latest_id = s.id
     ) ls ON ls.reseller_id = r.id
     ORDER BY r.name ASC'
)->fetchAll();

$summary = [
    'leaders' => count($leaderRows),
    'active_subscriptions' => 0,
    'active_consultants' => 0,
    'consultant_limit' => 0,
    'monthly_revenue' => 0.0,
    'pending' => 0,
    'problem' => 0,
];
$now = time();
foreach ($leaderRows as $row) {
    $summary['active_consultants'] += (int)$row['active_managers_count'];
    $summary['consultant_limit'] += (int)($row['manager_limit'] ?? 0);
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

require __DIR__ . '/../app/views/layouts/header.php';
?>
<div class="toolbar"><h1>Оплата лидеров</h1></div>
<?php if ($success === 'saved'): ?><div class="notice success">Подписка сохранена, лимит лидера обновлён.</div><?php endif; ?>
<?php if ($success === 'settings_saved'): ?><div class="notice success">Параметры оплаты сохранены.</div><?php endif; ?>
<?php foreach ($errors as $error): ?><div class="alert"><?= h($error) ?></div><?php endforeach; ?>

<section class="grid stats-grid">
    <article class="stat"><span>Лидеров</span><strong><?= (int)$summary['leaders'] ?></strong></article>
    <article class="stat"><span>Активных подписок</span><strong><?= (int)$summary['active_subscriptions'] ?></strong></article>
    <article class="stat"><span>Активных консультантов</span><strong><?= (int)$summary['active_consultants'] ?></strong></article>
    <article class="stat"><span>Выданный лимит</span><strong><?= (int)$summary['consultant_limit'] ?></strong></article>
    <article class="stat"><span>План в месяц</span><strong><?= h(money_text((float)$summary['monthly_revenue'])) ?></strong></article>
    <article class="stat"><span>Ожидают/проблемы</span><strong><?= (int)($summary['pending'] + $summary['problem']) ?></strong></article>
</section>

<section class="panel form-panel">
    <h2>Тариф и правила оплаты</h2>
    <p class="cell-muted">Цена применяется как базовая при создании новой подписки. Итог считается как лимит консультантов умножить на цену за консультанта.</p>
    <form method="post" class="crud-form">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_billing_settings">
        <label class="field">
            <span>Цена за консультанта в месяц, руб.</span>
            <input type="number" step="0.01" name="leader_price_per_consultant" value="<?= h($basePrice) ?>">
        </label>
        <label class="field wide">
            <span>Короткие условия оплаты</span>
            <textarea name="leader_payment_terms" rows="3"><?= h($paymentTerms) ?></textarea>
        </label>
        <div class="form-actions"><button type="submit">Сохранить тариф</button></div>
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
                <?php foreach ($leaders as $leader): ?>
                    <option
                        value="<?= (int)$leader['id'] ?>"
                        data-limit="<?= h((string)($leader['manager_limit'] ?? '')) ?>"
                    >
                        <?= h((string)$leader['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field">
            <span>Лимит консультантов *</span>
            <input type="number" min="1" name="consultant_limit" value="1" required>
        </label>
        <label class="field">
            <span>Цена за консультанта, руб. *</span>
            <input type="number" step="0.01" min="0.01" name="price_per_consultant" value="<?= h($basePrice) ?>" required>
        </label>
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
            const leader = document.querySelector('[name="reseller_id"]');
            const limit = document.querySelector('[name="consultant_limit"]');
            const price = document.querySelector('[name="price_per_consultant"]');
            const preview = document.querySelector('#subscription-amount-preview');
            if (!limit || !price || !preview) return;
            const formatMoney = (value) => new Intl.NumberFormat('ru-RU', {
                style: 'currency',
                currency: 'RUB',
            }).format(value);
            const updatePreview = () => {
                const amount = Number(limit.value || 0) * Number(String(price.value || '0').replace(',', '.'));
                preview.textContent = amount > 0 ? formatMoney(amount) : '—';
            };
            leader?.addEventListener('change', () => {
                const savedLimit = leader.selectedOptions?.[0]?.dataset?.limit || '';
                if (savedLimit && Number(savedLimit) > 0) {
                    limit.value = savedLimit;
                }
                updatePreview();
            });
            limit.addEventListener('input', updatePreview);
            price.addEventListener('input', updatePreview);
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
            <th>Консультанты</th>
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
            $amount = $item['amount_due'] !== null ? (float)$item['amount_due'] : null;
            $billing = trim((string)($item['billing_name'] ?? ''))
                ?: trim((string)($item['billing_email'] ?? ''))
                ?: '—';
            ?>
            <tr>
                <td>
                    <strong><?= h((string)$item['name']) ?></strong><br>
                    <span class="cell-muted"><?= h((string)($item['email'] ?: $item['phone'] ?: '—')) ?></span>
                </td>
                <td>
                    Активных: <?= (int)$item['active_managers_count'] ?><br>
                    Всего: <?= (int)$item['managers_count'] ?><br>
                    Лимит: <?= $item['manager_limit'] !== null ? (int)$item['manager_limit'] : 'без ограничения' ?>
                </td>
                <td>
                    <span class="badge"><?= h($statusLabel) ?></span><br>
                    <span class="cell-muted"><?= h(subscription_period_text($item)) ?></span>
                    <?php if (!empty($item['paid_at'])): ?>
                        <br><span class="cell-muted">Оплачено: <?= h((string)$item['paid_at']) ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?= h(money_text($amount)) ?><br>
                    <span class="cell-muted">
                        <?= $item['price_per_consultant'] !== null ? h(money_text((float)$item['price_per_consultant'])) . ' за консультанта' : 'цена не задана' ?>
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
