<?php

function crud_create_enabled(string $moduleKey): bool
{
    return !in_array($moduleKey, ['users', 'platform_accounts', 'leads'], true);
}

function crud_delete_enabled(string $moduleKey): bool
{
    return !in_array($moduleKey, ['users', 'platform_accounts', 'leads'], true);
}

function crud_edit_enabled(string $moduleKey): bool
{
    return $moduleKey !== 'platform_accounts';
}

function crud_action_label(string $moduleKey): string
{
    return match ($moduleKey) {
        'users' => 'Настроить',
        'leads' => 'Обработать',
        default => 'Редактировать',
    };
}

function crud_form_title(string $moduleKey, string $action): string
{
    if ($action === 'create') {
        return match ($moduleKey) {
            'resellers' => 'Добавить реселлера',
            'managers' => 'Добавить менеджера',
            'categories' => 'Добавить категорию',
            'products' => 'Добавить продукт',
            'tests' => 'Добавить тест',
            'broadcasts' => 'Создать рассылку',
            'content' => 'Добавить материал',
            default => 'Добавить запись',
        };
    }

    return match ($moduleKey) {
        'users' => 'Настройка пользователя',
        'leads' => 'Обработка заявки',
        'resellers' => 'Редактировать реселлера',
        'managers' => 'Редактировать менеджера',
        'categories' => 'Редактировать категорию',
        'products' => 'Редактировать продукт',
        'tests' => 'Редактировать тест',
        'broadcasts' => 'Редактировать рассылку',
        'content' => 'Редактировать материал',
        default => 'Редактировать запись',
    };
}

function crud_form_fields(string $moduleKey, array $fields): array
{
    if ($moduleKey === 'users') {
        return array_intersect_key($fields, array_flip([
            'reseller_id',
            'manager_id',
            'first_name',
            'last_name',
            'phone',
            'email',
            'status',
        ]));
    }

    if ($moduleKey === 'leads') {
        return array_intersect_key($fields, array_flip([
            'manager_id',
            'reseller_id',
            'product_id',
            'message',
            'status',
        ]));
    }

    return $fields;
}

function crud_display_columns(string $moduleKey): array
{
    return [
        'resellers' => [
            'id' => 'ID',
            'name' => 'Реселлер',
            'contacts' => 'Контакты',
            'referral_code' => 'Реф. код',
            'managers_count' => 'Менеджеры',
            'users_count' => 'Пользователи',
            'state' => 'Статус',
        ],
        'managers' => [
            'id' => 'ID',
            'name' => 'Менеджер',
            'reseller_name' => 'Реселлер',
            'contacts' => 'Контакты',
            'referral_code' => 'Реф. код',
            'users_count' => 'Пользователи',
            'state' => 'Статус',
        ],
        'users' => [
            'id' => 'ID',
            'display_name' => 'Пользователь',
            'platform_account' => 'Платформа',
            'reseller_name' => 'Реселлер',
            'manager_name' => 'Менеджер',
            'status' => 'Статус',
            'created_at' => 'Создан',
        ],
        'platform_accounts' => [
            'id' => 'ID',
            'user_name' => 'Пользователь',
            'platform_account' => 'Платформа',
            'username' => 'Username',
            'created_at' => 'Создан',
        ],
        'leads' => [
            'id' => 'ID',
            'lead_status' => 'Статус',
            'lead_summary' => 'Заявка',
            'user_name' => 'Пользователь',
            'product_title' => 'Продукт',
            'response_summary' => 'Ответ',
            'created_at' => 'Создана',
        ],
        'categories' => [
            'id' => 'ID',
            'title' => 'Категория',
            'slug' => 'Slug',
            'products_count' => 'Продукты',
            'sort_order' => 'Сорт.',
            'state' => 'Статус',
        ],
        'products' => [
            'id' => 'ID',
            'image_preview' => 'Фото',
            'title' => 'Продукт',
            'category_title' => 'Категория',
            'media_summary' => 'Медиа',
            'price' => 'Цена',
            'sort_order' => 'Сорт.',
            'state' => 'Статус',
        ],
        'tests' => [
            'id' => 'ID',
            'title' => 'Тест',
            'category_title' => 'Категория',
            'questions_count' => 'Вопросы',
            'sort_order' => 'Сорт.',
            'state' => 'Статус',
        ],
        'broadcasts' => [
            'id' => 'ID',
            'title' => 'Рассылка',
            'platform' => 'Платформа',
            'target_type' => 'Кому',
            'scheduled_at' => 'Когда',
            'status' => 'Статус',
        ],
        'content' => [
            'id' => 'ID',
            'image_preview' => 'Фото',
            'title' => 'Материал',
            'content_type' => 'Тип',
            'category_title' => 'Категория',
            'media_summary' => 'Файлы/ссылки',
            'status' => 'Статус',
            'publish_at' => 'Публикация',
        ],
    ][$moduleKey] ?? [];
}

