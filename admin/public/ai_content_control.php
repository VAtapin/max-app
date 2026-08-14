<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/permissions.php';
require_once __DIR__ . '/../app/core/ai_content_governance.php';

$admin = require_auth();
if (($admin['role'] ?? '') !== 'superadmin') {
    http_response_code(403);
    exit('Access denied');
}

$title = 'Контроль базы ИИ';
$errors = [];
$notice = '';

function ai_control_date(string $value): ?string
{
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
}

function ai_control_status_label(string $status): string
{
    return match ($status) {
        'approved' => 'Утверждено',
        'review' => 'На проверке',
        default => 'Черновик',
    };
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'sync_docs') {
            $sync = ai_docs_sync((int)$admin['id']);
            log_activity('admin', (int)$admin['id'], 'sync_docsify_ai', 'ai_knowledge_entries', null, $sync);
            $notice = 'Docsify синхронизирован: добавлено ' . $sync['created'] . ', обновлено ' . $sync['updated'] . ', без изменений ' . $sync['unchanged'] . ', отключено ' . $sync['disabled'] . '.';
        } elseif (in_array($action, ['save_product', 'save_scale_result', 'save_test_result'], true)) {
            $map = [
                'save_product' => ['products', 'product_id'],
                'save_scale_result' => ['test_scale_results', 'result_id'],
                'save_test_result' => ['test_results', 'result_id'],
            ];
            [$table, $idField] = $map[$action];
            $id = max(0, (int)($_POST[$idField] ?? 0));
            if ($id <= 0) {
                throw new RuntimeException('Запись не найдена.');
            }
            $status = in_array($_POST['content_status'] ?? '', ['draft', 'review', 'approved'], true) ? $_POST['content_status'] : 'draft';
            $common = [
                'id' => $id,
                'ai_enabled' => isset($_POST['ai_enabled']) ? 1 : 0,
                'content_status' => $status,
                'source_urls' => trim((string)($_POST['source_urls'] ?? '')) ?: null,
                'next_review_at' => ai_control_date(trim((string)($_POST['next_review_at'] ?? ''))),
                'reviewed_by' => $status === 'approved' ? (int)$admin['id'] : null,
            ];
            if ($action === 'save_product') {
                $currentStmt = db()->prepare('SELECT composition, usage_text, warning_text, contraindications FROM products WHERE id = :id LIMIT 1');
                $currentStmt->execute(['id' => $id]);
                $current = $currentStmt->fetch();
                $allowedClaims = trim((string)($_POST['allowed_claims'] ?? ''));
                if (!$current) {
                    throw new RuntimeException('Продукт не найден.');
                }
                if ($status === 'approved' && (!$current['composition'] || !$current['usage_text'] || !$current['warning_text'] || !$current['contraindications'] || $allowedClaims === '' || !$common['source_urls'])) {
                    throw new RuntimeException('Нельзя утвердить продукт: заполните состав, применение, предупреждения, противопоказания, допустимые формулировки и первоисточники.');
                }
                $stmt = db()->prepare('UPDATE products SET allowed_claims = :allowed_claims, source_urls = :source_urls, ai_enabled = :ai_enabled, content_status = :content_status, reviewed_by = :reviewed_by, reviewed_at = IF(:content_status_check = "approved", NOW(), NULL), next_review_at = :next_review_at WHERE id = :id');
                $stmt->execute($common + ['allowed_claims' => $allowedClaims ?: null, 'content_status_check' => $status]);
            } else {
                $currentStmt = db()->prepare('SELECT summary_text, advice_text FROM ' . $table . ' WHERE id = :id LIMIT 1');
                $currentStmt->execute(['id' => $id]);
                $current = $currentStmt->fetch();
                if (!$current) {
                    throw new RuntimeException('Результат чек-апа не найден.');
                }
                if ($status === 'approved' && (!$current['summary_text'] || !$current['advice_text'] || !$common['source_urls'])) {
                    throw new RuntimeException('Нельзя утвердить результат: заполните объяснение, совет и первоисточники.');
                }
                $stmt = db()->prepare('UPDATE ' . $table . ' SET exclusions_text = :exclusions_text, escalation_text = :escalation_text, source_urls = :source_urls, ai_enabled = :ai_enabled, content_status = :content_status, reviewed_by = :reviewed_by, reviewed_at = IF(:content_status_check = "approved", NOW(), NULL), next_review_at = :next_review_at WHERE id = :id');
                $stmt->execute($common + [
                    'exclusions_text' => trim((string)($_POST['exclusions_text'] ?? '')) ?: null,
                    'escalation_text' => trim((string)($_POST['escalation_text'] ?? '')) ?: null,
                    'content_status_check' => $status,
                ]);
            }
            log_activity('admin', (int)$admin['id'], 'review_ai_content', $table, $id, ['status' => $status]);
            $notice = 'Статус материала сохранён.';
        } elseif ($action === 'save_rule') {
            $scaleResultId = max(0, (int)($_POST['scale_result_id'] ?? 0));
            $testResultId = max(0, (int)($_POST['test_result_id'] ?? 0));
            $targetType = in_array($_POST['target_type'] ?? '', ['product', 'content'], true) ? $_POST['target_type'] : '';
            $targetId = max(0, (int)($targetType === 'content' ? ($_POST['content_target_id'] ?? 0) : ($_POST['product_target_id'] ?? 0)));
            $rationale = trim((string)($_POST['rationale'] ?? ''));
            if (($scaleResultId <= 0 && $testResultId <= 0) || ($scaleResultId > 0 && $testResultId > 0) || $targetType === '' || $targetId <= 0) {
                throw new RuntimeException('Выберите один результат и один целевой продукт или материал.');
            }
            if ($rationale === '') {
                throw new RuntimeException('Добавьте утверждённое объяснение связи или исключения.');
            }
            $stmt = db()->prepare('INSERT INTO ai_recommendation_rules (scale_result_id, test_result_id, target_type, target_id, rule_type, rationale, priority, is_active, is_approved, approved_by, approved_at, created_by) VALUES (:scale_result_id, :test_result_id, :target_type, :target_id, :rule_type, :rationale, :priority, :is_active, 1, :approved_by, NOW(), :created_by)');
            $stmt->execute([
                'scale_result_id' => $scaleResultId ?: null, 'test_result_id' => $testResultId ?: null,
                'target_type' => $targetType, 'target_id' => $targetId,
                'rule_type' => ($_POST['rule_type'] ?? '') === 'exclude' ? 'exclude' : 'include',
                'rationale' => $rationale,
                'priority' => max(1, min(1000, (int)($_POST['priority'] ?? 100))),
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'approved_by' => (int)$admin['id'], 'created_by' => (int)$admin['id'],
            ]);
            log_activity('admin', (int)$admin['id'], 'save_ai_recommendation_rule', 'ai_recommendation_rules', (int)db()->lastInsertId());
            $notice = 'Правило рекомендации добавлено.';
        } elseif ($action === 'delete_rule') {
            $id = max(0, (int)($_POST['id'] ?? 0));
            db()->prepare('DELETE FROM ai_recommendation_rules WHERE id = :id')->execute(['id' => $id]);
            log_activity('admin', (int)$admin['id'], 'delete_ai_recommendation_rule', 'ai_recommendation_rules', $id);
            $notice = 'Правило удалено.';
        } elseif ($action === 'save_scenario') {
            $eventKey = preg_replace('/[^a-z0-9_.:-]/i', '', trim((string)($_POST['event_key'] ?? '')));
            $template = trim((string)($_POST['template_text'] ?? ''));
            if ($eventKey === '' || $template === '') {
                throw new RuntimeException('Заполните событие и текст сценария.');
            }
            $channel = in_array($_POST['channel'] ?? '', ['any','admin','web','telegram','VK','OK','MAX'], true) ? $_POST['channel'] : 'any';
            $audience = ($_POST['audience'] ?? '') === 'admin' ? 'admin' : 'client';
            $stmt = db()->prepare('INSERT INTO ai_conversation_scenarios (owner_type, owner_id, event_key, channel, audience, title, template_text, allowed_variables, priority, is_active, is_approved, approved_by, approved_at, created_by) VALUES ("superadmin", 0, :event_key, :channel, :audience, :title, :template_text, :variables, :priority, :is_active, 1, :approved_by, NOW(), :created_by)');
            $stmt->execute([
                'event_key' => $eventKey, 'channel' => $channel, 'audience' => $audience,
                'title' => trim((string)($_POST['title'] ?? '')) ?: $eventKey, 'template_text' => $template,
                'variables' => json_encode(['first_name','consultant_name','test_title','days','city'], JSON_UNESCAPED_UNICODE),
                'priority' => max(1, min(1000, (int)($_POST['priority'] ?? 100))),
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'approved_by' => (int)$admin['id'], 'created_by' => (int)$admin['id'],
            ]);
            log_activity('admin', (int)$admin['id'], 'save_ai_scenario', 'ai_conversation_scenarios', (int)db()->lastInsertId());
            $notice = 'Сценарий добавлен.';
        } elseif ($action === 'delete_scenario') {
            $id = max(0, (int)($_POST['id'] ?? 0));
            db()->prepare('DELETE FROM ai_conversation_scenarios WHERE id = :id')->execute(['id' => $id]);
            log_activity('admin', (int)$admin['id'], 'delete_ai_scenario', 'ai_conversation_scenarios', $id);
            $notice = 'Сценарий удалён.';
        } elseif ($action === 'save_channel') {
            $platform = in_array($_POST['platform'] ?? '', ['telegram','VK','OK','MAX','web'], true) ? $_POST['platform'] : '';
            if ($platform === '') {
                throw new RuntimeException('Некорректный канал.');
            }
            $stmt = db()->prepare('UPDATE ai_channel_media_rules SET delivery_mode = :delivery_mode, max_file_bytes = :max_file_bytes, max_duration_seconds = :max_duration_seconds, allowed_mime_types = :allowed_mime_types, fallback_mode = :fallback_mode, is_active = :is_active, notes = :notes, updated_by = :admin_id WHERE platform = :platform');
            $stmt->execute([
                'platform' => $platform,
                'delivery_mode' => in_array($_POST['delivery_mode'] ?? '', ['native_video','video_message','link'], true) ? $_POST['delivery_mode'] : 'native_video',
                'max_file_bytes' => max(1048576, (int)($_POST['max_file_mb'] ?? 20) * 1048576),
                'max_duration_seconds' => max(5, min(3600, (int)($_POST['max_duration_seconds'] ?? 60))),
                'allowed_mime_types' => trim((string)($_POST['allowed_mime_types'] ?? 'video/mp4')),
                'fallback_mode' => in_array($_POST['fallback_mode'] ?? '', ['native_video','link','text'], true) ? $_POST['fallback_mode'] : 'link',
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'notes' => trim((string)($_POST['notes'] ?? '')) ?: null, 'admin_id' => (int)$admin['id'],
            ]);
            $notice = 'Правило доставки сохранено.';
        }
    } catch (Throwable $error) {
        $errors[] = $error->getMessage();
    }
}

