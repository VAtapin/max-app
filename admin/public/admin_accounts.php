<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/permissions.php';
require_once __DIR__ . '/../app/core/table_ui.php';

$admin = require_auth();
if (($admin['role'] ?? '') !== 'superadmin') {
    http_response_code(403);
    exit('Доступ запрещён.');
}

$title = 'Пользователи админки';
$roles = [
    'superadmin' => 'Супер-админ',
    'reseller' => 'Лидер',
    'manager' => 'Консультант',
];
$errors = [];
$success = (string)($_GET['success'] ?? '');

function admin_account_role_label(?string $role): string
{
    return [
        'superadmin' => 'Супер-админ',
        'reseller' => 'Лидер',
        'manager' => 'Консультант',
    ][(string)$role] ?? (string)$role;
}

function admin_account_redirect(array $params = []): never
{
    redirect('admin_accounts.php' . ($params ? '?' . http_build_query($params) : ''));
}

function admin_account_fetch(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT au.*, r.name AS reseller_name, m.name AS manager_name, m.reseller_id AS manager_reseller_id
         FROM admin_users au
         LEFT JOIN resellers r ON r.id = au.reseller_id
         LEFT JOIN managers m ON m.id = au.manager_id
         WHERE au.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function admin_account_active_superadmin_count(?int $excludeId = null): int
{
    $sql = 'SELECT COUNT(*) FROM admin_users WHERE role = "superadmin" AND is_active = 1';
    $params = [];
    if ($excludeId !== null) {
        $sql .= ' AND id <> :exclude_id';
        $params['exclude_id'] = $excludeId;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return (int)$stmt->fetchColumn();
}

function admin_account_email_exists(string $email, ?int $exceptId = null): bool
{
    $sql = 'SELECT COUNT(*) FROM admin_users WHERE email = :email';
    $params = ['email' => $email];
    if ($exceptId !== null) {
        $sql .= ' AND id <> :id';
        $params['id'] = $exceptId;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return (int)$stmt->fetchColumn() > 0;
}

function admin_account_resellers(): array
{
    return db()->query('SELECT id, name, email, is_active FROM resellers ORDER BY is_active DESC, name ASC, id DESC')->fetchAll();
}

function admin_account_managers(): array
{
    return db()->query(
        'SELECT m.id, m.name, m.email, m.reseller_id, m.is_active, r.name AS reseller_name
         FROM managers m
         LEFT JOIN resellers r ON r.id = m.reseller_id
         ORDER BY m.is_active DESC, r.name ASC, m.name ASC, m.id DESC'
    )->fetchAll();
}

function admin_account_manager_row(int $managerId): ?array
{
    $stmt = db()->prepare('SELECT id, reseller_id FROM managers WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $managerId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function admin_account_reseller_exists(int $resellerId): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM resellers WHERE id = :id');
    $stmt->execute(['id' => $resellerId]);

    return (int)$stmt->fetchColumn() > 0;
}

function admin_account_normalized_payload(array $post, ?array $existing, array $currentAdmin, array &$errors): array
{
    $id = $existing ? (int)$existing['id'] : null;
    $name = trim((string)($post['name'] ?? ''));
    $emailRaw = trim((string)($post['email'] ?? ''));
    $email = function_exists('mb_strtolower') ? mb_strtolower($emailRaw, 'UTF-8') : strtolower($emailRaw);
    $role = (string)($post['role'] ?? '');
    $password = (string)($post['password'] ?? '');
    $isActive = isset($post['is_active']) ? 1 : 0;
    $phone = trim((string)($post['phone'] ?? ''));
    $telegramId = trim((string)($post['telegram_id'] ?? ''));
    $vkId = trim((string)($post['vk_id'] ?? ''));
    $maxId = trim((string)($post['max_id'] ?? ''));
    $twoFactorRequired = isset($post['two_factor_required']) ? 1 : 0;
    $disableTwoFactor = isset($post['disable_two_factor']) ? 1 : 0;
    if ($disableTwoFactor === 1) {
        $twoFactorRequired = 0;
    }
    $resellerId = null;
    $managerId = null;

    if ($name === '') {
        $errors[] = 'Укажите имя.';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Укажите корректный email для входа.';
    } elseif (admin_account_email_exists($email, $id)) {
        $errors[] = 'Такой email уже используется в другой учётной записи.';
    }
    if (!in_array($role, ['superadmin', 'reseller', 'manager'], true)) {
        $errors[] = 'Выберите роль.';
    }
    if (!$existing && $password === '') {
        $errors[] = 'Для новой учётной записи нужен пароль.';
    }
    $passwordLength = function_exists('mb_strlen') ? mb_strlen($password, 'UTF-8') : strlen($password);
    if ($password !== '' && $passwordLength < 8) {
        $errors[] = 'Пароль должен быть не короче 8 символов.';
    }

    if ($role === 'reseller') {
        $resellerId = (int)($post['reseller_id'] ?? 0);
        if ($resellerId <= 0 || !admin_account_reseller_exists($resellerId)) {
            $errors[] = 'Выберите лидера для этой учётной записи.';
        }
    } elseif ($role === 'manager') {
        $managerId = (int)($post['manager_id'] ?? 0);
        $manager = $managerId > 0 ? admin_account_manager_row($managerId) : null;
        if (!$manager) {
            $errors[] = 'Выберите консультанта для этой учётной записи.';
        } else {
            $resellerId = $manager['reseller_id'] !== null ? (int)$manager['reseller_id'] : null;
        }
    }

    if ($existing) {
        $isCurrent = (int)$existing['id'] === (int)$currentAdmin['id'];
        if ($isCurrent && $role !== 'superadmin') {
            $errors[] = 'Нельзя изменить роль своей текущей учётной записи.';
        }
        if ($isCurrent && $isActive === 0) {
            $errors[] = 'Нельзя отключить свою текущую учётную запись.';
        }
        $removesActiveSuperadmin = (string)$existing['role'] === 'superadmin'
            && (int)$existing['is_active'] === 1
            && ($role !== 'superadmin' || $isActive === 0);
        if ($removesActiveSuperadmin && admin_account_active_superadmin_count((int)$existing['id']) === 0) {
            $errors[] = 'В системе должен остаться хотя бы один активный супер-админ.';
        }
    }

    return [
        'role' => $role,
        'reseller_id' => $resellerId,
        'manager_id' => $managerId,
        'name' => $name,
        'email' => $email,
        'phone' => $phone !== '' ? $phone : null,
        'telegram_id' => $telegramId !== '' ? $telegramId : null,
        'vk_id' => $vkId !== '' ? $vkId : null,
        'max_id' => $maxId !== '' ? $maxId : null,
        'two_factor_required' => $twoFactorRequired,
        'disable_two_factor' => $disableTwoFactor,
        'password' => $password,
        'is_active' => $isActive,
    ];
}

function admin_account_save(array $payload, ?int $id, array $admin): int
{
    $params = [
        'role' => $payload['role'],
        'reseller_id' => $payload['reseller_id'],
        'manager_id' => $payload['manager_id'],
        'name' => $payload['name'],
        'email' => $payload['email'],
        'phone' => $payload['phone'],
        'telegram_id' => $payload['telegram_id'],
        'vk_id' => $payload['vk_id'],
        'max_id' => $payload['max_id'],
        'two_factor_required' => $payload['two_factor_required'],
        'is_active' => $payload['is_active'],
    ];

    if ($id) {
        $passwordSql = '';
        if ($payload['password'] !== '') {
            $passwordSql = ', password_hash = :password_hash';
            $params['password_hash'] = password_hash($payload['password'], PASSWORD_DEFAULT);
        }
        $twoFactorSql = '';
        if ((int)($payload['disable_two_factor'] ?? 0) === 1) {
            $twoFactorSql = ', two_factor_enabled = 0, two_factor_secret = NULL, two_factor_confirmed_at = NULL';
        }
        $params['id'] = $id;
        $stmt = db()->prepare(
            'UPDATE admin_users
             SET role = :role, reseller_id = :reseller_id, manager_id = :manager_id,
                 name = :name, email = :email, phone = :phone, telegram_id = :telegram_id,
                 vk_id = :vk_id, max_id = :max_id, two_factor_required = :two_factor_required,
                 is_active = :is_active' . $passwordSql . $twoFactorSql . '
             WHERE id = :id'
        );
        $stmt->execute($params);
        log_activity('admin', (int)$admin['id'], 'update_admin_user', 'admin_users', $id);

        return $id;
    }

    $params['password_hash'] = password_hash($payload['password'], PASSWORD_DEFAULT);
    $stmt = db()->prepare(
        'INSERT INTO admin_users (
            role, reseller_id, manager_id, name, email, phone, telegram_id, vk_id, max_id,
            two_factor_required, password_hash, is_active
         ) VALUES (
            :role, :reseller_id, :manager_id, :name, :email, :phone, :telegram_id, :vk_id, :max_id,
            :two_factor_required, :password_hash, :is_active
         )'
    );
    $stmt->execute($params);
    $newId = (int)db()->lastInsertId();
    log_activity('admin', (int)$admin['id'], 'create_admin_user', 'admin_users', $newId);

    return $newId;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $postAction = (string)($_POST['action'] ?? '');
    $postId = (int)($_POST['id'] ?? 0);

    if ($postAction === 'delete') {
        $target = $postId > 0 ? admin_account_fetch($postId) : null;
        if (!$target) {
            $errors[] = 'Учётная запись не найдена.';
        } elseif ((int)$target['id'] === (int)$admin['id']) {
            $errors[] = 'Нельзя удалить свою текущую учётную запись.';
        } elseif ((string)$target['role'] === 'superadmin' && (int)$target['is_active'] === 1 && admin_account_active_superadmin_count((int)$target['id']) === 0) {
            $errors[] = 'Нельзя удалить последнего активного супер-админа.';
        }

        if (!$errors) {
            try {
                $stmt = db()->prepare('DELETE FROM admin_users WHERE id = :id');
                $stmt->execute(['id' => $postId]);
                log_activity('admin', (int)$admin['id'], 'delete_admin_user', 'admin_users', $postId);
                admin_account_redirect(['success' => 'deleted']);
            } catch (Throwable $e) {
                $errors[] = 'Не удалось удалить учётную запись: ' . $e->getMessage();
            }
        }
    } elseif ($postAction === 'save') {
        $existing = $postId > 0 ? admin_account_fetch($postId) : null;
        if ($postId > 0 && !$existing) {
            $errors[] = 'Учётная запись не найдена.';
        }
        if (!$errors) {
            $payload = admin_account_normalized_payload($_POST, $existing, $admin, $errors);
        }
        if (!$errors) {
            try {
                $savedId = admin_account_save($payload, $postId > 0 ? $postId : null, $admin);
                admin_account_redirect(['success' => 'saved', 'id' => $savedId]);
            } catch (Throwable $e) {
                $errors[] = 'Не удалось сохранить учётную запись: ' . $e->getMessage();
            }
        }
    }
}

$action = (string)($_GET['action'] ?? 'list');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errors && (string)($_POST['action'] ?? '') === 'save') {
    $action = !empty($_POST['id']) ? 'edit' : 'create';
}
if (!in_array($action, ['list', 'create', 'edit'], true)) {
    $action = 'list';
}

$editRow = null;
if ($action === 'edit') {
    $editId = $_SERVER['REQUEST_METHOD'] === 'POST' ? (int)($_POST['id'] ?? 0) : (int)($_GET['id'] ?? 0);
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errors) {
        $editRow = array_merge(admin_account_fetch($editId) ?: [], $_POST, [
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ]);
    } else {
        $editRow = admin_account_fetch($editId);
    }
    if (!$editRow) {
        $errors[] = 'Учётная запись не найдена.';
        $action = 'list';
    }
}
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST' && $errors) {
    $editRow = array_merge($_POST, ['is_active' => isset($_POST['is_active']) ? 1 : 0]);
}