function lead_filters_from_request(): array
{
    $allowedStatuses = ['new', 'contacted', 'interested', 'closed', 'lost'];
    $allowedPlatforms = ['telegram', 'vk', 'max', 'web'];
    $allowedResponse = ['all', 'none', 'sent', 'pending', 'failed'];

    $status = $_GET['status'] ?? 'new';
    $platform = $_GET['platform'] ?? 'all';
    $response = $_GET['response'] ?? 'all';
    $page = max(1, (int)($_GET['page'] ?? 1));

    return [
        'status' => in_array($status, $allowedStatuses, true) ? $status : 'all',
        'platform' => in_array($platform, $allowedPlatforms, true) ? $platform : 'all',
        'response' => in_array($response, $allowedResponse, true) ? $response : 'all',
        'page' => $page,
        'per_page' => 25,
    ];
}

function append_lead_filter_sql(string $sql, array $filters, array &$params): string
{
    if (($filters['status'] ?? 'all') !== 'all') {
        $sql .= ' AND l.status = :lead_status_filter';
        $params['lead_status_filter'] = $filters['status'];
    }

    if (($filters['platform'] ?? 'all') !== 'all') {
        $sql .= ' AND l.source_platform = :lead_platform_filter';
        $params['lead_platform_filter'] = $filters['platform'];
    }

    $response = $filters['response'] ?? 'all';
    if ($response === 'none') {
        $sql .= ' AND lr.response_count IS NULL';
    } elseif (in_array($response, ['sent', 'pending', 'failed'], true)) {
        $sql .= ' AND lr.last_response_status = :lead_response_filter';
        $params['lead_response_filter'] = $response;
    }

    return $sql;
}

function scoped_where_with_alias(array $scope, string $alias): array
{
    [$where, $params] = $scope;
    if (!$where) {
        return ['', $params];
    }

    $where = preg_replace('/\b(reseller_id|manager_id)\b/', $alias . '.$1', $where);
    return [$where, $params];
}