$readiness = ai_content_readiness();
$products = db()->query('SELECT id, title, composition, usage_text, warning_text, contraindications, allowed_claims, source_urls, ai_enabled, content_status, next_review_at FROM products WHERE is_deleted = 0 ORDER BY content_status, title LIMIT 200')->fetchAll();
$scaleResults = db()->query('SELECT tr.*, ts.title scale_title, t.id test_id, t.title test_title FROM test_scale_results tr JOIN test_scales ts ON ts.id = tr.scale_id JOIN tests t ON t.id = ts.test_id ORDER BY tr.content_status, t.title, ts.sort_order, tr.min_score LIMIT 300')->fetchAll();
$testResults = db()->query('SELECT tr.*, t.title test_title FROM test_results tr JOIN tests t ON t.id = tr.test_id ORDER BY tr.content_status, t.title, tr.min_score LIMIT 300')->fetchAll();
$productOptions = db()->query('SELECT id, title FROM products WHERE is_deleted = 0 AND is_active = 1 ORDER BY title')->fetchAll();
$contentOptions = db()->query('SELECT id, title FROM content_posts WHERE is_deleted = 0 AND status = "published" ORDER BY title')->fetchAll();
$rules = db()->query('SELECT r.*, COALESCE(CONCAT(t.title, " / ", ts.title, " / ", sr.title), CONCAT(t2.title, " / ", tr.title)) source_title, CASE WHEN r.target_type = "product" THEN p.title ELSE cp.title END target_title FROM ai_recommendation_rules r LEFT JOIN test_scale_results sr ON sr.id = r.scale_result_id LEFT JOIN test_scales ts ON ts.id = sr.scale_id LEFT JOIN tests t ON t.id = ts.test_id LEFT JOIN test_results tr ON tr.id = r.test_result_id LEFT JOIN tests t2 ON t2.id = tr.test_id LEFT JOIN products p ON r.target_type = "product" AND p.id = r.target_id LEFT JOIN content_posts cp ON r.target_type = "content" AND cp.id = r.target_id ORDER BY r.id DESC')->fetchAll();
$scenarios = db()->query('SELECT * FROM ai_conversation_scenarios ORDER BY event_key, channel, priority DESC, id DESC')->fetchAll();
$channels = db()->query('SELECT * FROM ai_channel_media_rules ORDER BY FIELD(platform, "telegram","VK","MAX","OK","web")')->fetchAll();

