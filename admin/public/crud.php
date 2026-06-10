<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/permissions.php';

$admin = require_auth();

$modules = [
    'resellers' => [
        'title' => 'Реселлеры',
        'table' => 'resellers',
        'columns' => ['id', 'name', 'email', 'phone', 'referral_code', 'is_active'],
        'fields' => [
            'name' => ['label' => 'Название', 'required' => true],
            'email' => ['label' => 'Email', 'type' => 'email'],
            'phone' => ['label' => 'Телефон'],
            'referral_code' => ['label' => 'Реферальный код', 'required' => true],
            'is_active' => ['label' => 'Активен', 'type' => 'checkbox', 'default' => 1],
        ],
    ],
    'managers' => [
        'title' => 'Менеджеры',
        'table' => 'managers',
        'columns' => ['id', 'reseller_id', 'name', 'email', 'phone', 'referral_code', 'is_active'],
        'fields' => [
            'reseller_id' => ['label' => 'Реселлер', 'type' => 'select', 'source' => 'resellers', 'nullable' => true],
            'name' => ['label' => 'Имя', 'required' => true],
            'email' => ['label' => 'Email', 'type' => 'email'],
            'phone' => ['label' => 'Телефон'],
            'telegram_id' => ['label' => 'Telegram ID'],
            'max_id' => ['label' => 'MAX ID'],
            'vk_id' => ['label' => 'VK ID'],
            'referral_code' => ['label' => 'Реферальный код', 'required' => true],
            'is_active' => ['label' => 'Активен', 'type' => 'checkbox', 'default' => 1],
        ],
    ],
    'users' => [
        'title' => 'Пользователи',
        'table' => 'end_users',
        'columns' => ['id', 'platform', 'platform_user_id', 'username', 'reseller_id', 'manager_id', 'status'],
        'fields' => [
            'reseller_id' => ['label' => 'Реселлер', 'type' => 'select', 'source' => 'resellers', 'nullable' => true],
            'manager_id' => ['label' => 'Менеджер', 'type' => 'select', 'source' => 'managers', 'nullable' => true],
            'platform' => ['label' => 'Платформа', 'type' => 'select', 'options' => ['telegram', 'vk', 'max', 'web'], 'required' => true],
            'platform_user_id' => ['label' => 'ID на платформе', 'required' => true],
            'username' => ['label' => 'Username'],
            'first_name' => ['label' => 'Имя'],
            'last_name' => ['label' => 'Фамилия'],
            'phone' => ['label' => 'Телефон'],
            'email' => ['label' => 'Email', 'type' => 'email'],
            'referral_code_used' => ['label' => 'Использованный реф. код'],
            'status' => ['label' => 'Статус', 'type' => 'select', 'options' => ['active', 'blocked', 'unsubscribed'], 'required' => true],
        ],
    ],
    'platform_accounts' => [
        'title' => 'Аккаунты платформ',
        'table' => 'platform_accounts',
        'columns' => ['id', 'end_user_id', 'platform', 'platform_user_id', 'username'],
        'fields' => [
            'end_user_id' => ['label' => 'Пользователь', 'type' => 'select', 'source' => 'end_users', 'required' => true],
            'platform' => ['label' => 'Платформа', 'type' => 'select', 'options' => ['telegram', 'vk', 'max', 'web'], 'required' => true],
            'platform_user_id' => ['label' => 'ID на платформе', 'required' => true],
            'username' => ['label' => 'Username'],
        ],
    ],
    'leads' => [
        'title' => 'Лиды',
        'table' => 'leads',
        'columns' => ['id', 'end_user_id', 'manager_id', 'reseller_id', 'product_id', 'source_platform', 'status', 'created_at'],
        'fields' => [
            'end_user_id' => ['label' => 'Пользователь', 'type' => 'select', 'source' => 'end_users', 'required' => true],
            'manager_id' => ['label' => 'Менеджер', 'type' => 'select', 'source' => 'managers', 'nullable' => true],
            'reseller_id' => ['label' => 'Реселлер', 'type' => 'select', 'source' => 'resellers', 'nullable' => true],
            'product_id' => ['label' => 'Продукт', 'type' => 'select', 'source' => 'products', 'nullable' => true],
            'source_platform' => ['label' => 'Платформа', 'type' => 'select', 'options' => ['telegram', 'vk', 'max', 'web'], 'required' => true],
            'message' => ['label' => 'Сообщение', 'type' => 'textarea'],
            'status' => ['label' => 'Статус', 'type' => 'select', 'options' => ['new', 'contacted', 'interested', 'closed', 'lost'], 'required' => true],
        ],
    ],
    'categories' => [
        'title' => 'Категории',
        'table' => 'product_categories',
        'columns' => ['id', 'title', 'slug', 'sort_order', 'is_active'],
        'fields' => [
            'title' => ['label' => 'Название', 'required' => true],
            'slug' => ['label' => 'Slug', 'required' => true],
            'description' => ['label' => 'Описание', 'type' => 'textarea'],
            'sort_order' => ['label' => 'Сортировка', 'type' => 'number', 'default' => 100],
            'is_active' => ['label' => 'Активна', 'type' => 'checkbox', 'default' => 1],
        ],
    ],
    'products' => [
        'title' => 'Продукты',
        'table' => 'products',
        'columns' => ['id', 'category_id', 'title', 'slug', 'price', 'is_active'],
        'fields' => [
            'category_id' => ['label' => 'Категория', 'type' => 'select', 'source' => 'product_categories', 'nullable' => true],
            'title' => ['label' => 'Название', 'required' => true],
            'slug' => ['label' => 'Slug', 'required' => true],
            'short_description' => ['label' => 'Краткое описание', 'type' => 'textarea'],
            'full_description' => ['label' => 'Полное описание', 'type' => 'textarea'],
            'composition' => ['label' => 'Состав', 'type' => 'textarea'],
            'usage_text' => ['label' => 'Применение', 'type' => 'textarea'],
            'warning_text' => ['label' => 'Предупреждение', 'type' => 'textarea'],
            'contraindications' => ['label' => 'Противопоказания', 'type' => 'textarea'],
            'image_path' => ['label' => 'Путь к изображению'],
            'price' => ['label' => 'Цена', 'type' => 'number', 'step' => '0.01', 'nullable' => true],
            'purchase_url' => ['label' => 'Ссылка для покупки/информации'],
            'sort_order' => ['label' => 'Сортировка', 'type' => 'number', 'default' => 100],
            'is_active' => ['label' => 'Активен', 'type' => 'checkbox', 'default' => 1],
        ],
    ],
    'tests' => [
        'title' => 'Тесты',
        'table' => 'tests',
        'columns' => ['id', 'title', 'category_id', 'is_active', 'sort_order'],
        'fields' => [
            'title' => ['label' => 'Название', 'required' => true],
            'description' => ['label' => 'Описание', 'type' => 'textarea'],
            'category_id' => ['label' => 'Категория', 'type' => 'select', 'source' => 'product_categories', 'nullable' => true],
            'sort_order' => ['label' => 'Сортировка', 'type' => 'number', 'default' => 100],
            'is_active' => ['label' => 'Активен', 'type' => 'checkbox', 'default' => 1],
        ],
    ],
    'broadcasts' => [
        'title' => 'Рассылки',
        'table' => 'broadcasts',
        'columns' => ['id', 'title', 'platform', 'target_type', 'scheduled_at', 'status'],
        'fields' => [
            'title' => ['label' => 'Название', 'required' => true],
            'message_text' => ['label' => 'Текст сообщения', 'type' => 'textarea', 'required' => true],
            'image_path' => ['label' => 'Путь к изображению'],
            'button_text' => ['label' => 'Текст кнопки'],
            'button_url' => ['label' => 'Ссылка кнопки'],
            'target_type' => ['label' => 'Кому', 'type' => 'select', 'options' => ['all', 'reseller', 'manager', 'segment'], 'required' => true],
            'target_reseller_id' => ['label' => 'Реселлер', 'type' => 'select', 'source' => 'resellers', 'nullable' => true],
            'target_manager_id' => ['label' => 'Менеджер', 'type' => 'select', 'source' => 'managers', 'nullable' => true],
            'platform' => ['label' => 'Платформа', 'type' => 'select', 'options' => ['all', 'telegram', 'vk', 'max'], 'required' => true],
            'schedule_type' => ['label' => 'Расписание', 'type' => 'select', 'options' => ['once', 'daily', 'weekly', 'monthly'], 'required' => true],
            'scheduled_at' => ['label' => 'Дата отправки', 'type' => 'datetime-local', 'nullable' => true],
            'status' => ['label' => 'Статус', 'type' => 'select', 'options' => ['draft', 'scheduled', 'sent', 'cancelled'], 'required' => true],
        ],
    ],
    'content' => [
        'title' => 'Контент',
        'table' => 'content_posts',
        'columns' => ['id', 'title', 'status', 'publish_at', 'created_by'],
        'fields' => [
            'title' => ['label' => 'Заголовок', 'required' => true],
            'short_text' => ['label' => 'Краткий текст', 'type' => 'textarea'],
            'full_text' => ['label' => 'Полный текст', 'type' => 'textarea'],
            'image_path' => ['label' => 'Путь к изображению'],
            'category_id' => ['label' => 'Категория', 'type' => 'select', 'source' => 'product_categories', 'nullable' => true],
            'status' => ['label' => 'Статус', 'type' => 'select', 'options' => ['draft', 'published', 'hidden'], 'required' => true],
            'publish_at' => ['label' => 'Дата публикации', 'type' => 'datetime-local', 'nullable' => true],
        ],
    ],
];

