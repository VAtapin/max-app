<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/permissions.php';
require_once __DIR__ . '/../app/core/crud_views.php';
require_once __DIR__ . '/../app/core/lead_responses.php';

$admin = require_auth();
if (!can_manage('leads', $admin)) {
    http_response_code(403);
    exit('Forbidden');
}

$title = 'Чат с клиентом';
$errors = [];
$success = (string)($_GET['success'] ?? '');
$leadId = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);

function lead_chat_row(int $leadId, array $admin): ?array
{
    if ($leadId <= 0) {
        return null;
    }

    [$where, $params] = scoped_where_with_alias(scope_where_for_leads($admin), 'l');
    $scopeSql = $where ? ' AND ' . preg_replace('/^WHERE\s+/i', '', $where) : '';
    $params['id'] = $leadId;

    $stmt = db()->prepare(
        "SELECT l.*, CONCAT_WS(' ', NULLIF(eu.first_name, ''), NULLIF(eu.last_name, '')) AS user_name,
                eu.username AS user_username, eu.platform_user_id AS user_platform_user_id,
                p.title AS product_title, COALESCE(pv.sku, p.catalog_sku) AS product_sku,
                pv.title AS variant_title, pv.volume_text AS variant_volume,
                m.name AS manager_name, r.name AS reseller_name
         FROM leads l
         LEFT JOIN end_users eu ON eu.id = l.end_user_id
         LEFT JOIN products p ON p.id = l.product_id
         LEFT JOIN product_variants pv ON pv.id = l.product_variant_id
         LEFT JOIN managers m ON m.id = l.manager_id
         LEFT JOIN resellers r ON r.id = l.reseller_id
         WHERE l.id = :id{$scopeSql}
         LIMIT 1"
    );
    $stmt->execute($params);
    $row = $stmt->fetch();

    return $row ?: null;
}

function lead_chat_select_options(string $source, array $admin, array &$errors): array
{
    $map = [
        'content_posts' => ['table' => 'content_posts', 'alias' => 'cp', 'module' => 'content', 'label' => 'title', 'extra' => 'cp.status <> "hidden"'],
        'tests' => ['table' => 'tests', 'alias' => 't', 'module' => 'tests', 'label' => 'title', 'extra' => 't.is_active = 1'],
    ];
    if (!isset($map[$source])) {
        return [];
    }

    $item = $map[$source];
    try {
        [$where, $params] = owned_content_scope_condition($item['module'], $admin, $item['alias']);
        $condition = $item['extra'];
        $where = $where
            ? $where . ' AND ' . $condition
            : 'WHERE ' . $condition;

        $stmt = db()->prepare(
            "SELECT {$item['alias']}.id, {$item['alias']}.{$item['label']} AS label
             FROM {$item['table']} {$item['alias']}
             {$where}
             ORDER BY {$item['alias']}.id DESC
             LIMIT 500"
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    } catch (Throwable $e) {
        $errors[] = 'Не удалось загрузить список: ' . $e->getMessage();
        return [];
    }
}

$lead = null;
try {
    $lead = lead_chat_row($leadId, $admin);
} catch (Throwable $e) {
    $errors[] = 'Не удалось открыть чат: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$lead) {
        $errors[] = 'Обращение не найдено или недоступно.';
    } else {
        try {
            $responseId = create_and_send_lead_response($leadId, $admin, $errors);
        } catch (Throwable $e) {
            $responseId = null;
            $errors[] = 'Не удалось отправить ответ: ' . $e->getMessage();
        }

        if ($responseId && !$errors) {
            $platform = lead_response_platform($responseId);
            $platformQuery = $platform !== '' ? '&sent_platform=' . rawurlencode($platform) : '';
            redirect('lead_chat.php?id=' . $leadId . '&success=response_sent' . $platformQuery);
        }
    }
}

$lead = null;
try {
    $lead = lead_chat_row($leadId, $admin);
} catch (Throwable $e) {
    $errors[] = 'Не удалось обновить чат: ' . $e->getMessage();
}
$contentOptions = $lead ? lead_chat_select_options('content_posts', $admin, $errors) : [];
$testOptions = $lead ? lead_chat_select_options('tests', $admin, $errors) : [];
$userLabel = trim((string)($lead['user_name'] ?? ''));
if ($userLabel === '') {
    $userLabel = trim((string)($lead['user_username'] ?? '')) ?: ('Клиент #' . (int)($lead['end_user_id'] ?? 0));
}

