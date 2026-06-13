<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/permissions.php';

$admin = require_auth();
$title = app_text('help.title');

$faqSections = [];
$featuredSection = null;
$errors = [];
$success = '';

function faq_items_from_text(string $value): ?string
{
    $items = array_values(array_filter(array_map(
        static fn(string $line): string => trim($line),
        preg_split('/\R/u', $value) ?: []
    )));

    return $items ? json_encode($items, JSON_UNESCAPED_UNICODE) : null;
}

function faq_items_to_text(?string $json): string
{
    $items = json_decode((string)$json, true);
    return is_array($items) ? implode("\n", array_map('strval', $items)) : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'save_faq') {
            $id = (int)($_POST['id'] ?? 0);
            $payload = [
                'title' => trim((string)($_POST['title'] ?? '')),
                'body' => trim((string)($_POST['body'] ?? '')),
                'items_json' => faq_items_from_text((string)($_POST['items_text'] ?? '')),
                'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'sort_order' => (int)($_POST['sort_order'] ?? 100),
            ];

            if ($payload['title'] === '' || $payload['body'] === '') {
                $errors[] = app_text('help.validation_required');
            } else {
                if ($payload['is_featured']) {
                    db()->exec('UPDATE help_faq_sections SET is_featured = 0');
                }

                if ($id > 0) {
                    $payload['id'] = $id;
                    $stmt = db()->prepare(
                        'UPDATE help_faq_sections
                         SET title = :title, body = :body, items_json = :items_json,
                             is_featured = :is_featured, is_active = :is_active, sort_order = :sort_order
                         WHERE id = :id'
                    );
                    $stmt->execute($payload);
                } else {
                    $stmt = db()->prepare(
                        'INSERT INTO help_faq_sections
                            (title, body, items_json, is_featured, is_active, sort_order)
                         VALUES
                            (:title, :body, :items_json, :is_featured, :is_active, :sort_order)'
                    );
                    $stmt->execute($payload);
                }
                log_activity('admin', (int)$admin['id'], 'save_help_faq', 'help_faq_sections', $id ?: (int)db()->lastInsertId());
                redirect('help.php?success=saved');
            }
        }

        if ($action === 'delete_faq') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = db()->prepare('DELETE FROM help_faq_sections WHERE id = :id');
                $stmt->execute(['id' => $id]);
                log_activity('admin', (int)$admin['id'], 'delete_help_faq', 'help_faq_sections', $id);
            }
            redirect('help.php?success=deleted');
        }
    } catch (Throwable $e) {
        $errors[] = app_text('help.save_failed') . $e->getMessage();
    }
}

$success = (string)($_GET['success'] ?? '');
$allSections = [];

try {
    $stmt = db()->query(
        'SELECT id, title, body, items_json, is_featured, is_active, sort_order
         FROM help_faq_sections
         ORDER BY sort_order, id'
    );
    foreach ($stmt->fetchAll() as $row) {
        $items = json_decode((string)($row['items_json'] ?? ''), true);
        $row['items'] = is_array($items) ? $items : [];
        $allSections[] = $row;
        if ((int)$row['is_active'] === 1) {
            if ((int)$row['is_featured'] === 1 && !$featuredSection) {
                $featuredSection = $row;
                continue;
            }
            $faqSections[] = $row;
        }
    }
} catch (Throwable) {
    $faqSections = [];
    $featuredSection = null;
    $allSections = [];
}

require __DIR__ . '/../app/views/layouts/header.php';
?>
<div class="page-title-row">
    <h1><?= h(app_text('help.title')) ?></h1>
</div>

<?php if ($success): ?>
    <div class="notice success"><?= h(app_text('help.success_' . $success)) ?></div>
<?php endif; ?>
<?php foreach ($errors as $error): ?>
    <div class="notice error"><?= h($error) ?></div>
<?php endforeach; ?>

<?php if ($featuredSection): ?>
    <section class="panel help-hero">
        <span class="eyebrow">SWPro</span>
        <h2><?= h((string)$featuredSection['title']) ?></h2>
        <p><?= h((string)$featuredSection['body']) ?></p>
    </section>
<?php endif; ?>

<?php if ($faqSections): ?>
    <section class="help-grid">
        <?php foreach ($faqSections as $section): ?>
            <article class="panel help-card">
                <h2><?= h((string)$section['title']) ?></h2>
                <p><?= h((string)$section['body']) ?></p>
                <?php if (!empty($section['items'])): ?>
                    <ul>
                        <?php foreach ($section['items'] as $item): ?>
                            <li><?= h((string)$item) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>