$moduleKey = $_GET['module'] ?? 'users';
if (!isset($modules[$moduleKey]) || !can_manage($moduleKey, $admin)) {
    http_response_code(403);
    exit('Access denied');
}

$module = $modules[$moduleKey];
$title = $module['title'];

function scope_where_for_module(string $moduleKey, array $admin): array
{
    if ($moduleKey === 'users') {
        return scope_where_for_users($admin);
    }

    if ($moduleKey === 'platform_accounts') {
        [$userWhere, $userParams] = scope_where_for_users($admin);
        if (!$userWhere) {
            return ['', []];
        }
        return [
            'WHERE end_user_id IN (SELECT id FROM end_users ' . $userWhere . ')',
            $userParams,
        ];
    }

    if ($moduleKey === 'leads') {
        return scope_where_for_leads($admin);
    }

    if ($moduleKey === 'managers' && $admin['role'] === 'reseller') {
        return ['WHERE reseller_id = :scope_reseller_id', ['scope_reseller_id' => $admin['reseller_id']]];
    }

    return ['', []];
}

function scoped_row_exists(string $moduleKey, array $module, int $id, array $admin): bool
{
    [$where, $params] = scope_where_for_module($moduleKey, $admin);
    $sql = "SELECT COUNT(*) FROM {$module['table']} WHERE id = :id";
    if ($where) {
        $sql .= ' AND ' . preg_replace('/^WHERE\s+/i', '', $where);
    }
    $params['id'] = $id;
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn() > 0;
}

