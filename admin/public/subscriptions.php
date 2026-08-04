<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/permissions.php';
require_once __DIR__ . '/../app/core/subscription_plans.php';
require_once __DIR__ . '/../app/core/table_ui.php';

$admin = require_auth();
if ($admin['role'] !== 'superadmin') {
    http_response_code(403);
    exit('Access denied');
}

$title = 'Подписка';
$errors = [];
$success = $_GET['success'] ?? null;
$action = (string)($_GET['action'] ?? 'list');
$id = max(0, (int)($_GET['id'] ?? 0));

function subscription_plan_defaults(): array
{
    return [
        'id' => 0,
        'title' => '',
        'slug' => '',
        'description' => '',
        'billing_basis' => 'branch',
        'direct_leader_limit' => null,
        'branch_leader_limit' => null,
        'direct_consultant_limit' => null,
        'branch_consultant_limit' => null,
        'per_child_consultant_limit' => null,
        'price_per_leader' => null,
        'price_per_consultant' => null,
        'fixed_monthly_price' => null,
        'payment_terms' => 'Оплата подтверждается администратором вручную. Онлайн-касса на первом этапе не подключена.',
        'sort_order' => 100,
        'is_active' => 1,
    ];
}

function subscription_plan_payload_from_post(array $source): array
{
    $payload = subscription_plan_defaults();
    $payload['id'] = max(0, (int)($source['id'] ?? 0));
    $payload['title'] = trim((string)($source['title'] ?? ''));
    $payload['slug'] = trim((string)($source['slug'] ?? ''));
    $payload['description'] = trim((string)($source['description'] ?? ''));
    $payload['billing_basis'] = (string)($source['billing_basis'] ?? 'branch');
    $payload['payment_terms'] = trim((string)($source['payment_terms'] ?? ''));
    $payload['sort_order'] = (int)($source['sort_order'] ?? 100);
    $payload['is_active'] = isset($source['is_active']) ? 1 : 0;

    foreach (subscription_plan_limit_fields() as $field) {
        $payload[$field] = subscription_parse_limit($source[$field] ?? '');
    }
    foreach (subscription_plan_price_fields() as $field) {
        $payload[$field] = subscription_parse_money($source[$field] ?? '');
    }

    return $payload;
}

function subscription_plan_validate(array &$payload): array
{
    $errors = [];
    if ($payload['title'] === '') {
        $errors[] = 'Укажите название подписки.';
    }
    $payload['slug'] = $payload['slug'] === ''
        ? subscription_plan_slug($payload['title'])
        : subscription_plan_slug($payload['slug']);

    if (!isset(subscription_billing_basis_labels()[$payload['billing_basis']])) {
        $errors[] = 'Выберите корректный способ расчёта.';
    }

    foreach (subscription_plan_limit_fields() as $field) {
        if ($payload[$field] !== null && $payload[$field] < 0) {
            $errors[] = 'Лимиты должны быть целыми числами или пустыми.';
            break;
        }
    }

    foreach (subscription_plan_price_fields() as $field) {
        if ($payload[$field] !== null && $payload[$field] < 0) {
            $errors[] = 'Стоимость не может быть отрицательной.';
            break;
        }
    }

    if ($payload['direct_leader_limit'] !== null
        && $payload['branch_leader_limit'] !== null
        && $payload['direct_leader_limit'] > $payload['branch_leader_limit']
    ) {
        $errors[] = 'Лимит прямых лидеров не может быть больше лимита лидеров во всей ветке.';
    }
    if ($payload['direct_consultant_limit'] !== null
        && $payload['branch_consultant_limit'] !== null
        && $payload['direct_consultant_limit'] > $payload['branch_consultant_limit']
    ) {
        $errors[] = 'Лимит прямых консультантов не может быть больше лимита консультантов во всей ветке.';
    }

    if ($payload['slug'] !== '') {
        $sql = 'SELECT id FROM subscription_plans WHERE slug = :slug';
        $params = ['slug' => $payload['slug']];
        if ((int)$payload['id'] > 0) {
            $sql .= ' AND id <> :id';
            $params['id'] = (int)$payload['id'];
        }
        $stmt = db()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        if ($stmt->fetchColumn()) {
            $errors[] = 'Такой код подписки уже используется.';
        }
    }

    return array_values(array_unique($errors));
}

