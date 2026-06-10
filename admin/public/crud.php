<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/permissions.php';

$admin = require_auth();

$modules = [
    'resellers' => ['title' => 'Реселлеры', 'table' => 'resellers', 'columns' => ['id', 'name', 'email', 'phone', 'referral_code', 'is_active']],
    'managers' => ['title' => 'Менеджеры', 'table' => 'managers', 'columns' => ['id', 'reseller_id', 'name', 'email', 'phone', 'referral_code', 'is_active']],
    'users' => ['title' => 'Пользователи', 'table' => 'end_users', 'columns' => ['id', 'platform', 'platform_user_id', 'username', 'reseller_id', 'manager_id', 'status']],
    'leads' => ['title' => 'Лиды', 'table' => 'leads', 'columns' => ['id', 'end_user_id', 'manager_id', 'reseller_id', 'product_id', 'source_platform', 'status']],
    'categories' => ['title' => 'Категории', 'table' => 'product_categories', 'columns' => ['id', 'title', 'slug', 'sort_order', 'is_active']],
    'products' => ['title' => 'Продукты', 'table' => 'products', 'columns' => ['id', 'category_id', 'title', 'slug', 'price', 'is_active']],
    'tests' => ['title' => 'Тесты', 'table' => 'tests', 'columns' => ['id', 'title', 'category_id', 'is_active', 'sort_order']],
    'broadcasts' => ['title' => 'Рассылки', 'table' => 'broadcasts', 'columns' => ['id', 'title', 'platform', 'target_type', 'scheduled_at', 'status']],
    'content' => ['title' => 'Контент', 'table' => 'content_posts', 'columns' => ['id', 'title', 'status', 'publish_at', 'created_by']],
];

$moduleKey = $_GET['module'] ?? 'users';
if (!isset($modules[$moduleKey]) || !can_manage($moduleKey, $admin)) {
    http_response_code(403);
    exit('Access denied');
}

$module = $modules[$moduleKey];
$title = $module['title'];
$columnsSql = implode(', ', array_map(static fn($column) => "`$column`", $module['columns']));
$params = [];
$where = '';

if ($moduleKey === 'users') {
    [$where, $params] = scope_where_for_users($admin);
}

if ($moduleKey === 'managers' && $admin['role'] === 'reseller') {
    $where = 'WHERE reseller_id = :reseller_id';
    $params = ['reseller_id' => $admin['reseller_id']];
}

$stmt = db()->prepare("SELECT $columnsSql FROM {$module['table']} $where ORDER BY id DESC LIMIT 100");
$stmt->execute($params);
$rows = $stmt->fetchAll();

require __DIR__ . '/../app/views/layouts/header.php';
?>
<div class="toolbar">
    <h1><?= h($title) ?></h1>
    <button type="button" class="button" disabled>Добавить</button>
</div>
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
                        <button type="button" class="link-button" disabled>Редактировать</button>
                        <button type="button" class="link-button danger" disabled>Удалить</button>
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
    <h2>CRUD-заготовка</h2>
    <p>Форма создания и редактирования будет расширяться по модулю. На этом этапе подготовлены таблицы, права доступа и безопасный вывод данных.</p>
</section>
<?php require __DIR__ . '/../app/views/layouts/footer.php'; ?>
