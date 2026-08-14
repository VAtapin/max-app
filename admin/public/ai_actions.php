<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/permissions.php';
require_once __DIR__ . '/../app/core/ai_workflows.php';

$admin = require_auth();
if (!can_manage('ai_actions', $admin)) {
    http_response_code(403);
    exit('Access denied');
}
$owner = ai_owner_for_admin($admin);
$title = 'Что сделать сегодня';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($admin['role'] ?? '') !== 'superadmin') {
    verify_csrf();
    $id = max(0, (int)($_POST['id'] ?? 0));
    $status = (string)($_POST['status'] ?? 'pending');
    if (!in_array($status, ['pending', 'approved', 'done', 'dismissed'], true)) {
        $status = 'pending';
    }
    $draftText = trim((string)($_POST['draft_text'] ?? ''));
    $stmt = db()->prepare('UPDATE ai_action_suggestions SET status = :status, draft_text = :draft_text, reviewed_by = :admin_id, reviewed_at = NOW() WHERE id = :id AND owner_type = :owner_type AND owner_id = :owner_id');
    $stmt->execute(['status' => $status, 'draft_text' => $draftText, 'admin_id' => (int)$admin['id'], 'id' => $id] + $owner);
    log_activity('admin', (int)$admin['id'], 'review_ai_action', 'ai_action_suggestions', $id, ['status' => $status]);
    redirect('ai_actions.php?success=saved');
}

$isSuperadmin = ($admin['role'] ?? '') === 'superadmin';
$actions = $isSuperadmin ? ai_superadmin_tasks() : ai_workflow_refresh_actions($admin);
require __DIR__ . '/../app/views/layouts/header.php';
?>
<div class="page-title-row"><div><h1>Что сделать сегодня</h1><p class="cell-muted"><?= $isSuperadmin ? 'SWPro показывает административные задачи по настройке, наполнению и безопасности системы.' : 'SWPro собирает поводы для контакта. Вы проверяете и редактируете текст до отправки.' ?></p></div></div>
<?php if (($_GET['success'] ?? '') === 'saved'): ?><div class="notice success">Задача обновлена.</div><?php endif; ?>

<?php if (!$actions): ?>
    <section class="panel empty-state"><h2>На сегодня срочных действий нет</h2><p><?= $isSuperadmin ? 'Настройки, Docsify и утверждённые материалы находятся в рабочем состоянии.' : 'Список обновляется по активности клиентов, чек-апам, датам рождения и персональным планам.' ?></p></section>
<?php elseif ($isSuperadmin): ?>
    <section class="ai-action-list">
    <?php foreach ($actions as $action): ?>
        <article class="panel ai-action-card">
            <div class="ai-action-card-head"><div><span class="eyebrow"><?= h((string)$action['reason']) ?></span><h2><?= h((string)$action['title']) ?></h2></div><span class="badge">Администрирование</span></div>
            <p><?= h((string)$action['description']) ?></p>
            <div class="form-actions"><a class="button" href="<?= h((string)$action['href']) ?>">Открыть и исправить</a></div>
        </article>
    <?php endforeach; ?>
    </section>
<?php else: ?>
    <section class="ai-action-list">
    <?php foreach ($actions as $action): ?>
        <article class="panel ai-action-card">
            <div class="ai-action-card-head"><div><span class="eyebrow"><?= h((string)$action['reason_text']) ?></span><h2><?= h(trim((string)$action['first_name'] . ' ' . (string)$action['last_name']) ?: 'Клиент') ?> — <?= h((string)$action['title']) ?></h2></div><span class="badge"><?= h(platform_label((string)$action['preferred_channel'])) ?></span></div>
            <form method="post" class="crud-form">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$action['id'] ?>">
                <label class="field wide"><span>Подготовленное сообщение</span><textarea name="draft_text" rows="5" id="ai-action-text-<?= (int)$action['id'] ?>"><?= h((string)$action['draft_text']) ?></textarea></label>
                <div class="form-actions">
                    <button type="button" class="secondary-button" data-copy-target="ai-action-text-<?= (int)$action['id'] ?>">Скопировать</button>
                    <button type="submit" name="status" value="approved">Проверено</button>
                    <button type="submit" name="status" value="done">Выполнено</button>
                    <button type="submit" name="status" value="dismissed" class="secondary-button">Не нужно</button>
                    <a class="button secondary-button" href="crud.php?module=leads&end_user_id=<?= (int)$action['end_user_id'] ?>">Открыть обращения</a>
                </div>
            </form>
        </article>
    <?php endforeach; ?>
    </section>
<?php endif; ?>
<script>document.querySelectorAll('[data-copy-target]').forEach((button) => button.addEventListener('click', async () => { const field = document.getElementById(button.dataset.copyTarget); if (!field) return; await navigator.clipboard.writeText(field.value); button.textContent = 'Скопировано'; setTimeout(() => button.textContent = 'Скопировать', 1500); }));</script>
<?php require __DIR__ . '/../app/views/layouts/footer.php'; ?>