function crud_list_query(string $moduleKey, array $module, array $admin): array
{
    if ($moduleKey === 'users') {
        [$where, $params] = scoped_where_with_alias(scope_where_for_users($admin), 'eu');
        return [
            "SELECT eu.id, CONCAT_WS(' ', NULLIF(eu.first_name, ''), NULLIF(eu.last_name, '')) AS full_name,
                    eu.username, eu.platform, eu.platform_user_id, eu.status, eu.created_at,
                    r.name AS reseller_name, m.name AS manager_name
             FROM end_users eu
             LEFT JOIN resellers r ON r.id = eu.reseller_id
             LEFT JOIN managers m ON m.id = eu.manager_id
             $where
             ORDER BY eu.id DESC
             LIMIT 100",
            $params,
        ];
    }

    if ($moduleKey === 'platform_accounts') {
        [$where, $params] = scope_where_for_module($moduleKey, $admin);
        return [
            "SELECT pa.id, pa.platform, pa.platform_user_id, pa.username, pa.created_at,
                    CONCAT_WS(' ', NULLIF(eu.first_name, ''), NULLIF(eu.last_name, '')) AS full_name,
                    eu.username AS user_username
             FROM platform_accounts pa
             LEFT JOIN end_users eu ON eu.id = pa.end_user_id
             $where
             ORDER BY pa.id DESC
             LIMIT 100",
            $params,
        ];
    }

    if ($moduleKey === 'leads') {
        [$where, $params] = scoped_where_with_alias(scope_where_for_leads($admin), 'l');
        $filters = lead_filters_from_request();
        $offset = ($filters['page'] - 1) * $filters['per_page'];
        $baseWhere = $where ?: 'WHERE 1=1';
        $baseWhere = append_lead_filter_sql($baseWhere, $filters, $params);
        return [
            "SELECT l.id, l.status, l.source_platform, l.message, l.created_at,
                    CONCAT_WS(' ', NULLIF(eu.first_name, ''), NULLIF(eu.last_name, '')) AS full_name,
                    eu.username AS user_username,
                    p.title AS product_title, m.name AS manager_name, r.name AS reseller_name,
                    lr.response_count, lr.last_response_status, lr.last_response_at
             FROM leads l
             LEFT JOIN end_users eu ON eu.id = l.end_user_id
             LEFT JOIN products p ON p.id = l.product_id
             LEFT JOIN managers m ON m.id = l.manager_id
             LEFT JOIN resellers r ON r.id = l.reseller_id
             LEFT JOIN (
                SELECT lead_id, COUNT(*) AS response_count, MAX(id) AS last_response_id
                FROM lead_responses
                GROUP BY lead_id
             ) lrc ON lrc.lead_id = l.id
             LEFT JOIN lead_responses lr ON lr.id = lrc.last_response_id
             $baseWhere
             ORDER BY l.id DESC
             LIMIT {$filters['per_page']} OFFSET $offset",
            $params,
        ];
    }

    if ($moduleKey === 'resellers') {
        return [
            "SELECT r.id, r.name, r.email, r.phone, r.referral_code,
                    IF(r.is_active = 1, 'активен', 'выключен') AS state,
                    COUNT(DISTINCT m.id) AS managers_count,
                    COUNT(DISTINCT eu.id) AS users_count
             FROM resellers r
             LEFT JOIN managers m ON m.reseller_id = r.id
             LEFT JOIN end_users eu ON eu.reseller_id = r.id
             GROUP BY r.id
             ORDER BY r.id DESC
             LIMIT 100",
            [],
        ];
    }

    if ($moduleKey === 'managers') {
        [$where, $params] = scoped_where_with_alias(scope_where_for_module($moduleKey, $admin), 'm');
        return [
            "SELECT m.id, m.name, m.email, m.phone, m.referral_code,
                    IF(m.is_active = 1, 'активен', 'выключен') AS state,
                    r.name AS reseller_name,
                    COUNT(DISTINCT eu.id) AS users_count
             FROM managers m
             LEFT JOIN resellers r ON r.id = m.reseller_id
             LEFT JOIN end_users eu ON eu.manager_id = m.id
             $where
             GROUP BY m.id
             ORDER BY m.id DESC
             LIMIT 100",
            $params,
        ];
    }

    if ($moduleKey === 'categories') {
        return [
            "SELECT c.id, c.title, c.slug, c.sort_order,
                    IF(c.is_active = 1, 'активна', 'выключена') AS state,
                    COUNT(p.id) AS products_count
             FROM product_categories c
             LEFT JOIN products p ON p.category_id = c.id
             GROUP BY c.id
             ORDER BY c.sort_order ASC, c.id DESC
             LIMIT 100",
            [],
        ];
    }

    if ($moduleKey === 'products') {
        return [
            "SELECT p.id, p.title, c.title AS category_title, p.image_path, p.document_path, p.video_url, p.purchase_url, p.price, p.sort_order,
                    IF(p.is_active = 1, 'активен', 'выключен') AS state
             FROM products p
             LEFT JOIN product_categories c ON c.id = p.category_id
             ORDER BY p.sort_order ASC, p.id DESC
             LIMIT 100",
            [],
        ];
    }

    if ($moduleKey === 'tests') {
        return [
            "SELECT t.id, t.title, c.title AS category_title, t.sort_order,
                    IF(t.is_active = 1, 'активен', 'выключен') AS state,
                    COUNT(q.id) AS questions_count
             FROM tests t
             LEFT JOIN product_categories c ON c.id = t.category_id
             LEFT JOIN test_questions q ON q.test_id = t.id
             GROUP BY t.id
             ORDER BY t.sort_order ASC, t.id DESC
             LIMIT 100",
            [],
        ];
    }

    if ($moduleKey === 'broadcasts') {
        return [
            'SELECT id, title, platform, target_type, scheduled_at, status FROM broadcasts ORDER BY id DESC LIMIT 100',
            [],
        ];
    }

    if ($moduleKey === 'content') {
        return [
            "SELECT cp.id, cp.title, cp.content_type, c.title AS category_title, cp.image_path,
                    cp.attachment_path, cp.video_url, cp.button_url, cp.status, cp.publish_at
             FROM content_posts cp
             LEFT JOIN product_categories c ON c.id = cp.category_id
             ORDER BY cp.id DESC
             LIMIT 100",
            [],
        ];
    }

    $columnsSql = implode(', ', array_map(static fn($column) => "`$column`", $module['columns']));
    [$where, $params] = scope_where_for_module($moduleKey, $admin);
    return ["SELECT $columnsSql FROM {$module['table']} $where ORDER BY id DESC LIMIT 100", $params];
}