$resellers = [];
$managers = [];
if ($action === 'create' || $action === 'edit') {
    $resellers = admin_account_resellers();
    $managers = admin_account_managers();
}

$roleFilter = (string)($_GET['role'] ?? '');
$activeFilter = (string)($_GET['active'] ?? '');
$where = [];
$params = [];
if (isset($roles[$roleFilter])) {
    $where[] = 'au.role = :role';
    $params['role'] = $roleFilter;
}
if ($activeFilter === '1' || $activeFilter === '0') {
    $where[] = 'au.is_active = :is_active';
    $params['is_active'] = (int)$activeFilter;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$accountSortMap = [
    'id' => '`id`',
    'name' => '`name`',
    'role' => '`role_order`',
    'binding' => '`reseller_name`',
    'email' => '`email`',
    'two_factor' => '`two_factor_required`',
    'is_active' => '`is_active`',
    'created_at' => '`created_at`',
];
$pageData = admin_table_paginated_rows(
    'SELECT au.*, r.name AS reseller_name, m.name AS manager_name,
            CASE au.role WHEN "superadmin" THEN 1 WHEN "reseller" THEN 2 ELSE 3 END AS role_order
     FROM admin_users au
     LEFT JOIN resellers r ON r.id = au.reseller_id
     LEFT JOIN managers m ON m.id = au.manager_id
     ' . $whereSql,
    $params,
    $accountSortMap,
    ['name', 'email', 'phone', 'telegram_id', 'max_id', 'vk_id', 'reseller_name', 'manager_name', 'role'],
    'role',
    'asc'
);
$accounts = $pageData['rows'];
$tableMeta = $pageData['meta'];
$totalRows = (int)$tableMeta['total'];
$totalPages = (int)$tableMeta['page_count'];
$page = (int)$tableMeta['page'];

function admin_account_page_url(int $page): string
{
    return admin_table_url(['page' => $page], 'admin_accounts.php');
}

require __DIR__ . '/../app/views/layouts/header.php';
?>
<div class="toolbar">
    <h1><?= h($title) ?></h1>
    <?php if ($action === 'list'): ?>
        <a class="button" href="admin_accounts.php?action=create">Добавить</a>
    <?php else: ?>
        <a class="button secondary-button" href="admin_accounts.php">К списку пользователей</a>
    <?php endif; ?>
</div>

<?php if ($success === 'saved'): ?>
    <div class="notice success">Учётная запись сохранена.</div>
<?php elseif ($success === 'deleted'): ?>
    <div class="notice success">Учётная запись удалена.</div>
<?php endif; ?>
<?php foreach ($errors as $error): ?>
    <div class="alert"><?= h($error) ?></div>
<?php endforeach; ?>

<?php if ($action === 'create' || $action === 'edit'): ?>
    <?php
    $formRole = (string)($editRow['role'] ?? 'manager');
    $formResellerId = (string)($editRow['reseller_id'] ?? '');
    $formManagerId = (string)($editRow['manager_id'] ?? '');
    ?>
    <section class="panel form-panel">
        <h2><?= $action === 'create' ? 'Добавить пользователя админки' : 'Редактировать пользователя админки' ?></h2>
        <form method="post" class="crud-form admin-account-form">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= h((string)($editRow['id'] ?? '')) ?>">
            <div class="admin-account-form-grid">
                <label class="field">
                    <span>Роль *</span>
                    <select name="role" data-admin-role-select>
                        <?php foreach ($roles as $role => $label): ?>
                            <option value="<?= h($role) ?>" <?= $formRole === $role ? 'selected' : '' ?>><?= h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field">
                    <span>Имя *</span>
                    <input type="text" name="name" value="<?= h((string)($editRow['name'] ?? '')) ?>" autocomplete="name">
                </label>
                <label class="field">
                    <span>Email для входа *</span>
                    <input type="email" name="email" value="<?= h((string)($editRow['email'] ?? '')) ?>" autocomplete="email">
                </label>
                <label class="field">
                    <span><?= $action === 'create' ? 'Пароль *' : 'Новый пароль' ?></span>
                    <input type="password" name="password" value="" autocomplete="new-password">
                    <?php if ($action === 'edit'): ?><small>Оставьте пустым, если пароль менять не нужно.</small><?php endif; ?>
                </label>
                <label class="field">
                    <span>Телефон</span>
                    <input type="text" name="phone" value="<?= h((string)($editRow['phone'] ?? '')) ?>" autocomplete="tel">
                </label>
                <label class="field">
                    <span>Telegram ID</span>
                    <input type="text" name="telegram_id" value="<?= h((string)($editRow['telegram_id'] ?? '')) ?>">
                </label>
                <label class="field">
                    <span>VK ID</span>
                    <input type="text" name="vk_id" value="<?= h((string)($editRow['vk_id'] ?? '')) ?>">
                </label>
                <label class="field">
                    <span>MAX ID</span>
                    <input type="text" name="max_id" value="<?= h((string)($editRow['max_id'] ?? '')) ?>">
                </label>
                <label class="field" data-role-field="reseller" <?= $formRole === 'reseller' ? '' : 'hidden' ?>>
                    <span>Лидер *</span>
                    <select name="reseller_id">
                        <option value="">Выберите лидера</option>
                        <?php foreach ($resellers as $reseller): ?>
                            <option value="<?= (int)$reseller['id'] ?>" <?= $formResellerId === (string)$reseller['id'] ? 'selected' : '' ?>>
                                #<?= (int)$reseller['id'] ?> <?= h((string)$reseller['name']) ?><?= (int)$reseller['is_active'] === 1 ? '' : ' · отключён' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field" data-role-field="manager" <?= $formRole === 'manager' ? '' : 'hidden' ?>>
                    <span>Консультант *</span>
                    <select name="manager_id">
                        <option value="">Выберите консультанта</option>
                        <?php foreach ($managers as $manager): ?>
                            <option value="<?= (int)$manager['id'] ?>" <?= $formManagerId === (string)$manager['id'] ? 'selected' : '' ?>>
                                #<?= (int)$manager['id'] ?> <?= h((string)$manager['name']) ?><?= $manager['reseller_name'] ? ' · лидер: ' . h((string)$manager['reseller_name']) : '' ?><?= (int)$manager['is_active'] === 1 ? '' : ' · отключён' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>Лидер для учётной записи будет взят из карточки консультанта.</small>
                </label>
                <label class="field checkbox-line">
                    <input type="checkbox" name="is_active" value="1" <?= (int)($editRow['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                    <span>Доступ активен</span>
                </label>
                <label class="field checkbox-line">
                    <input type="checkbox" name="two_factor_required" value="1" <?= (int)($editRow['two_factor_required'] ?? 0) === 1 ? 'checked' : '' ?>>
                    <span>2FA обязательна</span>
                </label>
                <?php if ($action === 'edit'): ?>
                    <label class="field checkbox-line">
                        <input type="checkbox" name="disable_two_factor" value="1">
                        <span>Отключить 2FA</span>
                    </label>
                    <p class="cell-muted">При сохранении обязательность 2FA и привязанный секрет будут удалены.</p>
                <?php endif; ?>
            </div>
            <div class="form-actions">
                <button type="submit">Сохранить</button>
                <a class="button secondary-button" href="admin_accounts.php">Отмена</a>
            </div>
        </form>
    </section>
    <script>
        (() => {
            const select = document.querySelector('[data-admin-role-select]');
            const fields = document.querySelectorAll('[data-role-field]');
            if (!select) {
                return;
            }
            const sync = () => {
                fields.forEach((field) => {
                    field.hidden = field.dataset.roleField !== select.value;
                });
            };
            select.addEventListener('change', sync);
            sync();
        })();
    </script>
<?php endif; ?>

<?php if ($action === 'list'): ?>
<section class="panel">
    <?= render_admin_table_tools($tableMeta, [
        [
            'name' => 'role',
            'label' => 'Роль',
            'options' => ['' => 'Все роли'] + $roles,
            'value' => $roleFilter,
        ],
        [
            'name' => 'active',
            'label' => 'Статус',
            'options' => [
                '' => 'Любой',
                '1' => 'Активные',
                '0' => 'Отключённые',
            ],
            'value' => $activeFilter,
        ],
    ], [
        'reset_url' => 'admin_accounts.php',
        'search_placeholder' => 'Имя, email, телефон, ID, VK, Telegram, MAX',
    ]) ?>

    <p class="table-summary">Найдено записей: <?= (int)$totalRows ?></p>
    <?php if (!$accounts): ?>
        <div class="empty-state">Учётные записи не найдены.</div>
    <?php else: ?>
        <table class="data-table responsive-table" data-module="admin-accounts">
            <thead>
            <tr>
                <th><?= render_admin_sort_link('id', 'ID', $tableMeta, $accountSortMap, 'admin_accounts.php') ?></th>
                <th><?= render_admin_sort_link('name', 'Пользователь', $tableMeta, $accountSortMap, 'admin_accounts.php') ?></th>
                <th><?= render_admin_sort_link('role', 'Роль', $tableMeta, $accountSortMap, 'admin_accounts.php') ?></th>
                <th>Привязка</th>
                <th><?= render_admin_sort_link('email', 'Контакты', $tableMeta, $accountSortMap, 'admin_accounts.php') ?></th>
                <th><?= render_admin_sort_link('two_factor', '2FA', $tableMeta, $accountSortMap, 'admin_accounts.php') ?></th>
                <th><?= render_admin_sort_link('is_active', 'Статус', $tableMeta, $accountSortMap, 'admin_accounts.php') ?></th>
                <th><?= render_admin_sort_link('created_at', 'Создан', $tableMeta, $accountSortMap, 'admin_accounts.php') ?></th>
                <th>Действия</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($accounts as $account): ?>
                <tr>
                    <td data-label="ID"><?= (int)$account['id'] ?></td>
                    <td data-label="Пользователь">
                        <strong><?= h((string)$account['name']) ?></strong>
                        <?php if ((int)$account['id'] === (int)$admin['id']): ?>
                            <span class="badge">Это вы</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Роль"><span class="badge"><?= h(admin_account_role_label((string)$account['role'])) ?></span></td>
                    <td data-label="Привязка">
                        <?php if ($account['role'] === 'reseller'): ?>
                            <?= $account['reseller_name'] ? 'Лидер: ' . h((string)$account['reseller_name']) : 'Лидер не выбран' ?>
                        <?php elseif ($account['role'] === 'manager'): ?>
                            <?= $account['manager_name'] ? 'Консультант: ' . h((string)$account['manager_name']) : 'Консультант не выбран' ?>
                            <?php if ($account['reseller_name']): ?><br><span class="cell-muted">Лидер: <?= h((string)$account['reseller_name']) ?></span><?php endif; ?>
                        <?php else: ?>
                            Полный доступ
                        <?php endif; ?>
                    </td>
                    <td data-label="Контакты">
                        <strong><?= h((string)$account['email']) ?></strong>
                        <?php if ($account['phone']): ?><br><span class="cell-muted"><?= h((string)$account['phone']) ?></span><?php endif; ?>
                        <?php if ($account['telegram_id']): ?><br><span class="cell-muted">TG: <?= h((string)$account['telegram_id']) ?></span><?php endif; ?>
                        <?php if ($account['vk_id']): ?><br><span class="cell-muted">VK: <?= h((string)$account['vk_id']) ?></span><?php endif; ?>
                        <?php if ($account['max_id']): ?><br><span class="cell-muted">MAX: <?= h((string)$account['max_id']) ?></span><?php endif; ?>
                    </td>
                    <td data-label="2FA">
                        <?php
                        $account2faRequired = (int)($account['two_factor_required'] ?? 0) === 1;
                        $account2faReady = admin_two_factor_ready($account);
                        ?>
                        <?php if ($account2faReady): ?>
                            <span class="badge badge-sent"><?= $account2faRequired ? 'Обязательна' : 'Включена' ?></span>
                        <?php elseif ($account2faRequired || (int)($account['two_factor_enabled'] ?? 0) === 1): ?>
                            <span class="badge badge-new">Нужно настроить</span>
                        <?php else: ?>
                            <span class="cell-muted">Нет</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Статус">
                        <span class="badge <?= (int)$account['is_active'] === 1 ? 'badge-sent' : 'badge-new' ?>">
                            <?= (int)$account['is_active'] === 1 ? 'Активен' : 'Отключён' ?>
                        </span>
                    </td>
                    <td data-label="Создан"><?= h((string)$account['created_at']) ?></td>
                    <td data-label="Действия" class="row-actions">
                        <a class="link-button" href="admin_accounts.php?action=edit&id=<?= (int)$account['id'] ?>">Редактировать</a>
                        <form method="post" class="inline-form" onsubmit="return confirm('Удалить эту учётную запись?');">
                            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$account['id'] ?>">
                            <button type="submit" class="link-button danger" <?= (int)$account['id'] === (int)$admin['id'] ? 'disabled' : '' ?>>Удалить</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?= render_admin_pagination($tableMeta, 'admin_accounts.php') ?>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php require __DIR__ . '/../app/views/layouts/footer.php'; ?>