function subscription_plan_save(array $payload, array $admin): int
{
    $params = [
        'slug' => $payload['slug'],
        'title' => $payload['title'],
        'description' => $payload['description'] !== '' ? $payload['description'] : null,
        'billing_basis' => $payload['billing_basis'],
        'direct_leader_limit' => $payload['direct_leader_limit'],
        'branch_leader_limit' => $payload['branch_leader_limit'],
        'direct_consultant_limit' => $payload['direct_consultant_limit'],
        'branch_consultant_limit' => $payload['branch_consultant_limit'],
        'per_child_consultant_limit' => $payload['per_child_consultant_limit'],
        'price_per_leader' => $payload['price_per_leader'],
        'price_per_consultant' => $payload['price_per_consultant'],
        'fixed_monthly_price' => $payload['fixed_monthly_price'],
        'payment_terms' => $payload['payment_terms'] !== '' ? $payload['payment_terms'] : null,
        'sort_order' => (int)$payload['sort_order'],
        'is_active' => (int)$payload['is_active'],
    ];

    if ((int)$payload['id'] > 0) {
        $params['id'] = (int)$payload['id'];
        $stmt = db()->prepare(
            'UPDATE subscription_plans
             SET slug = :slug,
                 title = :title,
                 description = :description,
                 billing_basis = :billing_basis,
                 direct_leader_limit = :direct_leader_limit,
                 branch_leader_limit = :branch_leader_limit,
                 direct_consultant_limit = :direct_consultant_limit,
                 branch_consultant_limit = :branch_consultant_limit,
                 per_child_consultant_limit = :per_child_consultant_limit,
                 price_per_leader = :price_per_leader,
                 price_per_consultant = :price_per_consultant,
                 fixed_monthly_price = :fixed_monthly_price,
                 payment_terms = :payment_terms,
                 sort_order = :sort_order,
                 is_active = :is_active
             WHERE id = :id'
        );
        $stmt->execute($params);
        log_activity('admin', (int)$admin['id'], 'update_subscription_plan', 'subscription_plans', (int)$payload['id']);
        return (int)$payload['id'];
    }

    $stmt = db()->prepare(
        'INSERT INTO subscription_plans (
            slug, title, description, billing_basis,
            direct_leader_limit, branch_leader_limit,
            direct_consultant_limit, branch_consultant_limit, per_child_consultant_limit,
            price_per_leader, price_per_consultant, fixed_monthly_price,
            payment_terms, sort_order, is_active
         ) VALUES (
            :slug, :title, :description, :billing_basis,
            :direct_leader_limit, :branch_leader_limit,
            :direct_consultant_limit, :branch_consultant_limit, :per_child_consultant_limit,
            :price_per_leader, :price_per_consultant, :fixed_monthly_price,
            :payment_terms, :sort_order, :is_active
         )'
    );
    $stmt->execute($params);
    $newId = (int)db()->lastInsertId();
    log_activity('admin', (int)$admin['id'], 'create_subscription_plan', 'subscription_plans', $newId);

    return $newId;
}

function subscription_plan_assigned_count(int $planId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM resellers WHERE subscription_plan_id = :id');
    $stmt->execute(['id' => $planId]);

    return (int)$stmt->fetchColumn();
}

function subscription_plan_list_url(array $overrides = []): string
{
    return admin_table_url($overrides, 'subscriptions.php');
}