function crud_cell_value(string $moduleKey, string $column, array $row): string
{
    if ($column === 'contacts') {
        return trim(($row['email'] ?? '') . "\n" . ($row['phone'] ?? '')) ?: '—';
    }

    if ($column === 'display_name' || $column === 'user_name') {
        return trim(($row['full_name'] ?? '') ?: ($row['user_username'] ?? '') ?: ($row['username'] ?? '')) ?: '—';
    }

    if ($column === 'platform_account') {
        return trim(($row['platform'] ?? '') . "\n" . ($row['platform_user_id'] ?? '')) ?: '—';
    }

    if ($column === 'lead_summary') {
        $message = trim((string)($row['message'] ?? ''));
        $message = $message !== '' ? $message : 'Без сообщения';
        return $message . "\n" . ($row['source_platform'] ?? '');
    }

    if ($column === 'lead_status') {
        return status_label((string)($row['status'] ?? 'new'));
    }

    if ($column === 'response_summary') {
        if (empty($row['response_count'])) {
            return status_label('none');
        }

        $status = (string)($row['last_response_status'] ?? 'pending');
        $date = (string)($row['last_response_at'] ?? '');
        return status_label($status) . ($date ? "\n" . $date : '');
    }

    if ($column === 'media_summary') {
        $items = [];
        if (!empty($row['image_path'])) {
            $items[] = 'изображение';
        }
        if (!empty($row['document_path']) || !empty($row['attachment_path'])) {
            $items[] = 'файл/PDF';
        }
        if (!empty($row['video_url'])) {
            $items[] = 'видео';
        }
        if (!empty($row['purchase_url']) || !empty($row['button_url'])) {
            $items[] = 'ссылка';
        }

        return $items ? implode("\n", $items) : '—';
    }

    return format_cell_value($row[$column] ?? null);
}

function status_badge_class(string $value): string
{
    return match ($value) {
        'new', 'none', status_label('none') => 'badge badge-new',
        'contacted', 'sent', status_label('contacted'), status_label('sent') => 'badge badge-sent',
        'interested', 'pending', status_label('interested'), status_label('pending') => 'badge badge-pending',
        'closed', status_label('closed') => 'badge badge-closed',
        'lost', 'failed', status_label('lost'), status_label('failed') => 'badge badge-failed',
        default => 'badge',
    };
}

function render_cell(string $moduleKey, string $key, array $row): string
{
    if ($key === 'image_preview' && !empty($row['image_path'])) {
        return '<img class="table-thumb" src="' . h($row['image_path']) . '" alt="">';
    }

    if ($moduleKey === 'leads' && in_array($key, ['lead_status', 'response_summary'], true)) {
        $value = crud_cell_value($moduleKey, $key, $row);
        $firstLine = strtok($value, "\n") ?: $value;
        $rest = trim(substr($value, strlen($firstLine)));
        return '<span class="' . h(status_badge_class($firstLine)) . '">' . h($firstLine) . '</span>'
            . ($rest !== '' ? '<div class="cell-muted">' . nl2br(h($rest)) . '</div>' : '');
    }

    if ($moduleKey === 'leads' && $key === 'lead_summary') {
        return '<div class="lead-message">' . nl2br(h(crud_cell_value($moduleKey, $key, $row))) . '</div>';
    }

    return nl2br(h(crud_cell_value($moduleKey, $key, $row)));
}