function scoped_end_user_exists(int $endUserId, array $admin): bool
{
    [$where, $params] = scope_where_for_users($admin);
    $sql = 'SELECT COUNT(*) FROM end_users WHERE id = :id';
    if ($where) {
        $sql .= ' AND ' . preg_replace('/^WHERE\s+/i', '', $where);
    }
    $params['id'] = $endUserId;
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn() > 0;
}

function select_options(string $source, array $admin): array
{
    $allowed = [
        'resellers' => ['table' => 'resellers', 'label' => 'name'],
        'managers' => ['table' => 'managers', 'label' => 'name'],
        'end_users' => ['table' => 'end_users', 'label' => 'platform_user_id'],
        'products' => ['table' => 'products', 'label' => 'title'],
        'product_categories' => ['table' => 'product_categories', 'label' => 'title'],
    ];
    if (!isset($allowed[$source])) {
        return [];
    }

    $item = $allowed[$source];
    $where = '';
    $params = [];
    if ($source === 'managers' && $admin['role'] === 'reseller') {
        $where = 'WHERE reseller_id = :reseller_id';
        $params['reseller_id'] = $admin['reseller_id'];
    }
    if ($source === 'end_users') {
        [$where, $params] = scope_where_for_users($admin);
    }

    $stmt = db()->prepare("SELECT id, {$item['label']} AS label FROM {$item['table']} $where ORDER BY id DESC LIMIT 500");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function normalize_datetime(?string $value): ?string
{
    if (!$value) {
        return null;
    }

    return str_replace('T', ' ', $value);
}

function datetime_for_input(?string $value): string
{
    if (!$value) {
        return '';
    }

    return str_replace(' ', 'T', substr($value, 0, 16));
}

function collect_payload(array $fields): array
{
    $payload = [];
    foreach ($fields as $name => $field) {
        $type = $field['type'] ?? 'text';
        if ($type === 'checkbox') {
            $payload[$name] = isset($_POST[$name]) ? 1 : 0;
            continue;
        }

        $value = trim((string)($_POST[$name] ?? ''));
        if ($type === 'datetime-local') {
            $value = normalize_datetime($value);
        }
        if (($field['nullable'] ?? false) && $value === '') {
            $value = null;
        }
        if ($type === 'number' && $value !== null && $value !== '') {
            $value = str_contains($value, '.') ? (float)$value : (int)$value;
        }
        $payload[$name] = $value;
    }

    return $payload;
}

function validate_payload(array $fields, array $payload): array
{
    $errors = [];
    foreach ($fields as $name => $field) {
        if (($field['required'] ?? false) && (($payload[$name] ?? null) === null || $payload[$name] === '')) {
            $errors[] = 'Заполните поле: ' . ($field['label'] ?? $name);
        }
        if (isset($field['options']) && ($payload[$name] ?? '') !== '' && !in_array($payload[$name], $field['options'], true)) {
            $errors[] = 'Некорректное значение поля: ' . ($field['label'] ?? $name);
        }
    }

    return $errors;
}

function validate_scope_payload(string $moduleKey, array $payload, array $admin): array
{
    $errors = [];
    if (in_array($moduleKey, ['leads', 'platform_accounts'], true)) {
        $endUserId = (int)($payload['end_user_id'] ?? 0);
        if ($endUserId && !scoped_end_user_exists($endUserId, $admin)) {
            $errors[] = 'Выбранный пользователь недоступен для вашей роли.';
        }
    }

    return $errors;
}

function apply_role_defaults(string $moduleKey, array $payload, array $admin): array
{
    if ($admin['role'] === 'reseller' && in_array($moduleKey, ['managers', 'users', 'leads'], true)) {
        $payload['reseller_id'] = $admin['reseller_id'];
    }
    if ($admin['role'] === 'manager' && in_array($moduleKey, ['users', 'leads'], true)) {
        $payload['manager_id'] = $admin['manager_id'];
        $payload['reseller_id'] = $admin['reseller_id'];
    }
    if ($moduleKey === 'broadcasts') {
        $payload['created_by'] = $admin['id'];
    }
    if ($moduleKey === 'content') {
        $payload['created_by'] = $admin['id'];
    }

    return $payload;
}

function save_record(string $moduleKey, array $module, array $payload, ?int $id, array $admin): int
{
    $payload = apply_role_defaults($moduleKey, $payload, $admin);
    $columns = array_keys($payload);

    if ($id) {
        $assignments = implode(', ', array_map(static fn($column) => "`$column` = :$column", $columns));
        $payload['id'] = $id;
        $stmt = db()->prepare("UPDATE {$module['table']} SET $assignments WHERE id = :id");
        $stmt->execute($payload);
        log_activity('admin', (int)$admin['id'], 'update_' . $module['table'], $module['table'], $id);
        return $id;
    }

    $columnSql = implode(', ', array_map(static fn($column) => "`$column`", $columns));
    $placeholderSql = implode(', ', array_map(static fn($column) => ":$column", $columns));
    $stmt = db()->prepare("INSERT INTO {$module['table']} ($columnSql) VALUES ($placeholderSql)");
    $stmt->execute($payload);
    $newId = (int)db()->lastInsertId();
    log_activity('admin', (int)$admin['id'], 'create_' . $module['table'], $module['table'], $newId);
    return $newId;
}

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$errors = [];
$editRow = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $postAction = $_POST['action'] ?? 'save';
    $postId = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;

    if ($postAction === 'delete') {
        if (!$postId || !scoped_row_exists($moduleKey, $module, $postId, $admin)) {
            http_response_code(404);
            exit('Record not found');
        }

        $stmt = db()->prepare("DELETE FROM {$module['table']} WHERE id = :id");
        $stmt->execute(['id' => $postId]);
        log_activity('admin', (int)$admin['id'], 'delete_' . $module['table'], $module['table'], $postId);
        redirect('crud.php?module=' . urlencode($moduleKey));
    }

    if ($postId && !scoped_row_exists($moduleKey, $module, $postId, $admin)) {
        http_response_code(404);
        exit('Record not found');
    }

    $payload = collect_payload($module['fields']);
    $errors = validate_payload($module['fields'], $payload);
    $errors = array_merge($errors, validate_scope_payload($moduleKey, $payload, $admin));
    if (!$errors) {
        save_record($moduleKey, $module, $payload, $postId, $admin);
        redirect('crud.php?module=' . urlencode($moduleKey));
    }

    $editRow = $payload + ['id' => $postId];
    $action = $postId ? 'edit' : 'create';
}