function subscription_plan_list_data(): array
{
    $sortMap = [
        'id' => '`id`',
        'title' => '`title`',
        'billing_basis' => '`billing_basis`',
        'fixed_monthly_price' => '`fixed_monthly_price`',
        'sort_order' => '`sort_order`',
        'is_active' => '`is_active`',
        'leaders_count' => '`leaders_count`',
    ];

    return admin_table_paginated_rows(
        'SELECT sp.*, COALESCE(assigned.leaders_count, 0) AS leaders_count
         FROM subscription_plans sp
         LEFT JOIN (
            SELECT subscription_plan_id, COUNT(*) AS leaders_count
            FROM resellers
            WHERE subscription_plan_id IS NOT NULL
            GROUP BY subscription_plan_id
         ) assigned ON assigned.subscription_plan_id = sp.id',
        [],
        $sortMap,
        ['title', 'slug', 'description', 'payment_terms', 'billing_basis'],
        'sort_order',
        'asc'
    );
}

function subscription_plan_sort_link(string $key, string $label, array $meta): string
{
    return render_admin_sort_link($key, $label, $meta, [
        'id' => '`id`',
        'title' => '`title`',
        'billing_basis' => '`billing_basis`',
        'fixed_monthly_price' => '`fixed_monthly_price`',
        'sort_order' => '`sort_order`',
        'is_active' => '`is_active`',
        'leaders_count' => '`leaders_count`',
    ], 'subscriptions.php');
}

function subscription_plan_limits_html(array $plan): string
{
    $items = [
        'Прямые лидеры' => $plan['direct_leader_limit'] !== null ? (int)$plan['direct_leader_limit'] : null,
        'Лидеры ветки' => $plan['branch_leader_limit'] !== null ? (int)$plan['branch_leader_limit'] : null,
        'Прямые консультанты' => $plan['direct_consultant_limit'] !== null ? (int)$plan['direct_consultant_limit'] : null,
        'Консультанты ветки' => $plan['branch_consultant_limit'] !== null ? (int)$plan['branch_consultant_limit'] : null,
        'На дочернего лидера' => $plan['per_child_consultant_limit'] !== null ? (int)$plan['per_child_consultant_limit'] : null,
    ];

    ob_start();
    ?>
    <div class="compact-lines">
        <?php foreach ($items as $label => $value): ?>
            <span><strong><?= h($label) ?>:</strong> <?= h(subscription_limit_text($value)) ?></span>
        <?php endforeach; ?>
    </div>
    <?php
    return trim(ob_get_clean());
}

function subscription_plan_prices_html(array $plan): string
{
    $amount = subscription_plan_amount($plan);
    ob_start();
    ?>
    <div class="compact-lines">
        <span><strong>Итого:</strong> <?= h(subscription_money_text($amount)) ?></span>
        <span><strong>Расчёт:</strong> <?= h(subscription_billing_basis_labels()[$plan['billing_basis'] ?? 'branch'] ?? 'Вся ветка') ?></span>
        <span><strong>Лидер:</strong> <?= h(subscription_money_text($plan['price_per_leader'] !== null ? (float)$plan['price_per_leader'] : null)) ?></span>
        <span><strong>Консультант:</strong> <?= h(subscription_money_text($plan['price_per_consultant'] !== null ? (float)$plan['price_per_consultant'] : null)) ?></span>
        <?php if ($plan['fixed_monthly_price'] !== null): ?>
            <span><strong>Фиксировано:</strong> <?= h(subscription_money_text((float)$plan['fixed_monthly_price'])) ?></span>
        <?php endif; ?>
    </div>
    <?php
    return trim(ob_get_clean());
}