require __DIR__ . '/../app/views/layouts/header.php';
?>
<div class="page-title-row"><div><h1>Контроль базы ИИ</h1><p class="cell-muted">Единая точка проверки Docsify, продуктов, результатов чек-апов, сценариев и связей рекомендаций.</p></div><a class="button secondary-button" href="ai_settings.php">Настройки ИИ</a></div>
<?php if ($notice !== ''): ?><div class="notice success"><?= h($notice) ?></div><?php endif; ?>
<?php foreach ($errors as $error): ?><div class="alert"><?= h($error) ?></div><?php endforeach; ?>

<section class="grid stats-grid">
    <article class="stat"><span>Продукты: утверждено / разрешено / всего</span><strong><?= $readiness['products_ready'] ?>/<?= $readiness['products_enabled'] ?>/<?= $readiness['products_total'] ?></strong></article>
    <article class="stat"><span>Диапазоны: утверждено / разрешено / всего</span><strong><?= $readiness['scale_results_ready'] ?>/<?= $readiness['scale_results_enabled'] ?>/<?= $readiness['scale_results_total'] ?></strong></article>
    <article class="stat"><span>Общие результаты: утверждено / разрешено / всего</span><strong><?= $readiness['single_results_ready'] ?>/<?= $readiness['single_results_enabled'] ?>/<?= $readiness['single_results_total'] ?></strong></article>
    <article class="stat"><span>Страницы Docsify в ИИ</span><strong><?= $readiness['docsify_active'] ?></strong></article>
    <article class="stat"><span>Правила рекомендаций</span><strong><?= $readiness['rules_active'] ?></strong></article>
    <article class="stat"><span>Сценарии</span><strong><?= $readiness['scenarios_active'] ?></strong></article>
    <article class="stat"><span>AI-профили специалистов</span><strong><?= $readiness['profiles_ready'] ?>/<?= $readiness['profiles_total'] ?></strong></article>
