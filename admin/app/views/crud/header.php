<?php
require $crudPublicDir . '/../app/views/layouts/header.php';
?>
<div class="toolbar">
    <h1><?= h($title) ?></h1>
    <?php if ($leadChatOnly): ?>
        <a class="button secondary-button" href="crud.php?module=leads">К списку обращений</a>
    <?php elseif ($canCreate): ?>
        <div class="toolbar-actions">
            <?php if ($moduleKey === 'site_templates' && site_template_current_owner($admin)): ?>
                <form method="post" class="inline-form toolbar-inline-form">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="import_global_site_templates">
                    <button type="submit" class="secondary-button">Импортировать базовые</button>
                </form>
            <?php endif; ?>
            <a class="button" href="crud.php?module=<?= h($moduleKey) ?>&action=create"><?= h(app_text('auto.k_559a87f7cc13')) ?></a>
        </div>
    <?php endif; ?>
</div>
<?php if ($success === 'saved'): ?>
    <div class="notice success"><?= h(app_text('auto.k_ead4c298eba3')) ?></div>
<?php elseif ($success === 'deleted'): ?>
    <div class="notice success"><?= h(app_text('auto.k_5db71cdc4927')) ?></div>
<?php elseif ($success === 'response_sent'): ?>
    <?php $sentPlatformLabel = platform_label((string)($_GET['sent_platform'] ?? '')); ?>
    <div class="notice success"><?= h(app_text('auto.k_0184f257cbfc', ['platform' => $sentPlatformLabel !== '' ? $sentPlatformLabel : 'платформу заявки'])) ?></div>
<?php elseif ($success === 'merged'): ?>
    <div class="notice success"><?= h(app_text('user_merge.success')) ?></div>
<?php elseif ($success === 'promoted' || $success === 'linked_staff'): ?>
    <?php
    $promotedModule = (string)($_GET['promoted_module'] ?? '');
    $promotedId = (int)($_GET['promoted_id'] ?? 0);
    $promotedLabel = $promotedModule === 'resellers' ? 'лидеров' : 'консультантов';
    $promotedText = $success === 'promoted'
        ? 'Клиент преобразован в рабочий аккаунт и теперь отображается в разделе ' . $promotedLabel . '.'
        : 'Клиент связан с рабочим аккаунтом и теперь отображается в разделе ' . $promotedLabel . '.';
    $promotedUrl = in_array($promotedModule, ['managers', 'resellers'], true) && $promotedId > 0
        ? 'crud.php?module=' . rawurlencode($promotedModule) . '&action=edit&id=' . $promotedId
        : '';
    ?>
    <div class="notice success">
        <?= h($promotedText) ?>
        <?php if ($promotedUrl !== ''): ?>
            <a href="<?= h($promotedUrl) ?>">Открыть запись</a>
        <?php endif; ?>
    </div>
<?php elseif ($success === 'personal_copy'): ?>
    <div class="notice success"><?= h(app_text('content_ownership.personal_copy')) ?></div>
<?php elseif ($success === 'content_reset'): ?>
    <div class="notice success">Личная версия сброшена. Теперь снова используется версия выше.</div>
<?php elseif ($success === 'templates_imported'): ?>
    <?php
    $importedTemplates = (int)($_GET['imported'] ?? 0);
    $restoredTemplates = (int)($_GET['restored'] ?? 0);
    $skippedTemplates = (int)($_GET['skipped'] ?? 0);
    ?>
    <div class="notice success">
        Базовые шаблоны импортированы.
        Новых: <?= $importedTemplates ?>,
        восстановлено: <?= $restoredTemplates ?>,
        уже были: <?= $skippedTemplates ?>.
    </div>
<?php elseif ($success === 'broadcast_sent'): ?>
    <div class="notice success"><?= h(app_text('broadcasts.run_success', [
        'sent' => (int)($_GET['sent'] ?? 0),
        'failed' => (int)($_GET['failed'] ?? 0),
    ])) ?></div>
<?php endif; ?>
<?php foreach ($errors as $error): ?>
    <div class="alert"><?= h($error) ?></div>
<?php endforeach; ?>
<?php if ($createLimitErrors && $action === 'list' && in_array($moduleKey, ['managers', 'resellers'], true)): ?>
    <div class="notice warning">
        <strong>Лимит закончился.</strong>
        Чтобы добавить новых участников, увеличьте лимит в подписке.
        <?php foreach ($createLimitErrors as $limitError): ?>
            <br><?= h($limitError) ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php if ($moduleKey === 'integrations' && ($_GET['ok_webhook'] ?? '') === 'ready'): ?>
    <div class="notice success">Группа OK сохранена, webhook подключён. Ответы клиентов будут попадать в живой чат.</div>
<?php elseif ($moduleKey === 'integrations' && ($_GET['ok_webhook'] ?? '') === 'failed'): ?>
    <div class="notice warning">Группа OK сохранена, но webhook пока не подключён. Проверьте ID группы и Bot API token: ответ OK указан в поле «Последняя ошибка Callback».</div>
<?php endif; ?>
<?php if ($moduleKey === 'integrations'): ?>
    <?= render_vk_connection_help_link() ?>
<?php endif; ?>