function subscription_plan_status_class(bool $isActive): string
{
    return $isActive ? 'badge badge-sent' : 'badge badge-failed';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $postAction = (string)($_POST['action'] ?? 'save_plan');

    if ($postAction === 'save_plan') {
        $plan = subscription_plan_payload_from_post($_POST);
        $errors = subscription_plan_validate($plan);
        if (!$errors) {
            try {
                $savedId = subscription_plan_save($plan, $admin);
                redirect('subscriptions.php?action=edit&id=' . $savedId . '&success=saved');
            } catch (Throwable $e) {
                $errors[] = 'Не удалось сохранить подписку: ' . $e->getMessage();
            }
        }
        $action = (int)$plan['id'] > 0 ? 'edit' : 'create';
        $id = (int)$plan['id'];
        $editPlan = $plan;
    } elseif ($postAction === 'toggle_plan') {
        $planId = (int)($_POST['id'] ?? 0);
        $stmt = db()->prepare('UPDATE subscription_plans SET is_active = IF(is_active = 1, 0, 1) WHERE id = :id');
        $stmt->execute(['id' => $planId]);
        log_activity('admin', (int)$admin['id'], 'toggle_subscription_plan', 'subscription_plans', $planId);
        redirect('subscriptions.php?success=toggled');
    } elseif ($postAction === 'delete_plan') {
        $planId = (int)($_POST['id'] ?? 0);
        if ($planId <= 0) {
            $errors[] = 'Подписка не найдена.';
        } elseif (subscription_plan_assigned_count($planId) > 0) {
            $errors[] = 'Нельзя удалить подписку, которая назначена лидерам. Отключите её, чтобы больше не выбирать.';
        } else {
            $stmt = db()->prepare('DELETE FROM subscription_plans WHERE id = :id');
            $stmt->execute(['id' => $planId]);
            log_activity('admin', (int)$admin['id'], 'delete_subscription_plan', 'subscription_plans', $planId);
            redirect('subscriptions.php?success=deleted');
        }
    }
}

if (!isset($editPlan)) {
    $editPlan = subscription_plan_defaults();
    if ($action === 'edit' && $id > 0) {
        $row = subscription_plan_row($id, false);
        if ($row) {
            $editPlan = array_merge($editPlan, $row);
        } else {
            $errors[] = 'Подписка не найдена.';
            $action = 'list';
        }
    }
}

$listData = subscription_plan_list_data();
$rows = $listData['rows'];
$meta = $listData['meta'];
$assignedTotal = (int)db()->query('SELECT COUNT(*) FROM resellers WHERE subscription_plan_id IS NOT NULL')->fetchColumn();
$activeTotal = (int)db()->query('SELECT COUNT(*) FROM subscription_plans WHERE is_active = 1')->fetchColumn();

require __DIR__ . '/../app/views/layouts/header.php';
?>
<div class="toolbar">
    <h1>Подписка</h1>
    <?php if ($action === 'list'): ?>
        <a class="button" href="subscriptions.php?action=create">Добавить</a>
    <?php else: ?>
        <a class="button secondary-button" href="subscriptions.php">К списку подписок</a>
    <?php endif; ?>
</div>

<?php if ($success === 'saved'): ?><div class="notice success">Подписка сохранена.</div><?php endif; ?>
<?php if ($success === 'toggled'): ?><div class="notice success">Статус подписки изменён.</div><?php endif; ?>
<?php if ($success === 'deleted'): ?><div class="notice success">Подписка удалена.</div><?php endif; ?>
<?php foreach ($errors as $error): ?><div class="alert"><?= h($error) ?></div><?php endforeach; ?>

<section class="grid stats-grid">
    <article class="stat"><span>Всего подписок</span><strong><?= (int)$meta['total'] ?></strong></article>
    <article class="stat"><span>Активных</span><strong><?= $activeTotal ?></strong></article>
    <article class="stat"><span>Назначено лидерам</span><strong><?= $assignedTotal ?></strong></article>
</section>