</section>

<section class="panel"><h2>Профили лидеров и консультантов</h2><p>Сведения о специалисте, стиль обращения, приветствие, запрещённые формулировки и правила передачи диалога заполняются каждым владельцем в разделе «Мой мини-сайт». Счётчик выше показывает профили, где заполнены основные сведения, стиль и правила передачи человеку.</p></section>

<section class="panel"><div class="page-title-row"><div><h2>Docsify → база знаний ИИ</h2><p class="cell-muted">Синхронизация читает Markdown из <code>docs/</code>. По умолчанию страницы доступны помощнику админки; клиентский доступ включается метаданными страницы.</p></div><form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="sync_docs"><button type="submit">Синхронизировать Docsify</button></form></div>
<p class="cell-muted">Служебные страницы, файлы <code>_*</code> и чек-лист наполнения в ответы не попадают.</p></section>

<section class="panel"><h2>Карточки продуктов</h2><p class="cell-muted">Состав, применение, предупреждения и противопоказания редактируются в разделе «Продукты». Здесь фиксируются источники, допустимые формулировки и допуск к ИИ.</p>
<?php foreach ($products as $row): ?><details class="faq-manage-item"><summary><strong><?= h((string)$row['title']) ?></strong><span class="cell-muted"><?= h(ai_control_status_label((string)$row['content_status'])) ?> · <?= (int)$row['ai_enabled'] ? 'доступен ИИ' : 'не используется ИИ' ?></span></summary><form method="post" class="crud-form"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save_product"><input type="hidden" name="product_id" value="<?= (int)$row['id'] ?>"><label class="field wide"><span>Допустимые обещания и формулировки</span><textarea name="allowed_claims" rows="3"><?= h((string)$row['allowed_claims']) ?></textarea></label><label class="field wide"><span>Первоисточники и утверждённые материалы</span><textarea name="source_urls" rows="3"><?= h((string)$row['source_urls']) ?></textarea></label><label class="field"><span>Статус</span><select name="content_status"><?php foreach (['draft'=>'Черновик','review'=>'На проверке','approved'=>'Утверждено'] as $key=>$label): ?><option value="<?= $key ?>" <?= $row['content_status']===$key?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label><label class="field"><span>Следующая проверка</span><input type="date" name="next_review_at" value="<?= h((string)$row['next_review_at']) ?>"></label><label class="check-row"><input type="checkbox" name="ai_enabled" value="1" <?= (int)$row['ai_enabled'] ? 'checked' : '' ?>><span>Разрешить использовать в ответах ИИ</span></label><div class="form-actions"><a class="button secondary-button" href="crud.php?module=products&action=edit&id=<?= (int)$row['id'] ?>">Открыть продукт</a><button type="submit">Сохранить контроль</button></div></form></details><?php endforeach; ?></section>

