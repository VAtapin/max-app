<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/permissions.php';
require_once __DIR__ . '/../app/core/crud_views.php';
require_once __DIR__ . '/../app/core/lead_responses.php';

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
        'title' => 'Заявки',
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
        'title' => 'Категории продуктов',
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
            'category_id' => ['label' => 'Категория продукта', 'type' => 'select', 'source' => 'product_categories', 'nullable' => true],
            'title' => ['label' => 'Название', 'required' => true],
            'slug' => ['label' => 'Slug', 'required' => true],
            'short_description' => ['label' => 'Краткое описание', 'type' => 'textarea'],
            'full_description' => ['label' => 'Полное описание', 'type' => 'textarea'],
            'composition' => ['label' => 'Состав', 'type' => 'textarea'],
            'usage_text' => ['label' => 'Применение', 'type' => 'textarea'],
            'warning_text' => ['label' => 'Предупреждение', 'type' => 'textarea'],
            'contraindications' => ['label' => 'Противопоказания', 'type' => 'textarea'],
            'image_path' => ['label' => 'Изображение', 'type' => 'file', 'accept' => 'image/*'],
            'document_path' => ['label' => 'PDF/инструкция', 'type' => 'file', 'accept' => 'application/pdf'],
            'video_url' => ['label' => 'Ссылка на видео'],
            'price' => ['label' => 'Цена', 'type' => 'number', 'step' => '0.01', 'nullable' => true],
            'purchase_url' => ['label' => 'Ссылка на видео/страницу с информацией'],
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
            'image_path' => ['label' => 'Изображение', 'type' => 'file', 'accept' => 'image/*'],
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
        'title' => 'Материалы',
        'table' => 'content_posts',
        'columns' => ['id', 'title', 'status', 'publish_at', 'created_by'],
        'fields' => [
            'content_type' => ['label' => 'Тип материала', 'type' => 'select', 'options' => ['article', 'image', 'pdf', 'video', 'link'], 'required' => true],
            'title' => ['label' => 'Заголовок', 'required' => true],
            'short_text' => ['label' => 'Краткий текст', 'type' => 'textarea'],
            'full_text' => ['label' => 'Полный текст', 'type' => 'textarea'],
            'image_path' => ['label' => 'Изображение', 'type' => 'file', 'accept' => 'image/*'],
            'attachment_path' => ['label' => 'PDF/файл', 'type' => 'file', 'accept' => 'application/pdf,video/mp4,image/*'],
            'video_url' => ['label' => 'Ссылка на видео'],
            'button_text' => ['label' => 'Текст кнопки'],
            'button_url' => ['label' => 'Ссылка кнопки'],
            'category_id' => ['label' => 'Категория продукта', 'type' => 'select', 'source' => 'product_categories', 'nullable' => true],
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
        'content_posts' => ['table' => 'content_posts', 'label' => 'title'],
        'tests' => ['table' => 'tests', 'label' => 'title'],
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

function safe_select_options(string $source, array $admin, array &$errors): array
{
    try {
        return select_options($source, $admin);
    } catch (Throwable $e) {
        $errors[] = 'Не удалось загрузить список для поля: ' . $source . '. ' . $e->getMessage();
        return [];
    }
}

function format_cell_value(mixed $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    return (string)$value;
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
        if ($type === 'file') {
            $current = trim((string)($_POST[$name . '_current'] ?? ''));
            $payload[$name] = $current !== '' ? $current : null;
            continue;
        }

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

function public_upload_path(string $moduleKey, string $filename): string
{
    $folder = match ($moduleKey) {
        'products' => 'products',
        'broadcasts' => 'broadcasts',
        'content' => 'content',
        'leads' => 'responses',
        default => 'files',
    };

    return '/admin/uploads/' . $folder . '/' . $filename;
}

function upload_directory(string $moduleKey): string
{
    $folder = match ($moduleKey) {
        'products' => 'products',
        'broadcasts' => 'broadcasts',
        'content' => 'content',
        'leads' => 'responses',
        default => 'files',
    };

    return dirname(__DIR__) . '/uploads/' . $folder;
}

function apply_file_uploads(string $moduleKey, array $fields, array $payload, array &$errors): array
{
    $config = app_config();
    $allowedImageTypes = $config['security']['allowed_image_types'] ?? ['image/jpeg', 'image/png', 'image/webp'];
    $allowedAttachmentTypes = $config['security']['allowed_attachment_types'] ?? [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
        'video/mp4',
    ];
    $maxBytes = (int)($config['security']['upload_max_bytes'] ?? 5242880);

    foreach ($fields as $name => $field) {
        if (($field['type'] ?? 'text') !== 'file') {
            continue;
        }

        $file = $_FILES[$name] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Не удалось загрузить файл для поля: ' . ($field['label'] ?? $name);
            continue;
        }

        if ((int)$file['size'] > $maxBytes) {
            $errors[] = 'Файл слишком большой. Максимум: ' . round($maxBytes / 1024 / 1024, 1) . ' МБ.';
            continue;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $accept = (string)($field['accept'] ?? 'image/*');
        $allowedTypes = $accept === 'image/*' ? $allowedImageTypes : $allowedAttachmentTypes;
        if (!in_array($mime, $allowedTypes, true)) {
            $errors[] = $accept === 'image/*'
                ? 'Поддерживаются только изображения JPG, PNG или WebP.'
                : 'Поддерживаются JPG, PNG, WebP, PDF или MP4.';
            continue;
        }

        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            'video/mp4' => 'mp4',
            default => null,
        };
        if (!$extension) {
            $errors[] = 'Не удалось определить тип изображения.';
            continue;
        }

        $directory = upload_directory($moduleKey);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            $errors[] = 'Не удалось создать папку для загрузок.';
            continue;
        }

        $filename = date('YmdHis') . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
        $target = $directory . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            $errors[] = 'Не удалось сохранить загруженный файл.';
            continue;
        }

        $payload[$name] = public_upload_path($moduleKey, $filename);
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
$success = $_GET['success'] ?? null;
$editRow = null;
$canCreate = crud_create_enabled($moduleKey);
$canEdit = crud_edit_enabled($moduleKey);
$canDelete = crud_delete_enabled($moduleKey);
$formFields = crud_form_fields($moduleKey, $module['fields']);

if ($action === 'create' && !$canCreate) {
    $errors[] = 'Этот раздел заполняется автоматически из Telegram, VK, MAX или мини-приложения. Создание вручную отключено.';
    $action = 'list';
}

if ($action === 'edit' && !$canEdit) {
    $errors[] = 'Этот раздел доступен только для просмотра. Изменения делаются через карточку пользователя или канал подключения.';
    $action = 'list';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $postAction = $_POST['action'] ?? 'save';
    $postId = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;

    if ($postAction === 'send_lead_response') {
        if ($moduleKey !== 'leads' || !$postId || !scoped_row_exists($moduleKey, $module, $postId, $admin)) {
            http_response_code(404);
            exit('Record not found');
        }

        $responseId = create_and_send_lead_response($postId, $admin, $errors);
        if ($responseId && !$errors) {
            redirect('crud.php?module=leads&action=edit&id=' . $postId . '&success=response_sent');
        }
        $action = 'edit';
        $id = $postId;
    }

    if ($postAction === 'delete') {
        if (!$canDelete) {
            $errors[] = 'Удаление в этом разделе отключено, чтобы не потерять историю пользователей, платформ и заявок.';
        } else {
        if (!$postId || !scoped_row_exists($moduleKey, $module, $postId, $admin)) {
            http_response_code(404);
            exit('Record not found');
        }

        try {
            $stmt = db()->prepare("DELETE FROM {$module['table']} WHERE id = :id");
            $stmt->execute(['id' => $postId]);
            log_activity('admin', (int)$admin['id'], 'delete_' . $module['table'], $module['table'], $postId);
            redirect('crud.php?module=' . urlencode($moduleKey) . '&success=deleted');
        } catch (Throwable $e) {
            $errors[] = 'Не удалось удалить запись: ' . $e->getMessage();
        }
        }
        $action = 'list';
    }

    if ($postAction === 'save') {
    if (($postId && !$canEdit) || (!$postId && !$canCreate)) {
        $errors[] = $postId
            ? 'Редактирование в этом разделе отключено.'
            : 'Создание вручную в этом разделе отключено.';
        $action = 'list';
    } else {
    if ($postId && !scoped_row_exists($moduleKey, $module, $postId, $admin)) {
        http_response_code(404);
        exit('Record not found');
    }

    $payload = collect_payload($formFields);
    $payload = apply_file_uploads($moduleKey, $formFields, $payload, $errors);
    $errors = array_merge($errors, validate_payload($formFields, $payload));
    $errors = array_merge($errors, validate_scope_payload($moduleKey, $payload, $admin));
    if (!$errors) {
        try {
            save_record($moduleKey, $module, $payload, $postId, $admin);
            redirect('crud.php?module=' . urlencode($moduleKey) . '&success=saved');
        } catch (Throwable $e) {
            $errors[] = 'Не удалось сохранить запись: ' . $e->getMessage();
        }
    }

    $editRow = $payload + ['id' => $postId];
    $action = $postId ? 'edit' : 'create';
    }
    }
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

$rows = [];
$listHtml = '';
$displayColumns = crud_display_columns($moduleKey);
try {
    [$listSql, $params] = crud_list_query($moduleKey, $module, $admin);
    $stmt = db()->prepare($listSql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $listHtml = render_crud_list($moduleKey, $displayColumns, $rows, $canEdit, $canDelete);
} catch (Throwable $e) {
    $errors[] = 'Не удалось загрузить список записей: ' . $e->getMessage();
    $listHtml = '<div class="empty-state">Список временно недоступен. Подробность показана выше.</div>';
}

require __DIR__ . '/../app/views/layouts/header.php';
?>
<div class="toolbar">
    <h1><?= h($title) ?></h1>
    <?php if ($canCreate): ?>
        <a class="button" href="crud.php?module=<?= h($moduleKey) ?>&action=create">Добавить</a>
    <?php endif; ?>
</div>
<?php if ($success === 'saved'): ?>
    <div class="notice success">Запись сохранена.</div>
<?php elseif ($success === 'deleted'): ?>
    <div class="notice success">Запись удалена.</div>
<?php elseif ($success === 'response_sent'): ?>
    <div class="notice success">Ответ отправлен пользователю.</div>
<?php endif; ?>
<?php foreach ($errors as $error): ?>
    <div class="alert"><?= h($error) ?></div>
<?php endforeach; ?>
<?php if ($action === 'create' || $action === 'edit'): ?>
    <section class="panel form-panel">
        <h2><?= h(crud_form_title($moduleKey, $action)) ?></h2>
        <form method="post" class="crud-form" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= h((string)($editRow['id'] ?? '')) ?>">
            <?php foreach ($formFields as $name => $field): ?>
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
                                <?php foreach (safe_select_options($field['source'], $admin, $errors) as $option): ?>
                                    <option value="<?= (int)$option['id'] ?>" <?= (string)$value === (string)$option['id'] ? 'selected' : '' ?>>
                                        #<?= (int)$option['id'] ?> <?= h($option['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    <?php elseif ($type === 'file'): ?>
                        <?php if ($value): ?>
                            <a class="file-link" href="<?= h((string)$value) ?>" target="_blank" rel="noopener">Текущий файл</a>
                        <?php endif; ?>
                        <input type="hidden" name="<?= h($name) ?>_current" value="<?= h((string)$value) ?>">
                        <input type="file" name="<?= h($name) ?>" <?= isset($field['accept']) ? 'accept="' . h($field['accept']) . '"' : '' ?>>
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
    <?php if ($moduleKey === 'leads' && $action === 'edit' && $editRow): ?>
        <section class="panel form-panel">
            <h2>Ответить пользователю</h2>
            <form method="post" class="crud-form" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="send_lead_response">
                <input type="hidden" name="id" value="<?= h((string)$editRow['id']) ?>">

                <label class="field">
                    <span>Текст ответа</span>
                    <textarea name="response_text" rows="4" placeholder="Напишите сообщение пользователю"></textarea>
                </label>

                <label class="field">
                    <span>Материал</span>
                    <select name="response_content_id">
                        <option value="">Не выбран</option>
                        <?php foreach (safe_select_options('content_posts', $admin, $errors) as $option): ?>
                            <option value="<?= (int)$option['id'] ?>">#<?= (int)$option['id'] ?> <?= h($option['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="field">
                    <span>Предложить тест</span>
                    <select name="response_test_id">
                        <option value="">Не выбран</option>
                        <?php foreach (safe_select_options('tests', $admin, $errors) as $option): ?>
                            <option value="<?= (int)$option['id'] ?>">#<?= (int)$option['id'] ?> <?= h($option['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="field">
                    <span>Файл: изображение, PDF или MP4</span>
                    <input type="file" name="response_attachment" accept="image/*,application/pdf,video/mp4">
                </label>

                <label class="field">
                    <span>Ссылка на видео, PDF или страницу</span>
                    <input type="url" name="response_external_url" placeholder="https://...">
                </label>

                <div class="form-actions">
                    <button type="submit">Отправить ответ</button>
                </div>
            </form>
        </section>

        <section class="panel">
            <h2>История ответов</h2>
            <?php $responses = lead_response_history((int)$editRow['id']); ?>
            <?php if ($responses): ?>
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Дата</th>
                        <th>Менеджер</th>
                        <th>Ответ</th>
                        <th>Материал/тест</th>
                        <th>Статус</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($responses as $response): ?>
                        <tr>
                            <td><?= h($response['created_at']) ?></td>
                            <td><?= h($response['admin_name'] ?? '—') ?></td>
                            <td><?= nl2br(h($response['message_text'] ?? '')) ?></td>
                            <td>
                                <?= h($response['content_title'] ?? '') ?>
                                <?= $response['test_title'] ? '<br>' . h($response['test_title']) : '' ?>
                                <?= $response['attachment_path'] ? '<br><a href="' . h($response['attachment_path']) . '" target="_blank" rel="noopener">Файл</a>' : '' ?>
                            </td>
                            <td>
                                <?= h($response['status']) ?>
                                <?= $response['error_message'] ? '<br>' . h($response['error_message']) : '' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">Ответов по этой заявке пока нет.</div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
<?php endif; ?>
<section class="panel">
    <?= $listHtml ?>
</section>
<section class="panel">
    <h2>Права доступа</h2>
    <div class="access-rules">
        <p><strong>Супер-админ:</strong> видит всю систему, управляет продуктами, тестами, материалами, рассылками, реселлерами, менеджерами, пользователями и заявками.</p>
        <p><strong>Реселлер:</strong> видит своих менеджеров, пользователей, аккаунты платформ, заявки и рассылки в рамках своей структуры. Продукты и тесты не меняет.</p>
        <p><strong>Менеджер:</strong> видит только назначенных ему пользователей, их платформы и заявки. Может обрабатывать заявки, но не управляет каталогом и тестами.</p>
    </div>
</section>
<?php require __DIR__ . '/../app/views/layouts/footer.php'; ?>