<?php if ($action === 'create' || $action === 'edit'): ?>
    <section class="panel form-panel">
        <h2><?= $action === 'edit' ? 'Редактировать подписку' : 'Добавить подписку' ?></h2>
        <p class="cell-muted">Подписка хранит лимиты, цены и условия. В карточке лидера выбирается только один готовый вариант.</p>
        <form method="post" class="crud-form">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_plan">
            <input type="hidden" name="id" value="<?= (int)($editPlan['id'] ?? 0) ?>">
            <label class="field"><span>Название *</span><input name="title" value="<?= h((string)($editPlan['title'] ?? '')) ?>" required></label>
            <label class="field">
                <span>Код</span>
                <input name="slug" value="<?= h((string)($editPlan['slug'] ?? '')) ?>" placeholder="zapolnitsya-avtomaticheski">
                <small class="field-hint">Можно оставить пустым: код будет создан из названия.</small>
            </label>
            <label class="field">
                <span>Расчёт суммы</span>
                <select name="billing_basis">
                    <?php foreach (subscription_billing_basis_labels() as $value => $label): ?>
                        <option value="<?= h($value) ?>" <?= ($editPlan['billing_basis'] ?? 'branch') === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field"><span>Сортировка</span><input type="number" name="sort_order" value="<?= h((string)($editPlan['sort_order'] ?? 100)) ?>"></label>
            <label class="field wide"><span>Описание</span><textarea name="description" rows="3"><?= h((string)($editPlan['description'] ?? '')) ?></textarea></label>

            <label class="field"><span>Лимит прямых лидеров</span><input type="number" min="0" name="direct_leader_limit" value="<?= h((string)($editPlan['direct_leader_limit'] ?? '')) ?>" placeholder="без лимита"></label>
            <label class="field"><span>Лимит лидеров во всей ветке</span><input type="number" min="0" name="branch_leader_limit" value="<?= h((string)($editPlan['branch_leader_limit'] ?? '')) ?>" placeholder="без лимита"></label>
            <label class="field"><span>Лимит прямых консультантов</span><input type="number" min="0" name="direct_consultant_limit" value="<?= h((string)($editPlan['direct_consultant_limit'] ?? '')) ?>" placeholder="без лимита"></label>
            <label class="field"><span>Лимит консультантов во всей ветке</span><input type="number" min="0" name="branch_consultant_limit" value="<?= h((string)($editPlan['branch_consultant_limit'] ?? '')) ?>" placeholder="без лимита"></label>
            <label class="field"><span>Консультантов на дочернего лидера</span><input type="number" min="0" name="per_child_consultant_limit" value="<?= h((string)($editPlan['per_child_consultant_limit'] ?? '')) ?>" placeholder="без лимита"></label>

            <label class="field"><span>Цена за лидера в месяц</span><input type="number" step="0.01" min="0" name="price_per_leader" value="<?= h((string)($editPlan['price_per_leader'] ?? '')) ?>" placeholder="0,00"></label>
            <label class="field"><span>Цена за консультанта в месяц</span><input type="number" step="0.01" min="0" name="price_per_consultant" value="<?= h((string)($editPlan['price_per_consultant'] ?? '')) ?>" placeholder="0,00"></label>
            <label class="field"><span>Фиксированная цена в месяц</span><input type="number" step="0.01" min="0" name="fixed_monthly_price" value="<?= h((string)($editPlan['fixed_monthly_price'] ?? '')) ?>" placeholder="если нужен фикс"></label>
            <div class="field"><span>Расчёт по подписке</span><strong id="subscription-plan-preview">—</strong></div>
            <label class="field wide"><span>Условия</span><textarea name="payment_terms" rows="4"><?= h((string)($editPlan['payment_terms'] ?? '')) ?></textarea></label>
            <label class="check-row"><input type="checkbox" name="is_active" value="1" <?= (int)($editPlan['is_active'] ?? 1) === 1 ? 'checked' : '' ?>><span>Подписка активна и доступна для выбора</span></label>
            <div class="form-actions">
                <button type="submit">Сохранить</button>
                <a class="button secondary-button" href="subscriptions.php">Отмена</a>
            </div>
        </form>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const preview = document.getElementById('subscription-plan-preview');
                const form = preview?.closest('form');
                if (!form || !preview) return;
                const numberValue = (name) => Number(String(form.elements[name]?.value || '0').replace(',', '.')) || 0;
                const money = (value) => new Intl.NumberFormat('ru-RU', {style: 'currency', currency: 'RUB'}).format(value);
                const updatePreview = () => {
                    const fixed = numberValue('fixed_monthly_price');
                    if (fixed > 0) {
                        preview.textContent = money(fixed);
                        return;
                    }
                    const basis = form.elements.billing_basis?.value || 'branch';
                    const leaders = numberValue(basis === 'direct' ? 'direct_leader_limit' : 'branch_leader_limit');
                    const consultants = numberValue(basis === 'direct' ? 'direct_consultant_limit' : 'branch_consultant_limit');
                    const total = leaders * numberValue('price_per_leader') + consultants * numberValue('price_per_consultant');
                    preview.textContent = total > 0 ? money(total) : '—';
                };
                form.querySelectorAll('input, select').forEach((control) => {
                    control.addEventListener('input', updatePreview);
                    control.addEventListener('change', updatePreview);
                });
                updatePreview();
            });
        </script>
    </section>