<section class="panel"><h2>Диапазоны шкал чек-апа</h2><p class="cell-muted">Объяснение и совет редактируются в конструкторе чек-апа. Здесь задаются исключения, передача человеку и источники.</p>
<?php foreach ($scaleResults as $row): ?><details class="faq-manage-item"><summary><strong><?= h($row['test_title'].' / '.$row['scale_title'].' / '.$row['title']) ?></strong><span class="cell-muted"><?= (int)$row['min_score'] ?>–<?= (int)$row['max_score'] ?> · <?= h(ai_control_status_label((string)$row['content_status'])) ?></span></summary><form method="post" class="crud-form"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save_scale_result"><input type="hidden" name="result_id" value="<?= (int)$row['id'] ?>"><label class="field wide"><span>Исключения и запреты</span><textarea name="exclusions_text" rows="2"><?= h((string)$row['exclusions_text']) ?></textarea></label><label class="field wide"><span>Когда передать диалог человеку</span><textarea name="escalation_text" rows="2"><?= h((string)$row['escalation_text']) ?></textarea></label><label class="field wide"><span>Первоисточники</span><textarea name="source_urls" rows="2"><?= h((string)$row['source_urls']) ?></textarea></label><label class="field"><span>Статус</span><select name="content_status"><?php foreach (['draft'=>'Черновик','review'=>'На проверке','approved'=>'Утверждено'] as $key=>$label): ?><option value="<?= $key ?>" <?= $row['content_status']===$key?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label><label class="field"><span>Следующая проверка</span><input type="date" name="next_review_at" value="<?= h((string)$row['next_review_at']) ?>"></label><label class="check-row"><input type="checkbox" name="ai_enabled" value="1" <?= (int)$row['ai_enabled']?'checked':'' ?>><span>Разрешить ИИ</span></label><div class="form-actions"><a class="button secondary-button" href="crud.php?module=tests&builder=<?= (int)$row['test_id'] ?>">Открыть конструктор</a><button type="submit">Сохранить</button></div></form></details><?php endforeach; ?>
<?php foreach ($testResults as $row): ?><details class="faq-manage-item"><summary><strong><?= h($row['test_title'].' / '.$row['title']) ?></strong><span class="cell-muted">общий результат · <?= (int)$row['min_score'] ?>–<?= (int)$row['max_score'] ?> · <?= h(ai_control_status_label((string)$row['content_status'])) ?></span></summary><form method="post" class="crud-form"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save_test_result"><input type="hidden" name="result_id" value="<?= (int)$row['id'] ?>"><label class="field wide"><span>Исключения и запреты</span><textarea name="exclusions_text" rows="2"><?= h((string)$row['exclusions_text']) ?></textarea></label><label class="field wide"><span>Когда передать диалог человеку</span><textarea name="escalation_text" rows="2"><?= h((string)$row['escalation_text']) ?></textarea></label><label class="field wide"><span>Первоисточники</span><textarea name="source_urls" rows="2"><?= h((string)$row['source_urls']) ?></textarea></label><label class="field"><span>Статус</span><select name="content_status"><?php foreach (['draft'=>'Черновик','review'=>'На проверке','approved'=>'Утверждено'] as $key=>$label): ?><option value="<?= $key ?>" <?= $row['content_status']===$key?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label><label class="field"><span>Следующая проверка</span><input type="date" name="next_review_at" value="<?= h((string)$row['next_review_at']) ?>"></label><label class="check-row"><input type="checkbox" name="ai_enabled" value="1" <?= (int)$row['ai_enabled']?'checked':'' ?>><span>Разрешить ИИ</span></label><div class="form-actions"><button type="submit">Сохранить</button></div></form></details><?php endforeach; ?></section>

