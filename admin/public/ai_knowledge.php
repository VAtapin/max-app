<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/permissions.php';
require_once __DIR__ . '/../app/core/ai_center.php';

$admin = require_auth();
if (!can_manage('ai_knowledge', $admin)) {
    http_response_code(403);
    exit('Access denied');
}
$owner = ai_owner_for_admin($admin);
$title = 'База знаний ИИ';
$errors = [];
$success = (string)($_GET['success'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? 'save');
    $id = max(0, (int)($_POST['id'] ?? 0));
    $existing = null;
    if ($id > 0) {
        $stmt = db()->prepare('SELECT * FROM ai_knowledge_entries WHERE id = :id AND owner_type = :owner_type AND owner_id = :owner_id LIMIT 1');
        $stmt->execute(['id' => $id] + $owner);
        $existing = $stmt->fetch() ?: null;
    }
    if ($action === 'delete' && $existing) {
        db()->prepare('DELETE FROM ai_knowledge_entries WHERE id = :id')->execute(['id' => $id]);
        log_activity('admin', (int)$admin['id'], 'delete_ai_knowledge', 'ai_knowledge_entries', $id);
        redirect('ai_knowledge.php?success=deleted');
    }
    if ($action === 'save') {
        $payload = [
            'audience' => in_array($_POST['audience'] ?? '', ['admin', 'client', 'both'], true) ? $_POST['audience'] : 'both',
            'title' => trim((string)($_POST['title'] ?? '')),
            'content' => trim((string)($_POST['content'] ?? '')),
            'keywords' => trim((string)($_POST['keywords'] ?? '')),
            'page_context' => trim((string)($_POST['page_context'] ?? '')),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];
        if ($payload['title'] === '' || $payload['content'] === '') {
            $errors[] = 'Заполните название и содержание.';
        } elseif ($id > 0 && !$existing) {
            $errors[] = 'Материал не найден или недоступен.';
        } else {
            if ($existing) {
                $stmt = db()->prepare('UPDATE ai_knowledge_entries SET audience = :audience, title = :title, content = :content, keywords = :keywords, page_context = :page_context, is_active = :is_active, is_approved = 1, approved_by = :approved_by, approved_at = NOW(), version = version + 1 WHERE id = :id');
                $stmt->execute($payload + ['approved_by' => (int)$admin['id'], 'id' => $id]);
            } else {
                $stmt = db()->prepare('INSERT INTO ai_knowledge_entries (owner_type, owner_id, audience, title, content, keywords, page_context, is_active, is_approved, approved_by, approved_at, created_by) VALUES (:owner_type, :owner_id, :audience, :title, :content, :keywords, :page_context, :is_active, 1, :approved_by, NOW(), :created_by)');
                $stmt->execute($payload + $owner + ['approved_by' => (int)$admin['id'], 'created_by' => (int)$admin['id']]);
                $id = (int)db()->lastInsertId();
            }
            log_activity('admin', (int)$admin['id'], 'save_ai_knowledge', 'ai_knowledge_entries', $id);
            redirect('ai_knowledge.php?success=saved');
        }
    }
}

$stmt = db()->prepare('SELECT * FROM ai_knowledge_entries WHERE owner_type = :owner_type AND owner_id = :owner_id ORDER BY updated_at DESC, id DESC');
$stmt->execute($owner);
$rows = $stmt->fetchAll();
require __DIR__ . '/../app/views/layouts/header.php';
?>
<div class="page-title-row"><div><h1>База знаний ИИ</h1><p class="cell-muted">Дополнительные утверждённые материалы. HELP, продукты, материалы сайта, профиль и результаты чек-апа подключаются автоматически.</p></div></div>
<?php if ($success === 'saved'): ?><div class="notice success">Материал сохранён и доступен помощнику.</div><?php endif; ?>
<?php if ($success === 'deleted'): ?><div class="notice success">Материал удалён.</div><?php endif; ?>
<?php foreach ($errors as $error): ?><div class="alert"><?= h($error) ?></div><?php endforeach; ?>

<section class="panel form-panel">
    <h2>Добавить материал</h2>
    <form method="post" class="crud-form">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save">
        <label class="field"><span>Для кого</span><select name="audience"><option value="both">Админка и клиенты</option><option value="admin">Только админка</option><option value="client">Только клиенты</option></select></label>
        <label class="field"><span>Название *</span><input name="title" required></label>
        <label class="field"><span>Контекст страницы</span><input name="page_context" placeholder="products.php или оставить пустым"></label>
        <label class="field wide"><span>Ключевые слова</span><input name="keywords" placeholder="синонимы и формулировки вопросов"></label>
        <label class="field wide"><span>Утверждённое содержание *</span><textarea name="content" rows="8" required></textarea></label>
        <label class="check-row"><input type="checkbox" name="is_active" value="1" checked><span>Материал активен</span></label>
        <div class="form-actions"><button type="submit">Сохранить</button></div>
    </form>
</section>

<?php if ($rows): ?><section class="panel"><h2>Мои дополнительные материалы</h2>
    <?php foreach ($rows as $row): ?><details class="faq-manage-item"><summary><strong><?= h((string)$row['title']) ?></strong><span class="cell-muted"><?= h((string)$row['audience']) ?> · версия <?= (int)$row['version'] ?></span></summary>
        <form method="post" class="crud-form">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
            <label class="field"><span>Для кого</span><select name="audience"><?php foreach (['both' => 'Админка и клиенты', 'admin' => 'Только админка', 'client' => 'Только клиенты'] as $key => $label): ?><option value="<?= h($key) ?>" <?= $row['audience'] === $key ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></label>
            <label class="field"><span>Название</span><input name="title" value="<?= h((string)$row['title']) ?>" required></label>
            <label class="field"><span>Контекст страницы</span><input name="page_context" value="<?= h((string)$row['page_context']) ?>"></label>
            <label class="field wide"><span>Ключевые слова</span><input name="keywords" value="<?= h((string)$row['keywords']) ?>"></label>
            <label class="field wide"><span>Содержание</span><textarea name="content" rows="8" required><?= h((string)$row['content']) ?></textarea></label>
            <label class="check-row"><input type="checkbox" name="is_active" value="1" <?= (int)$row['is_active'] === 1 ? 'checked' : '' ?>><span>Материал активен</span></label>
            <div class="form-actions"><button type="submit">Сохранить</button></div>
        </form>
        <form method="post" class="inline-form" onsubmit="return confirm('Удалить материал?')"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button type="submit" class="link-button danger">Удалить</button></form>
    </details><?php endforeach; ?>
</section><?php endif; ?>
<?php require __DIR__ . '/../app/views/layouts/footer.php'; ?>
