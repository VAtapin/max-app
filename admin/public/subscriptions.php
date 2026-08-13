<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/permissions.php';
require_once __DIR__ . '/../app/core/subscription_plans.php';
require_once __DIR__ . '/../app/core/table_ui.php';

$admin = require_auth();
if (!can_manage('subscriptions', $admin)) {
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
        'owner_type' => subscription_plan_global_owner_type(),
        'owner_id' => subscription_plan_global_owner_id(),
        'title' => '',
        'slug' => '',
        'description' => '',
        'billing_mode' => 'prepaid',
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
        'payment_grace_days' => 5,
        'discount_6' => 2,
        'discount_12' => 5,
        'sort_order' => 100,
        'is_active' => 1,
    ];
}

function subscription_plan_payload_from_post(array $source, array $admin, ?array $existing = null): array
{
    $payload = array_merge(subscription_plan_defaults(), $existing ?? []);
    $payload['id'] = max(0, (int)($source['id'] ?? 0));
    if ($existing) {
        $payload['owner_type'] = (string)($existing['owner_type'] ?? subscription_plan_global_owner_type());
        $payload['owner_id'] = (int)($existing['owner_id'] ?? subscription_plan_global_owner_id());
    } else {
        $owner = subscription_plan_owner_for_admin($admin);
        $payload['owner_type'] = $owner['owner_type'];
        $payload['owner_id'] = $owner['owner_id'];
    }
    $payload['title'] = trim((string)($source['title'] ?? ''));
    $payload['slug'] = trim((string)($source['slug'] ?? ''));
    $payload['description'] = trim((string)($source['description'] ?? ''));
    $payload['billing_mode'] = (string)($source['billing_mode'] ?? 'prepaid');
    $payload['billing_basis'] = (string)($source['billing_basis'] ?? 'branch');
    $payload['payment_terms'] = trim((string)($source['payment_terms'] ?? ''));
    $payload['payment_grace_days'] = max(0, min(60, (int)($source['payment_grace_days'] ?? 5)));
    $payload['discount_6'] = max(0, min(100, (float)str_replace(',', '.', (string)($source['discount_6'] ?? 2))));
    $payload['discount_12'] = max(0, min(100, (float)str_replace(',', '.', (string)($source['discount_12'] ?? 5))));
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

function subscription_plan_limit_owner_errors(array $payload, array $admin): array
{
    if (($admin['role'] ?? '') !== 'reseller' || empty($admin['reseller_id'])) {
        return [];
    }

    $reseller = team_reseller_row((int)$admin['reseller_id']);
    if (!$reseller) {
        return ['Не удалось определить лимиты вашей текущей подписки.'];
    }

    $checks = [
        'direct_leader_limit' => ['Лимит лидеров 1-го уровня', 'direct_leader_limit'],
        'branch_leader_limit' => ['Лимит всех лидеров в ветке', 'branch_leader_limit'],
        'direct_consultant_limit' => ['Лимит консультантов 1-го уровня', 'direct_manager_limit'],
        'branch_consultant_limit' => ['Лимит всех консультантов в ветке', 'branch_manager_limit'],
        'per_child_consultant_limit' => ['Консультантов на дочернего лидера', 'per_child_manager_limit'],
    ];

    $errors = [];
    foreach ($checks as $field => [$label, $parentField]) {
        if ($payload[$field] === null || ($reseller[$parentField] ?? null) === null || $reseller[$parentField] === '') {
            continue;
        }

        $max = (int)$reseller[$parentField];
        if ((int)$payload[$field] > $max) {
            $errors[] = $label . ' не может быть больше вашего лимита: ' . $max . '.';
        }
    }

    return $errors;
}

function subscription_plan_validate(array &$payload, array $admin): array
{
    $errors = [];
    if ($payload['title'] === '') {
        $errors[] = 'Укажите название подписки.';
    }
    $payload['slug'] = $payload['slug'] === ''
        ? subscription_plan_slug($payload['title'])
        : subscription_plan_slug($payload['slug']);

    if (!isset(subscription_billing_mode_labels()[$payload['billing_mode']])) {
        $errors[] = 'Выберите корректный тип оплаты.';
    }
    if (!isset(subscription_billing_basis_labels()[$payload['billing_basis']])) {
        $errors[] = 'Выберите корректную базу расчёта.';
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
        $sql = 'SELECT id FROM subscription_plans WHERE slug = :slug AND owner_type = :owner_type AND owner_id = :owner_id';
        $params = [
            'slug' => $payload['slug'],
            'owner_type' => (string)($payload['owner_type'] ?? subscription_plan_global_owner_type()),
            'owner_id' => (int)($payload['owner_id'] ?? subscription_plan_global_owner_id()),
        ];
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

    $errors = array_merge($errors, subscription_plan_limit_owner_errors($payload, $admin));

    return array_values(array_unique($errors));
}

function subscription_plan_save(array $payload, array $admin): int
{
    $params = [
        'owner_type' => (string)($payload['owner_type'] ?? subscription_plan_global_owner_type()),
        'owner_id' => (int)($payload['owner_id'] ?? subscription_plan_global_owner_id()),
        'slug' => $payload['slug'],
        'title' => $payload['title'],
        'description' => $payload['description'] !== '' ? $payload['description'] : null,
        'billing_mode' => $payload['billing_mode'],
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
        'payment_grace_days' => (int)$payload['payment_grace_days'],
        'sort_order' => (int)$payload['sort_order'],
        'is_active' => (int)$payload['is_active'],
    ];

    if ((int)$payload['id'] > 0) {
        $params['id'] = (int)$payload['id'];
        $stmt = db()->prepare(
            'UPDATE subscription_plans
             SET owner_type = :owner_type,
                 owner_id = :owner_id,
                 slug = :slug,
                 title = :title,
                 description = :description,
                 billing_mode = :billing_mode,
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
                 payment_grace_days = :payment_grace_days,
                 sort_order = :sort_order,
                 is_active = :is_active
             WHERE id = :id'
        );
        $stmt->execute($params);
        subscription_plan_save_discounts((int)$payload['id'], $payload);
        log_activity('admin', (int)$admin['id'], 'update_subscription_plan', 'subscription_plans', (int)$payload['id']);
        return (int)$payload['id'];
    }

    $stmt = db()->prepare(
        'INSERT INTO subscription_plans (
            owner_type, owner_id, slug, title, description, billing_mode, billing_basis,
            direct_leader_limit, branch_leader_limit,
            direct_consultant_limit, branch_consultant_limit, per_child_consultant_limit,
            price_per_leader, price_per_consultant, fixed_monthly_price,
            payment_terms, payment_grace_days, sort_order, is_active
         ) VALUES (
            :owner_type, :owner_id, :slug, :title, :description, :billing_mode, :billing_basis,
            :direct_leader_limit, :branch_leader_limit,
            :direct_consultant_limit, :branch_consultant_limit, :per_child_consultant_limit,
            :price_per_leader, :price_per_consultant, :fixed_monthly_price,
            :payment_terms, :payment_grace_days, :sort_order, :is_active
         )'
    );
    $stmt->execute($params);
    $newId = (int)db()->lastInsertId();
    subscription_plan_save_discounts($newId, $payload);
    log_activity('admin', (int)$admin['id'], 'create_subscription_plan', 'subscription_plans', $newId);

    return $newId;
}

function subscription_plan_save_discounts(int $planId, array $payload): void
{
    $stmt = db()->prepare(
        'INSERT INTO subscription_period_discounts
            (subscription_plan_id, months, discount_percent, badge_text, is_active, sort_order)
         VALUES (:plan_id, :months, :discount, :badge, 1, :sort_order)
         ON DUPLICATE KEY UPDATE discount_percent = VALUES(discount_percent), badge_text = VALUES(badge_text), is_active = 1'
    );
    foreach ([
        [1, 0, null, 10],
        [6, (float)($payload['discount_6'] ?? 2), 'Выгодно', 20],
        [12, (float)($payload['discount_12'] ?? 5), 'Максимальная выгода', 30],
    ] as [$months, $discount, $badge, $sortOrder]) {
        $stmt->execute(['plan_id' => $planId, 'months' => $months, 'discount' => $discount, 'badge' => $badge, 'sort_order' => $sortOrder]);
    }
}

function subscription_plan_assigned_count(int $planId, ?array $admin = null): int
{
    $sql = 'SELECT COUNT(*) FROM resellers WHERE subscription_plan_id = :id';
    $params = ['id' => $planId];
    if ($admin && ($admin['role'] ?? '') === 'reseller' && !empty($admin['reseller_id'])) {
        [$branchSql, $branchParams] = team_sql_in_condition(
            'id',
            team_reseller_branch_ids((int)$admin['reseller_id'], true),
            'assigned_reseller'
        );
        $sql .= ' AND ' . $branchSql;
        $params += $branchParams;
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return (int)$stmt->fetchColumn();
}

function subscription_plan_list_url(array $overrides = []): string
{
    return admin_table_url($overrides, 'subscriptions.php');
}

function subscription_plan_list_data(array $admin): array
{
    $sortMap = [
        'id' => '`id`',
        'title' => '`title`',
        'billing_mode' => '`billing_mode`',
        'billing_basis' => '`billing_basis`',
        'fixed_monthly_price' => '`fixed_monthly_price`',
        'sort_order' => '`sort_order`',
        'is_active' => '`is_active`',
        'leaders_count' => '`leaders_count`',
    ];

    $assignedWhere = 'subscription_plan_id IS NOT NULL';
    $params = [];
    if (($admin['role'] ?? '') === 'reseller' && !empty($admin['reseller_id'])) {
        [$branchSql, $branchParams] = team_sql_in_condition(
            'id',
            team_reseller_branch_ids((int)$admin['reseller_id'], true),
            'assigned_reseller'
        );
        $assignedWhere .= ' AND ' . $branchSql;
        $params += $branchParams;
    }

    [$scopeSql, $scopeParams] = subscription_plan_visibility_sql($admin, 'sp', false);
    $params += $scopeParams;

    return admin_table_paginated_rows(
        'SELECT sp.*, COALESCE(assigned.leaders_count, 0) AS leaders_count, owner.name AS owner_name
         FROM subscription_plans sp
         LEFT JOIN (
            SELECT subscription_plan_id, COUNT(*) AS leaders_count
            FROM resellers
            WHERE ' . $assignedWhere . '
            GROUP BY subscription_plan_id
         ) assigned ON assigned.subscription_plan_id = sp.id
         LEFT JOIN resellers owner ON owner.id = sp.owner_id AND sp.owner_type = "reseller"
         WHERE 1 = 1' . $scopeSql,
        $params,
        $sortMap,
        ['title', 'slug', 'description', 'payment_terms', 'billing_mode', 'billing_basis', 'owner_name'],
        'sort_order',
        'asc'
    );
}

function subscription_plan_active_count(array $admin): int
{
    [$scopeSql, $params] = subscription_plan_visibility_sql($admin, 'subscription_plans', false);
    $stmt = db()->prepare('SELECT COUNT(*) FROM subscription_plans WHERE is_active = 1' . $scopeSql);
    $stmt->execute($params);

    return (int)$stmt->fetchColumn();
}

function subscription_plan_assigned_total(array $admin): int
{
    $sql = 'SELECT COUNT(*) FROM resellers WHERE subscription_plan_id IS NOT NULL';
    $params = [];
    if (($admin['role'] ?? '') === 'reseller' && !empty($admin['reseller_id'])) {
        [$branchSql, $branchParams] = team_sql_in_condition(
            'id',
            team_reseller_branch_ids((int)$admin['reseller_id'], true),
            'assigned_total_reseller'
        );
        $sql .= ' AND ' . $branchSql;
        $params += $branchParams;
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return (int)$stmt->fetchColumn();
}

function subscription_plan_sort_link(string $key, string $label, array $meta): string
{
    return render_admin_sort_link($key, $label, $meta, [
        'id' => '`id`',
        'title' => '`title`',
        'billing_mode' => '`billing_mode`',
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
        'Лидеры 1-го уровня' => $plan['direct_leader_limit'] !== null ? (int)$plan['direct_leader_limit'] : null,
        'Всего лидеров в ветке' => $plan['branch_leader_limit'] !== null ? (int)$plan['branch_leader_limit'] : null,
        'Консультанты 1-го уровня' => $plan['direct_consultant_limit'] !== null ? (int)$plan['direct_consultant_limit'] : null,
        'Всего консультантов в ветке' => $plan['branch_consultant_limit'] !== null ? (int)$plan['branch_consultant_limit'] : null,
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
    $mode = subscription_plan_billing_mode($plan);
    $unitLabel = $mode === 'prepaid' ? 'место' : 'активный';
    ob_start();
    ?>
    <div class="compact-lines">
        <span><strong>Оплата:</strong> <?= h(subscription_billing_mode_labels()[$mode]) ?></span>
        <span><strong>База:</strong> <?= h(subscription_billing_basis_labels()[$plan['billing_basis'] ?? 'branch'] ?? 'Вся ветка') ?></span>
        <span><strong>Лидер:</strong> <?= h(subscription_money_text($plan['price_per_leader'] !== null ? (float)$plan['price_per_leader'] : null)) ?> / <?= h($unitLabel) ?></span>
        <span><strong>Консультант:</strong> <?= h(subscription_money_text($plan['price_per_consultant'] !== null ? (float)$plan['price_per_consultant'] : null)) ?> / <?= h($unitLabel) ?></span>
        <?php if (subscription_money_value($plan['fixed_monthly_price'] ?? null) > 0): ?>
            <span><strong>База:</strong> <?= h(subscription_money_text((float)$plan['fixed_monthly_price'])) ?> / месяц</span>
        <?php endif; ?>
        <span><strong>Формула:</strong> <?= h(subscription_plan_formula_text($plan)) ?></span>
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
        $postId = max(0, (int)($_POST['id'] ?? 0));
        $existingPlan = null;
        if ($postId > 0) {
            $existingPlan = subscription_plan_row($postId, false, $admin, true);
            if (!$existingPlan) {
                $errors[] = 'Эту подписку нельзя редактировать.';
            }
        }

        $plan = subscription_plan_payload_from_post($_POST, $admin, $existingPlan);
        if (!$errors) {
            $errors = subscription_plan_validate($plan, $admin);
        }
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
        $plan = subscription_plan_row($planId, false, $admin, true);
        if (!$plan) {
            $errors[] = 'Эту подписку нельзя менять.';
        } else {
            $stmt = db()->prepare('UPDATE subscription_plans SET is_active = IF(is_active = 1, 0, 1) WHERE id = :id');
            $stmt->execute(['id' => $planId]);
            log_activity('admin', (int)$admin['id'], 'toggle_subscription_plan', 'subscription_plans', $planId);
            redirect('subscriptions.php?success=toggled');
        }
    } elseif ($postAction === 'delete_plan') {
        $planId = (int)($_POST['id'] ?? 0);
        $plan = subscription_plan_row($planId, false, $admin, true);
        if ($planId <= 0) {
            $errors[] = 'Подписка не найдена.';
        } elseif (!$plan) {
            $errors[] = 'Эту подписку нельзя удалить.';
        } elseif (subscription_plan_assigned_count($planId, $admin) > 0) {
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
        $row = subscription_plan_row($id, false, $admin, true);
        if ($row) {
            $editPlan = array_merge($editPlan, $row);
            $discountStmt = db()->prepare('SELECT months, discount_percent FROM subscription_period_discounts WHERE subscription_plan_id = :id');
            $discountStmt->execute(['id' => $id]);
            foreach ($discountStmt->fetchAll() as $discountRow) {
                if ((int)$discountRow['months'] === 6) $editPlan['discount_6'] = (float)$discountRow['discount_percent'];
                if ((int)$discountRow['months'] === 12) $editPlan['discount_12'] = (float)$discountRow['discount_percent'];
            }
        } else {
            $errors[] = 'Подписка не найдена или недоступна для редактирования.';
            $action = 'list';
        }
    }
}

$listData = subscription_plan_list_data($admin);
$rows = $listData['rows'];
$meta = $listData['meta'];
$assignedTotal = subscription_plan_assigned_total($admin);
$activeTotal = subscription_plan_active_count($admin);

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
        <p class="cell-muted">
            <?php if (($admin['role'] ?? '') === 'reseller'): ?>
                Подписка будет доступна лидерам вашей ветки. Лимиты не могут быть выше вашей текущей подписки.
            <?php else: ?>
                Подписка хранит лимиты, цены и условия. В карточке лидера выбирается только один готовый вариант.
            <?php endif; ?>
        </p>
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
                <span>Тип оплаты</span>
                <select name="billing_mode">
                    <?php foreach (subscription_billing_mode_labels() as $value => $label): ?>
                        <option value="<?= h($value) ?>" <?= ($editPlan['billing_mode'] ?? 'prepaid') === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="field-hint">Предоплата считает сумму по выбранным местам. По факту считает активных людей в команде.</small>
            </label>
            <label class="field">
                <span>Кого считать</span>
                <select name="billing_basis">
                    <?php foreach (subscription_billing_basis_labels() as $value => $label): ?>
                        <option value="<?= h($value) ?>" <?= ($editPlan['billing_basis'] ?? 'branch') === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field"><span>Сортировка</span><input type="number" name="sort_order" value="<?= h((string)($editPlan['sort_order'] ?? 100)) ?>"></label>
            <label class="field wide"><span>Описание</span><textarea name="description" rows="3"><?= h((string)($editPlan['description'] ?? '')) ?></textarea></label>

            <label class="field"><span>Лимит лидеров 1-го уровня</span><input type="number" min="0" name="direct_leader_limit" value="<?= h((string)($editPlan['direct_leader_limit'] ?? '')) ?>" placeholder="без лимита"></label>
            <label class="field"><span>Лимит всех лидеров в ветке</span><input type="number" min="0" name="branch_leader_limit" value="<?= h((string)($editPlan['branch_leader_limit'] ?? '')) ?>" placeholder="без лимита"></label>
            <label class="field"><span>Лимит консультантов 1-го уровня</span><input type="number" min="0" name="direct_consultant_limit" value="<?= h((string)($editPlan['direct_consultant_limit'] ?? '')) ?>" placeholder="без лимита"></label>
            <label class="field"><span>Лимит всех консультантов в ветке</span><input type="number" min="0" name="branch_consultant_limit" value="<?= h((string)($editPlan['branch_consultant_limit'] ?? '')) ?>" placeholder="без лимита"></label>
            <label class="field"><span>Консультантов на дочернего лидера</span><input type="number" min="0" name="per_child_consultant_limit" value="<?= h((string)($editPlan['per_child_consultant_limit'] ?? '')) ?>" placeholder="без лимита"></label>

            <label class="field"><span>Цена за лидера в месяц</span><input type="number" step="0.01" min="0" name="price_per_leader" value="<?= h((string)($editPlan['price_per_leader'] ?? '')) ?>" placeholder="0,00"></label>
            <label class="field"><span>Цена за консультанта в месяц</span><input type="number" step="0.01" min="0" name="price_per_consultant" value="<?= h((string)($editPlan['price_per_consultant'] ?? '')) ?>" placeholder="0,00"></label>
            <label class="field"><span>Базовая часть в месяц</span><input type="number" step="0.01" min="0" name="fixed_monthly_price" value="<?= h((string)($editPlan['fixed_monthly_price'] ?? '')) ?>" placeholder="если нужен минимум"></label>
            <label class="field"><span>Дней на оплату счёта по факту</span><input type="number" min="0" max="60" name="payment_grace_days" value="<?= (int)($editPlan['payment_grace_days'] ?? 5) ?>"><small class="field-hint">Счёт формируется 1-го числа за прошедший календарный месяц. После этого срока ограничиваются только клиентские приложения должника.</small></label>
            <label class="field"><span>Скидка при оплате за 6 месяцев, %</span><input type="number" step="0.01" min="0" max="100" name="discount_6" value="<?= h((string)($editPlan['discount_6'] ?? 2)) ?>"></label>
            <label class="field"><span>Скидка при оплате за 12 месяцев, %</span><input type="number" step="0.01" min="0" max="100" name="discount_12" value="<?= h((string)($editPlan['discount_12'] ?? 5)) ?>"></label>
            <div class="field"><span>Формула начисления</span><strong id="subscription-plan-preview">—</strong></div>
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
                    const parts = [];
                    const fixed = numberValue('fixed_monthly_price');
                    const leaderPrice = numberValue('price_per_leader');
                    const consultantPrice = numberValue('price_per_consultant');
                    const mode = form.elements.billing_mode?.value || 'prepaid';
                    const leaderLabel = mode === 'prepaid' ? 'места лидеров' : 'активные лидеры';
                    const consultantLabel = mode === 'prepaid' ? 'места консультантов' : 'активные консультанты';
                    if (fixed > 0) parts.push(`база ${money(fixed)}`);
                    if (leaderPrice > 0) parts.push(`${leaderLabel} × ${money(leaderPrice)}`);
                    if (consultantPrice > 0) parts.push(`${consultantLabel} × ${money(consultantPrice)}`);
                    preview.textContent = parts.length ? parts.join(' + ') : 'стоимость не задана';
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
                    <th>Владелец</th>
                    <th>Условия</th>
                    <th>Лимиты</th>
                    <th><?= subscription_plan_sort_link('fixed_monthly_price', 'Тариф', $meta) ?></th>
                    <th><?= subscription_plan_sort_link('leaders_count', 'Лидеры', $meta) ?></th>
                    <th><?= subscription_plan_sort_link('is_active', 'Статус', $meta) ?></th>
                    <th>Действия</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $isActive = (int)($row['is_active'] ?? 0) === 1;
                    $canEditPlan = subscription_plan_can_edit($row, $admin);
                    ?>
                    <tr>
                        <td data-label="ID" data-column="id"><?= (int)$row['id'] ?></td>
                        <td data-label="Подписка" data-column="title"><strong><?= h((string)$row['title']) ?></strong><br><span class="cell-muted"><?= h((string)$row['slug']) ?></span></td>
                        <td data-label="Владелец" data-column="owner"><?= h(subscription_plan_owner_label($row)) ?></td>
                        <td data-label="Условия" data-column="description">
                            <?= h((string)($row['description'] ?: '—')) ?>
                            <?php if (!empty($row['payment_terms'])): ?><br><span class="cell-muted"><?= h((string)$row['payment_terms']) ?></span><?php endif; ?>
                        </td>
                        <td data-label="Лимиты" data-column="limits"><?= subscription_plan_limits_html($row) ?></td>
                        <td data-label="Тариф" data-column="price"><?= subscription_plan_prices_html($row) ?></td>
                        <td data-label="Лидеры" data-column="leaders_count"><?= (int)$row['leaders_count'] ?></td>
                        <td data-label="Статус" data-column="is_active"><span class="<?= h(subscription_plan_status_class($isActive)) ?>"><?= $isActive ? 'Активна' : 'Отключена' ?></span></td>
                        <td data-label="Действия" data-column="actions" class="row-actions">
                            <?php if ($canEditPlan): ?>
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
                            <?php else: ?>
                                <span class="cell-muted">Только просмотр</span>
                            <?php endif; ?>
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