<section class="panel"><h2>Связи «результат → продукт или материал»</h2><form method="post" class="crud-form"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save_rule"><label class="field"><span>Диапазон шкалы</span><select name="scale_result_id"><option value="0">Не выбран</option><?php foreach ($scaleResults as $row): ?><option value="<?= (int)$row['id'] ?>"><?= h($row['test_title'].' / '.$row['scale_title'].' / '.$row['title']) ?></option><?php endforeach; ?></select></label><label class="field"><span>Обычный результат</span><select name="test_result_id"><option value="0">Не выбран</option><?php foreach ($testResults as $row): ?><option value="<?= (int)$row['id'] ?>"><?= h($row['test_title'].' / '.$row['title']) ?></option><?php endforeach; ?></select></label><label class="field"><span>Цель</span><select name="target_type"><option value="product">Продукт</option><option value="content">Материал</option></select></label><label class="field"><span>Продукт</span><select name="product_target_id"><option value="0">Не выбран</option><?php foreach ($productOptions as $row): ?><option value="<?= (int)$row['id'] ?>"><?= h((string)$row['title']) ?></option><?php endforeach; ?></select></label><label class="field"><span>Материал</span><select name="content_target_id"><option value="0">Не выбран</option><?php foreach ($contentOptions as $row): ?><option value="<?= (int)$row['id'] ?>"><?= h((string)$row['title']) ?></option><?php endforeach; ?></select></label><label class="field"><span>Действие</span><select name="rule_type"><option value="include">Рекомендовать</option><option value="exclude">Исключить</option></select></label><label class="field"><span>Приоритет</span><input type="number" name="priority" value="100" min="1" max="1000"></label><label class="field wide"><span>Утверждённое объяснение связи</span><textarea name="rationale" rows="2"></textarea></label><label class="check-row"><input type="checkbox" name="is_active" value="1" checked><span>Правило активно</span></label><div class="form-actions"><button type="submit">Добавить правило</button></div></form>
<?php foreach ($rules as $row): ?><div class="faq-manage-item"><strong><?= h((string)$row['source_title']) ?></strong> → <?= h((string)$row['rule_type']) ?>: <?= h((string)($row['target_title'] ?: $row['target_type'].' #'.$row['target_id'])) ?><form method="post" class="inline-form" onsubmit="return confirm('Удалить правило?')"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="delete_rule"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="link-button danger">Удалить</button></form></div><?php endforeach; ?></section>