require __DIR__ . '/../app/views/layouts/header.php';
?>
<div class="toolbar lead-chat-toolbar">
    <div>
        <h1><?= h($title) ?></h1>
        <?php if ($lead): ?>
            <p class="cell-muted">
                <?= h($userLabel) ?>
                <?= render_platform_badge((string)($lead['source_platform'] ?? '')) ?>
                <span class="<?= h(status_badge_class(status_label((string)($lead['status'] ?? 'new')))) ?>">
                    <?= h(status_label((string)($lead['status'] ?? 'new'))) ?>
                </span>
            </p>
        <?php endif; ?>
    </div>
    <a class="button secondary-button" href="crud.php?module=leads">К списку обращений</a>
</div>

<?php if ($success === 'response_sent'): ?>
    <?php $sentPlatformLabel = platform_label((string)($_GET['sent_platform'] ?? '')); ?>
    <div class="notice success">Ответ отправлен пользователю в <?= h($sentPlatformLabel !== '' ? $sentPlatformLabel : 'платформу заявки') ?>.</div>
<?php endif; ?>

<?php foreach ($errors as $error): ?>
    <div class="alert"><?= h($error) ?></div>
<?php endforeach; ?>

<?php if (!$lead): ?>
    <section class="panel">
        <div class="empty-state">Чат не найден. Вернитесь к списку обращений и откройте актуальную карточку клиента.</div>
    </section>
<?php else: ?>
    <div class="lead-chat-page">
        <section class="panel lead-chat-thread-panel">
            <div class="lead-chat-client-head">
                <div>
                    <span class="eyebrow">Клиент</span>
                    <h2><?= h($userLabel) ?></h2>
                </div>
                <div class="lead-compact-meta">
                    <span><b>Консультант</b><?= h((string)($lead['manager_name'] ?: '—')) ?></span>
                    <?php if (!empty($lead['product_title'])): ?>
                        <span><b>Продукт</b><?= h((string)$lead['product_title']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($lead['product_sku'])): ?>
                        <span><b>Артикул</b><?= h((string)$lead['product_sku']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($lead['variant_title']) || !empty($lead['variant_volume'])): ?>
                        <span><b>Вариант</b><?= h(trim((string)($lead['variant_title'] ?? '') . ' ' . (string)($lead['variant_volume'] ?? ''))) ?></span>
                    <?php endif; ?>
                    <span><b>Обращение</b>#<?= (int)$lead['id'] ?></span>
                </div>
            </div>
            <?= render_lead_conversation((int)$lead['end_user_id']) ?>
        </section>

        <section class="panel form-panel lead-chat-reply-panel">
            <h2>Ответить пользователю</h2>
            <form method="post" class="crud-form" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="send_lead_response">
                <input type="hidden" name="id" value="<?= (int)$lead['id'] ?>">

                <label class="field">
                    <span>Текст ответа</span>
                    <textarea name="response_text" rows="4" placeholder="Напишите ответ клиенту"></textarea>
                </label>

                <div class="form-grid">
                    <label class="field">
                        <span>Материал</span>
                        <select name="response_content_id">
                            <option value="">Не выбрано</option>
                            <?php foreach ($contentOptions as $option): ?>
                                <option value="<?= (int)$option['id'] ?>">#<?= (int)$option['id'] ?> <?= h($option['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="field">
                        <span>Тест</span>
                        <select name="response_test_id">
                            <option value="">Не выбрано</option>
                            <?php foreach ($testOptions as $option): ?>
                                <option value="<?= (int)$option['id'] ?>">#<?= (int)$option['id'] ?> <?= h($option['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <label class="field">
                    <span>Вложения</span>
                    <input type="file" name="response_attachments[]" accept="image/*,application/pdf,video/mp4,audio/*" multiple>
                </label>

                <label class="field">
                    <span>Внешняя ссылка</span>
                    <input type="url" name="response_external_url" placeholder="https://...">
                </label>

                <div class="form-actions">
                    <button type="submit">Отправить ответ</button>
                </div>
            </form>
        </section>
    </div>
<?php endif; ?>

<?= render_lead_media_modal() ?>
<?php require __DIR__ . '/../app/views/layouts/footer.php'; ?>