function render_lead_filters(): string
{
    $filters = lead_filters_from_request();
    $statuses = [
        'all' => 'Все статусы',
        'new' => status_label('new'),
        'contacted' => status_label('contacted'),
        'interested' => status_label('interested'),
        'closed' => status_label('closed'),
        'lost' => status_label('lost'),
    ];
    $platforms = ['all' => platform_label('all'), 'telegram' => platform_label('telegram'), 'vk' => platform_label('vk'), 'max' => platform_label('max'), 'web' => platform_label('web')];
    $responses = ['all' => 'Все ответы', 'none' => status_label('none'), 'sent' => status_label('sent'), 'pending' => status_label('pending'), 'failed' => status_label('failed')];

    ob_start();
    ?>
    <form method="get" class="filters">
        <input type="hidden" name="module" value="leads">
        <label>
            <span>Статус</span>
            <select name="status">
                <?php foreach ($statuses as $value => $label): ?>
                    <option value="<?= h($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Платформа</span>
            <select name="platform">
                <?php foreach ($platforms as $value => $label): ?>
                    <option value="<?= h($value) ?>" <?= $filters['platform'] === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Ответ</span>
            <select name="response">
                <?php foreach ($responses as $value => $label): ?>
                    <option value="<?= h($value) ?>" <?= $filters['response'] === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit">Показать</button>
        <a class="button secondary-button" href="crud.php?module=leads">Сбросить</a>
    </form>
    <?php
    return trim(ob_get_clean());
}

function render_lead_pagination(int $rowCount): string
{
    $filters = lead_filters_from_request();
    $page = $filters['page'];
    $params = $_GET;
    $params['module'] = 'leads';

    ob_start();
    ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <?php $params['page'] = $page - 1; ?>
            <a class="button secondary-button" href="crud.php?<?= h(http_build_query($params)) ?>">Назад</a>
        <?php endif; ?>
        <span>Страница <?= (int)$page ?></span>
        <?php if ($rowCount >= $filters['per_page']): ?>
            <?php $params['page'] = $page + 1; ?>
            <a class="button secondary-button" href="crud.php?<?= h(http_build_query($params)) ?>">Дальше</a>
        <?php endif; ?>
    </div>
    <?php
    return trim(ob_get_clean());
}

function render_crud_list(string $moduleKey, array $columns, array $rows, bool $canEdit, bool $canDelete): string
{
    ob_start();
    ?>
    <?php if ($moduleKey === 'leads'): ?>
        <?= render_lead_filters() ?>
    <?php endif; ?>
    <div class="table-summary">Найдено записей: <?= count($rows) ?></div>
    <?php if ($rows): ?>
        <table class="data-table <?= $moduleKey === 'leads' ? 'compact-table' : '' ?>">
            <thead>
                <tr>
                    <?php foreach ($columns as $label): ?>
                        <th><?= h($label) ?></th>
                    <?php endforeach; ?>
                    <?php if ($canEdit || $canDelete): ?>
                        <th>Действия</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php foreach ($columns as $key => $label): ?>
                            <td><?= render_cell($moduleKey, $key, $row) ?></td>
                        <?php endforeach; ?>
                        <?php if ($canEdit || $canDelete): ?>
                            <td class="row-actions">
                                <?php if ($canEdit): ?>
                                    <a class="link-button" href="crud.php?module=<?= h($moduleKey) ?>&action=edit&id=<?= (int)$row['id'] ?>"><?= h(crud_action_label($moduleKey)) ?></a>
                                <?php endif; ?>
                                <?php if ($canDelete): ?>
                                    <form method="post" class="inline-form" onsubmit="return confirm('Удалить запись #<?= (int)$row['id'] ?>?');">
                                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                        <button type="submit" class="link-button danger">Удалить</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="empty-state">Записей в этом разделе пока нет или они недоступны для вашей роли.</div>
    <?php endif; ?>
    <?php if ($moduleKey === 'leads'): ?>
        <?= render_lead_pagination(count($rows)) ?>
    <?php endif; ?>
    <?php
    return trim(ob_get_clean());
}