<section class="panel"><h2>Приветственные и разговорные сценарии</h2><p class="cell-muted">Переменные: <code>{{first_name}}</code>, <code>{{consultant_name}}</code>, <code>{{test_title}}</code>, <code>{{days}}</code>, <code>{{city}}</code>.</p><form method="post" class="crud-form"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save_scenario"><label class="field"><span>Название</span><input name="title" required></label><label class="field"><span>Событие</span><input name="event_key" placeholder="welcome, test_result, retest" required></label><label class="field"><span>Канал</span><select name="channel"><?php foreach (['any','admin','web','telegram','VK','OK','MAX'] as $channel): ?><option value="<?= h($channel) ?>"><?= h($channel) ?></option><?php endforeach; ?></select></label><label class="field"><span>Аудитория</span><select name="audience"><option value="client">Клиент</option><option value="admin">Админка</option></select></label><label class="field"><span>Приоритет</span><input type="number" name="priority" value="100"></label><label class="field wide"><span>Текст сценария</span><textarea name="template_text" rows="5" required></textarea></label><label class="check-row"><input type="checkbox" name="is_active" value="1" checked><span>Активен и утверждён</span></label><div class="form-actions"><button type="submit">Добавить сценарий</button></div></form>
<?php foreach ($scenarios as $row): ?><div class="faq-manage-item"><strong><?= h((string)$row['title']) ?></strong><span class="cell-muted"> <?= h((string)$row['event_key']) ?> · <?= h((string)$row['channel']) ?></span><p><?= nl2br(h((string)$row['template_text'])) ?></p><form method="post" class="inline-form" onsubmit="return confirm('Удалить сценарий?')"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="delete_scenario"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="link-button danger">Удалить</button></form></div><?php endforeach; ?></section>

<section class="panel"><h2>Правила доставки AI-видео</h2><?php foreach ($channels as $row): ?><details class="faq-manage-item"><summary><strong><?= h((string)$row['platform']) ?></strong><span class="cell-muted"><?= h((string)$row['delivery_mode']) ?> · <?= round((int)$row['max_file_bytes']/1048576) ?> МБ · <?= (int)$row['max_duration_seconds'] ?> сек.</span></summary><form method="post" class="crud-form"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save_channel"><input type="hidden" name="platform" value="<?= h((string)$row['platform']) ?>"><label class="field"><span>Способ доставки</span><select name="delivery_mode"><?php foreach (['native_video','video_message','link'] as $mode): ?><option value="<?= $mode ?>" <?= $row['delivery_mode']===$mode?'selected':'' ?>><?= $mode ?></option><?php endforeach; ?></select></label><label class="field"><span>Максимум, МБ</span><input type="number" name="max_file_mb" value="<?= max(1,round((int)$row['max_file_bytes']/1048576)) ?>"></label><label class="field"><span>Максимальная длина, сек.</span><input type="number" name="max_duration_seconds" value="<?= (int)$row['max_duration_seconds'] ?>"></label><label class="field"><span>MIME-типы</span><input name="allowed_mime_types" value="<?= h((string)$row['allowed_mime_types']) ?>"></label><label class="field"><span>Запасной вариант</span><select name="fallback_mode"><?php foreach (['native_video','link','text'] as $mode): ?><option value="<?= $mode ?>" <?= $row['fallback_mode']===$mode?'selected':'' ?>><?= $mode ?></option><?php endforeach; ?></select></label><label class="field wide"><span>Примечание после проверки API канала</span><textarea name="notes" rows="2"><?= h((string)$row['notes']) ?></textarea></label><label class="check-row"><input type="checkbox" name="is_active" value="1" <?= (int)$row['is_active']?'checked':'' ?>><span>Канал активен</span></label><div class="form-actions"><button type="submit">Сохранить</button></div></form></details><?php endforeach; ?></section>

<?php require __DIR__ . '/../app/views/layouts/footer.php'; ?>