if ($action === 'edit' && $id) {
    if (!scoped_row_exists($moduleKey, $module, $id, $admin)) {
        http_response_code(404);
        exit('Record not found');
    }
    $stmt = db()->prepare("SELECT * FROM {$module['table']} WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $editRow = $stmt->fetch();
}

$columnsSql = implode(', ', array_map(static fn($column) => "`$column`", $module['columns']));
[$where, $params] = scope_where_for_module($moduleKey, $admin);
$stmt = db()->prepare("SELECT $columnsSql FROM {$module['table']} $where ORDER BY id DESC LIMIT 100");
$stmt->execute($params);
$rows = $stmt->fetchAll();

require __DIR__ . '/../app/views/layouts/header.php';
?>
<div class="toolbar">
    <h1><?= h($title) ?></h1>
    <a class="button" href="crud.php?module=<?= h($moduleKey) ?>&action=create">Добавить</a>
</div>
<?php if ($action === 'create' || $action === 'edit'): ?>
    <section class="panel form-panel">
        <h2><?= $action === 'edit' ? 'Редактировать запись' : 'Добавить запись' ?></h2>
        <?php foreach ($errors as $error): ?>
            <div class="alert"><?= h($error) ?></div>
        <?php endforeach; ?>
        <form method="post" class="crud-form">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= h((string)($editRow['id'] ?? '')) ?>">
            <?php foreach ($module['fields'] as $name => $field): ?>
                <?php
                $type = $field['type'] ?? 'text';
                $value = $editRow[$name] ?? ($field['default'] ?? '');
                ?>
                <label class="field">
                    <span><?= h($field['label'] ?? $name) ?><?= ($field['required'] ?? false) ? ' *' : '' ?></span>
                    <?php if ($type === 'textarea'): ?>
                        <textarea name="<?= h($name) ?>" rows="4"><?= h((string)$value) ?></textarea>
                    <?php elseif ($type === 'select'): ?>
                        <select name="<?= h($name) ?>">
                            <?php if ($field['nullable'] ?? false): ?>
                                <option value="">Не выбрано</option>
                            <?php endif; ?>
                            <?php if (isset($field['options'])): ?>
                                <?php foreach ($field['options'] as $option): ?>
                                    <option value="<?= h($option) ?>" <?= (string)$value === (string)$option ? 'selected' : '' ?>><?= h($option) ?></option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php foreach (select_options($field['source'], $admin) as $option): ?>
                                    <option value="<?= (int)$option['id'] ?>" <?= (string)$value === (string)$option['id'] ? 'selected' : '' ?>>
                                        #<?= (int)$option['id'] ?> <?= h($option['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    <?php elseif ($type === 'checkbox'): ?>
                        <input type="checkbox" name="<?= h($name) ?>" value="1" <?= (int)$value === 1 ? 'checked' : '' ?>>
                    <?php else: ?>
                        <?php $inputValue = $type === 'datetime-local' ? datetime_for_input($value ? (string)$value : null) : (string)$value; ?>
                        <input
                            type="<?= h($type) ?>"
                            name="<?= h($name) ?>"
                            value="<?= h($inputValue) ?>"
                            <?= isset($field['step']) ? 'step="' . h($field['step']) . '"' : '' ?>
                        >
                    <?php endif; ?>
                </label>
            <?php endforeach; ?>
            <div class="form-actions">
                <button type="submit">Сохранить</button>
                <a class="button secondary-button" href="crud.php?module=<?= h($moduleKey) ?>">Отмена</a>
            </div>
        </form>
    </section>
<?php endif; ?>
<section class="panel">
    <table>
        <thead>
            <tr>
                <?php foreach ($module['columns'] as $column): ?>
                    <th><?= h($column) ?></th>
                <?php endforeach; ?>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <?php foreach ($module['columns'] as $column): ?>
                        <td><?= h((string)($row[$column] ?? '')) ?></td>
                    <?php endforeach; ?>
                    <td>
                        <a class="link-button" href="crud.php?module=<?= h($moduleKey) ?>&action=edit&id=<?= (int)$row['id'] ?>">Редактировать</a>
                        <form method="post" class="inline-form" onsubmit="return confirm('Удалить запись #<?= (int)$row['id'] ?>?');">
                            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                            <button type="submit" class="link-button danger">Удалить</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="<?= count($module['columns']) + 1 ?>">Записей пока нет.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>
<section class="panel">
    <h2>Правила доступа</h2>
    <p>Супер-админ управляет всеми разделами. Реселлер видит своих менеджеров, пользователей и лиды. Менеджер работает только со своими пользователями и лидами.</p>
</section>
<?php require __DIR__ . '/../app/views/layouts/footer.php'; ?>