<?php else: ?>
    <div class="empty-state"><?= h(app_text('help.empty')) ?></div>
<?php endif; ?>

<section class="panel form-panel faq-editor">
    <h2><?= h(app_text('help.editor_title')) ?></h2>
    <p class="cell-muted"><?= h(app_text('help.editor_hint')) ?></p>

    <form method="post" class="crud-form faq-form">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_faq">
        <label class="field">
            <span><?= h(app_text('help.field_title')) ?></span>
            <input name="title" required>
        </label>
        <label class="field">
            <span><?= h(app_text('help.field_body')) ?></span>
            <textarea name="body" rows="4" required></textarea>
        </label>
        <label class="field">
            <span><?= h(app_text('help.field_items')) ?></span>
            <textarea name="items_text" rows="5" placeholder="<?= h(app_text('help.items_placeholder')) ?>"></textarea>
        </label>
        <label class="field">
            <span><?= h(app_text('help.field_sort')) ?></span>
            <input type="number" name="sort_order" value="100">
        </label>
        <label class="checkbox-line">
            <input type="checkbox" name="is_featured" value="1">
            <?= h(app_text('help.field_featured')) ?>
        </label>
        <label class="checkbox-line">
            <input type="checkbox" name="is_active" value="1" checked>
            <?= h(app_text('help.field_active')) ?>
        </label>
        <div class="form-actions">
            <button type="submit"><?= h(app_text('help.add_section')) ?></button>
        </div>
    </form>
</section>

<?php if ($allSections): ?>
    <section class="panel faq-manage-list">
        <h2><?= h(app_text('help.manage_title')) ?></h2>
        <?php foreach ($allSections as $section): ?>
            <details class="faq-manage-item">
                <summary>
                    <strong><?= h((string)$section['title']) ?></strong>
                    <span class="cell-muted">
                        #<?= (int)$section['id'] ?> · <?= h(app_text((int)$section['is_active'] === 1 ? 'help.state_active' : 'help.state_hidden')) ?>
                        <?= (int)$section['is_featured'] === 1 ? ' · ' . h(app_text('help.state_featured')) : '' ?>
                    </span>
                </summary>
                <form method="post" class="crud-form faq-form">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="save_faq">
                    <input type="hidden" name="id" value="<?= (int)$section['id'] ?>">
                    <label class="field">
                        <span><?= h(app_text('help.field_title')) ?></span>
                        <input name="title" value="<?= h((string)$section['title']) ?>" required>
                    </label>
                    <label class="field">
                        <span><?= h(app_text('help.field_body')) ?></span>
                        <textarea name="body" rows="4" required><?= h((string)$section['body']) ?></textarea>
                    </label>
                    <label class="field">
                        <span><?= h(app_text('help.field_items')) ?></span>
                        <textarea name="items_text" rows="5"><?= h(faq_items_to_text((string)($section['items_json'] ?? ''))) ?></textarea>
                    </label>
                    <label class="field">
                        <span><?= h(app_text('help.field_sort')) ?></span>
                        <input type="number" name="sort_order" value="<?= (int)$section['sort_order'] ?>">
                    </label>
                    <label class="checkbox-line">
                        <input type="checkbox" name="is_featured" value="1" <?= (int)$section['is_featured'] === 1 ? 'checked' : '' ?>>
                        <?= h(app_text('help.field_featured')) ?>
                    </label>
                    <label class="checkbox-line">
                        <input type="checkbox" name="is_active" value="1" <?= (int)$section['is_active'] === 1 ? 'checked' : '' ?>>
                        <?= h(app_text('help.field_active')) ?>
                    </label>
                    <div class="form-actions">
                        <button type="submit"><?= h(app_text('auto.k_4864057d626a')) ?></button>
                    </div>
                </form>
                <form method="post" class="inline-form faq-delete-form" onsubmit="return confirm('<?= h(app_text('help.delete_confirm')) ?>');">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete_faq">
                    <input type="hidden" name="id" value="<?= (int)$section['id'] ?>">
                    <button type="submit" class="link-button danger"><?= h(app_text('auto.k_86ea33aef5e9')) ?></button>
                </form>
            </details>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/../app/views/layouts/footer.php'; ?>