<?php else: ?>
    <section class="panel">
        <?= render_admin_table_tools($meta, [], [
            'reset_url' => 'subscriptions.php',
            'search_placeholder' => 'Название, код, условия',
        ]) ?>
        <div class="table-summary">Найдено записей: <?= (int)$meta['total'] ?></div>
        <?php if ($rows): ?>
            <table class="data-table responsive-table" data-module="subscriptions">
                <thead>
                <tr>
                    <th><?= subscription_plan_sort_link('id', 'ID', $meta) ?></th>
                    <th><?= subscription_plan_sort_link('title', 'Подписка', $meta) ?></th>
                    <th>Условия</th>
                    <th>Лимиты</th>
                    <th><?= subscription_plan_sort_link('fixed_monthly_price', 'Стоимость', $meta) ?></th>
                    <th><?= subscription_plan_sort_link('leaders_count', 'Лидеры', $meta) ?></th>
                    <th><?= subscription_plan_sort_link('is_active', 'Статус', $meta) ?></th>
                    <th>Действия</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php $isActive = (int)($row['is_active'] ?? 0) === 1; ?>
                    <tr>
                        <td data-label="ID" data-column="id"><?= (int)$row['id'] ?></td>
                        <td data-label="Подписка" data-column="title"><strong><?= h((string)$row['title']) ?></strong><br><span class="cell-muted"><?= h((string)$row['slug']) ?></span></td>
                        <td data-label="Условия" data-column="description">
                            <?= h((string)($row['description'] ?: '—')) ?>
                            <?php if (!empty($row['payment_terms'])): ?><br><span class="cell-muted"><?= h((string)$row['payment_terms']) ?></span><?php endif; ?>
                        </td>
                        <td data-label="Лимиты" data-column="limits"><?= subscription_plan_limits_html($row) ?></td>
                        <td data-label="Стоимость" data-column="price"><?= subscription_plan_prices_html($row) ?></td>
                        <td data-label="Лидеры" data-column="leaders_count"><?= (int)$row['leaders_count'] ?></td>
                        <td data-label="Статус" data-column="is_active"><span class="<?= h(subscription_plan_status_class($isActive)) ?>"><?= $isActive ? 'Активна' : 'Отключена' ?></span></td>
                        <td data-label="Действия" data-column="actions" class="row-actions">
                            <a class="link-button" href="subscriptions.php?action=edit&id=<?= (int)$row['id'] ?>">Редактировать</a>
                            <form method="post" class="inline-form">
                                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                <input type="hidden" name="action" value="toggle_plan">
                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                <button type="submit" class="link-button"><?= $isActive ? 'Отключить' : 'Включить' ?></button>
                            </form>
                            <form method="post" class="inline-form" onsubmit="return confirm('Удалить подписку?');">
                                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete_plan">
                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                <button type="submit" class="link-button danger">Удалить</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?= render_admin_pagination($meta, 'subscriptions.php') ?>
        <?php else: ?>
            <div class="empty-state">Подписки не найдены.</div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/../app/views/layouts/footer.php'; ?>
