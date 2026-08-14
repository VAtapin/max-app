<?php

require_once __DIR__ . '/ai_center.php';

function ai_docs_root(): string
{
    return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'docs';
}

function ai_docs_parse(string $markdown, string $fallbackTitle): array
{
    $meta = [];
    if (preg_match('/\A---\R(.*?)\R---\R/s', $markdown, $match)) {
        foreach (preg_split('/\R/', $match[1]) ?: [] as $line) {
            if (preg_match('/^([a-z0-9_-]+)\s*:\s*(.*?)\s*$/i', $line, $parts)) {
                $meta[strtolower($parts[1])] = trim($parts[2], " \t\n\r\0\x0B\"'");
            }
        }
        $markdown = substr($markdown, strlen($match[0]));
    }
    preg_match('/^#\s+(.+)$/m', $markdown, $heading);
    $title = trim((string)($meta['ai_title'] ?? ($heading[1] ?? $fallbackTitle)));
    $content = preg_replace('/```.*?```/s', ' ', $markdown) ?? $markdown;
    $content = preg_replace('/!\[[^\]]*\]\([^)]*\)/', ' ', $content) ?? $content;
    $content = preg_replace('/\[([^\]]+)\]\([^)]*\)/', '$1', $content) ?? $content;
    $content = preg_replace('/<[^>]+>/', ' ', $content) ?? $content;
    $content = preg_replace('/^[#>*_-]+\s*/m', '', $content) ?? $content;
    $content = preg_replace('/[ \t]+/', ' ', $content) ?? $content;
    $content = preg_replace('/\R{3,}/', "\n\n", $content) ?? $content;
    return [
        'title' => $title !== '' ? $title : $fallbackTitle,
        'content' => trim($content),
        'audience' => in_array(($meta['ai_audience'] ?? 'admin'), ['admin', 'client', 'both'], true) ? $meta['ai_audience'] : 'admin',
        'keywords' => (string)($meta['ai_keywords'] ?? ''),
        'page_context' => (string)($meta['ai_page_context'] ?? ''),
        'enabled' => !in_array(strtolower((string)($meta['ai_enabled'] ?? 'true')), ['0', 'false', 'no', 'off'], true),
    ];
}

function ai_docs_sync(?int $adminId = null): array
{
    if (ai_setting('ai.docs_sync_enabled', '1') !== '1') {
        throw new RuntimeException('Синхронизация Docsify выключена в настройках ИИ.');
    }
    $root = realpath(ai_docs_root());
    if (!$root || !is_dir($root)) {
        throw new RuntimeException('Каталог Docsify не найден.');
    }
    $seen = [];
    $created = 0;
    $updated = 0;
    $unchanged = 0;
    $disabled = 0;
    $select = db()->prepare('SELECT id, content_hash, is_active FROM ai_knowledge_entries WHERE owner_type = "superadmin" AND owner_id = 0 AND source_type = "docsify" AND source_key = :source_key LIMIT 1');
    $insert = db()->prepare('INSERT INTO ai_knowledge_entries (owner_type, owner_id, audience, source_type, source_key, source_url, title, content, content_hash, keywords, page_context, is_approved, is_active, version, approved_by, approved_at, last_synced_at, created_by) VALUES ("superadmin", 0, :audience, "docsify", :source_key, :source_url, :title, :content, :content_hash, :keywords, :page_context, 1, :is_active, 1, :admin_id, NOW(), NOW(), :admin_id)');
    $update = db()->prepare('UPDATE ai_knowledge_entries SET audience = :audience, source_url = :source_url, title = :title, content = :content, content_hash = :content_hash, keywords = :keywords, page_context = :page_context, is_approved = 1, is_active = :is_active, version = version + 1, approved_by = :admin_id, approved_at = NOW(), last_synced_at = NOW() WHERE id = :id');
    $touch = db()->prepare('UPDATE ai_knowledge_entries SET last_synced_at = NOW(), is_active = :is_active WHERE id = :id');
    db()->beginTransaction();
    try {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'md') {
                continue;
            }
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            if (str_starts_with($relative, '_assets/') || str_starts_with(basename($relative), '_') || $relative === 'AI_CONTENT_CHECKLIST.md') {
                continue;
            }
            $markdown = file_get_contents($file->getPathname());
            if ($markdown === false) {
                continue;
            }
            $parsed = ai_docs_parse($markdown, pathinfo($relative, PATHINFO_FILENAME));
            if ($parsed['content'] === '') {
                continue;
            }
            $hash = hash('sha256', json_encode($parsed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $sourceUrl = '/docs/#/' . preg_replace('/(?:\/README)?\.md$/', '', $relative);
            $select->execute(['source_key' => $relative]);
            $existing = $select->fetch() ?: null;
            $payload = [
                'audience' => $parsed['audience'],
                'source_key' => $relative,
                'source_url' => $sourceUrl,
                'title' => $parsed['title'],
                'content' => $parsed['content'],
                'content_hash' => $hash,
                'keywords' => $parsed['keywords'],
                'page_context' => $parsed['page_context'],
                'is_active' => $parsed['enabled'] ? 1 : 0,
                'admin_id' => $adminId,
            ];
            $seen[] = $relative;
            if (!$existing) {
                $insert->execute($payload);
                $created++;
            } elseif (hash_equals((string)$existing['content_hash'], $hash)) {
                $touch->execute(['id' => (int)$existing['id'], 'is_active' => $payload['is_active']]);
                $unchanged++;
            } else {
                $update->execute($payload + ['id' => (int)$existing['id']]);
                $updated++;
            }
        }
        $all = db()->query('SELECT id, source_key FROM ai_knowledge_entries WHERE owner_type = "superadmin" AND owner_id = 0 AND source_type = "docsify" AND is_active = 1')->fetchAll();
        $deactivate = db()->prepare('UPDATE ai_knowledge_entries SET is_active = 0, last_synced_at = NOW() WHERE id = :id');
        foreach ($all as $row) {
            if (!in_array((string)$row['source_key'], $seen, true)) {
                $deactivate->execute(['id' => (int)$row['id']]);
                $disabled++;
            }
        }
        db()->commit();
    } catch (Throwable $error) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        throw $error;
    }
    return compact('created', 'updated', 'unchanged', 'disabled');
}

function ai_content_readiness(): array
{
    $queries = [
        'products_total' => 'SELECT COUNT(*) FROM products WHERE is_deleted = 0',
        'products_ready' => 'SELECT COUNT(*) FROM products WHERE is_deleted = 0 AND ai_enabled = 1 AND content_status = "approved" AND composition IS NOT NULL AND usage_text IS NOT NULL AND warning_text IS NOT NULL AND contraindications IS NOT NULL AND allowed_claims IS NOT NULL AND source_urls IS NOT NULL',
        'scale_results_total' => 'SELECT COUNT(*) FROM test_scale_results',
        'scale_results_ready' => 'SELECT COUNT(*) FROM test_scale_results WHERE ai_enabled = 1 AND content_status = "approved" AND summary_text IS NOT NULL AND advice_text IS NOT NULL AND source_urls IS NOT NULL',
        'single_results_total' => 'SELECT COUNT(*) FROM test_results',
        'single_results_ready' => 'SELECT COUNT(*) FROM test_results WHERE ai_enabled = 1 AND content_status = "approved" AND summary_text IS NOT NULL AND advice_text IS NOT NULL AND source_urls IS NOT NULL',
        'docsify_active' => 'SELECT COUNT(*) FROM ai_knowledge_entries WHERE source_type = "docsify" AND is_active = 1 AND is_approved = 1',
        'rules_active' => 'SELECT COUNT(*) FROM ai_recommendation_rules WHERE is_active = 1 AND is_approved = 1',
        'scenarios_active' => 'SELECT COUNT(*) FROM ai_conversation_scenarios WHERE is_active = 1 AND is_approved = 1',
        'profiles_total' => 'SELECT COUNT(*) FROM consultant_profiles',
        'profiles_ready' => 'SELECT COUNT(*) FROM consultant_profiles WHERE COALESCE(display_name, "") <> "" AND (COALESCE(short_description, "") <> "" OR COALESCE(bio, "") <> "") AND COALESCE(ai_greeting_style, "") <> "" AND COALESCE(ai_persona_notes, "") <> "" AND COALESCE(ai_handoff_rules, "") <> ""',
    ];
    $result = [];
    foreach ($queries as $key => $sql) {
        $result[$key] = (int)db()->query($sql)->fetchColumn();
    }
    return $result;
}

function ai_docs_pending_summary(): array
{
    $root = realpath(ai_docs_root());
    if (!$root || !is_dir($root)) {
        return ['changed' => 0, 'missing' => 0, 'error' => 'Каталог Docsify не найден'];
    }
    $stored = [];
    foreach (db()->query('SELECT source_key, content_hash FROM ai_knowledge_entries WHERE source_type = "docsify" AND is_active = 1')->fetchAll() as $row) {
        $stored[(string)$row['source_key']] = (string)$row['content_hash'];
    }
    $seen = [];
    $changed = 0;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'md') {
            continue;
        }
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        if (str_starts_with($relative, '_assets/') || str_starts_with(basename($relative), '_') || $relative === 'AI_CONTENT_CHECKLIST.md') {
            continue;
        }
        $markdown = file_get_contents($file->getPathname());
        if ($markdown === false) {
            continue;
        }
        $parsed = ai_docs_parse($markdown, pathinfo($relative, PATHINFO_FILENAME));
        if ($parsed['content'] === '') {
            continue;
        }
        $seen[$relative] = true;
        $hash = hash('sha256', json_encode($parsed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        if (!isset($stored[$relative]) || !hash_equals($stored[$relative], $hash)) {
            $changed++;
        }
    }
    return ['changed' => $changed, 'missing' => count(array_diff_key($stored, $seen)), 'error' => null];
}

function ai_superadmin_tasks(): array
{
    $tasks = [];
    $add = static function (string $title, string $reason, string $description, string $href, int $priority = 50) use (&$tasks): void {
        $tasks[] = compact('title', 'reason', 'description', 'href', 'priority');
    };
    if (!ai_enabled()) {
        $add('Проверить и включить AI-центр', 'AI-центр сейчас выключен', 'Проверьте провайдера, согласия и правила обработки, затем включите AI-центр, когда база будет готова.', 'ai_settings.php', 100);
    }
    $readiness = ai_content_readiness();
    $productsPending = max(0, $readiness['products_total'] - $readiness['products_ready']);
    if ($productsPending > 0) {
        $add('Проверить карточки продуктов', 'Не готовы для ИИ: ' . $productsPending, 'Заполните обязательные сведения и источники, затем отдельно утвердите карточки для использования в клиентских ответах.', 'ai_content_control.php', 90);
    }
    $resultsPending = max(0, ($readiness['scale_results_total'] + $readiness['single_results_total']) - ($readiness['scale_results_ready'] + $readiness['single_results_ready']));
    if ($resultsPending > 0) {
        $add('Проверить результаты чек-апов', 'Не готовы для ИИ: ' . $resultsPending, 'Для каждого диапазона нужны утверждённое объяснение, совет, ограничения и первоисточники.', 'ai_content_control.php', 90);
    }
    $profilesPending = max(0, $readiness['profiles_total'] - $readiness['profiles_ready']);
    if ($profilesPending > 0) {
        $add('Заполнить AI-профили специалистов', 'Неполных профилей: ' . $profilesPending, 'Попросите лидеров и консультантов заполнить сведения, стиль общения и правила передачи диалога человеку.', 'ai_content_control.php', 70);
    }
    $docs = ai_docs_pending_summary();
    if ($docs['error']) {
        $add('Проверить Docsify', 'Справка недоступна', (string)$docs['error'], 'ai_content_control.php', 95);
    } elseif ($docs['changed'] > 0 || $docs['missing'] > 0) {
        $add('Синхронизировать Docsify', 'Изменено или добавлено: ' . $docs['changed'] . '; удалено: ' . $docs['missing'], 'Обновите поисковую копию HELP, чтобы помощник отвечал по актуальной документации.', 'ai_content_control.php', 95);
    }
    if (($readiness['scale_results_total'] + $readiness['single_results_total']) > 0 && $readiness['rules_active'] === 0) {
        $add('Добавить правила рекомендаций', 'Нет активных связей результатов', 'Свяжите утверждённые результаты чек-апа с подходящими материалами или продуктами и задайте исключения.', 'ai_content_control.php', 65);
    }
    if ($readiness['scenarios_active'] === 0) {
        $add('Добавить разговорные сценарии', 'Нет активных сценариев', 'Подготовьте приветствие и основные события общения. До этого система использует безопасные стандартные тексты.', 'ai_content_control.php', 60);
    }
    $overdue = (int)db()->query('SELECT (SELECT COUNT(*) FROM products WHERE is_deleted = 0 AND next_review_at IS NOT NULL AND next_review_at < CURRENT_DATE) + (SELECT COUNT(*) FROM test_scale_results WHERE next_review_at IS NOT NULL AND next_review_at < CURRENT_DATE) + (SELECT COUNT(*) FROM test_results WHERE next_review_at IS NOT NULL AND next_review_at < CURRENT_DATE)')->fetchColumn();
    if ($overdue > 0) {
        $add('Перепроверить устаревшие материалы', 'Просрочена проверка: ' . $overdue, 'Сверьте содержание с первоисточниками и назначьте следующую дату проверки.', 'ai_content_control.php', 85);
    }
    $textProvider = ai_setting('ai.text_provider', 'swpro');
    if ($textProvider === 'openai' && !ai_openai_key_configured()) {
        $add('Подключить OpenAI', 'Выбран провайдер без серверного ключа', 'Добавьте OPENAI_API_KEY в защищённую серверную конфигурацию и выполните тест подключения.', 'ai_settings.php', 100);
    }
    $videoProvider = ai_setting('ai.video_provider', 'disabled');
    if ($videoProvider !== 'disabled' && !ai_video_provider_configured((string)$videoProvider)) {
        $add('Подключить видеопровайдера', 'Не найден ключ ' . strtoupper((string)$videoProvider), 'Добавьте ключ в защищённую серверную конфигурацию и проверьте подключение.', 'ai_settings.php', 100);
    }
    usort($tasks, static fn(array $a, array $b): int => $b['priority'] <=> $a['priority']);
    return $tasks;
}

function ai_render_scenario(string $eventKey, string $channel, array $owner, array $variables, string $audience = 'client'): ?string
{
    $stmt = db()->prepare('SELECT template_text FROM ai_conversation_scenarios WHERE event_key = :event_key AND audience = :audience AND is_active = 1 AND is_approved = 1 AND channel IN (:channel, "any") AND ((owner_type = :owner_type AND owner_id = :owner_id) OR (owner_type = "superadmin" AND owner_id = 0)) ORDER BY (owner_type = :owner_type_order AND owner_id = :owner_id_order) DESC, (channel = :channel_order) DESC, priority DESC, id DESC LIMIT 1');
    $stmt->execute([
        'event_key' => $eventKey, 'audience' => $audience, 'channel' => $channel,
        'owner_type' => $owner['owner_type'], 'owner_id' => $owner['owner_id'],
        'owner_type_order' => $owner['owner_type'], 'owner_id_order' => $owner['owner_id'], 'channel_order' => $channel,
    ]);
    $template = $stmt->fetchColumn();
    if (!is_string($template) || trim($template) === '') {
        return null;
    }
    $safe = [];
    foreach (['first_name', 'consultant_name', 'test_title', 'days', 'city'] as $key) {
        $safe['{{' . $key . '}}'] = trim((string)($variables[$key] ?? ''));
    }
    return trim(strtr($template, $safe));
}

function ai_rules_for_session(int $testSessionId): array
{
    $stmt = db()->prepare('SELECT DISTINCT r.* FROM ai_recommendation_rules r WHERE r.is_active = 1 AND r.is_approved = 1 AND (r.scale_result_id IN (SELECT uss.result_id FROM user_test_scale_scores uss JOIN test_scale_results sr ON sr.id = uss.result_id AND sr.ai_enabled = 1 AND sr.content_status = "approved" WHERE uss.session_id = :scale_session AND uss.result_id IS NOT NULL) OR r.test_result_id IN (SELECT tr.id FROM user_test_sessions uts JOIN test_results tr ON tr.test_id = uts.test_id AND tr.min_score <= uts.total_score AND tr.max_score >= uts.total_score AND tr.ai_enabled = 1 AND tr.content_status = "approved" WHERE uts.id = :test_session)) ORDER BY r.rule_type = "exclude" ASC, r.priority DESC, r.id');
    $stmt->execute(['scale_session' => $testSessionId, 'test_session' => $testSessionId]);
    return $stmt->fetchAll();
}

function ai_apply_recommendation_rules(int $endUserId, int $testSessionId): void
{
    foreach (ai_rules_for_session($testSessionId) as $rule) {
        if ($rule['target_type'] !== 'product') {
            continue;
        }
        $productId = (int)$rule['target_id'];
        if ($rule['rule_type'] === 'exclude') {
            db()->prepare('DELETE FROM recommendations WHERE test_session_id = :session_id AND product_id = :product_id')->execute(['session_id' => $testSessionId, 'product_id' => $productId]);
            continue;
        }
        $exists = db()->prepare('SELECT id FROM recommendations WHERE test_session_id = :session_id AND product_id = :product_id LIMIT 1');
        $exists->execute(['session_id' => $testSessionId, 'product_id' => $productId]);
        if (!$exists->fetchColumn()) {
            db()->prepare('INSERT INTO recommendations (end_user_id, test_session_id, product_id, reason_text, score) VALUES (:user_id, :session_id, :product_id, :reason, :score)')->execute([
                'user_id' => $endUserId, 'session_id' => $testSessionId, 'product_id' => $productId,
                'reason' => $rule['rationale'], 'score' => (int)$rule['priority'],
            ]);
        }
    }
}

function ai_rule_materials_for_session(int $testSessionId): array
{
    $included = [];
    $excluded = [];
    foreach (ai_rules_for_session($testSessionId) as $rule) {
        if ($rule['target_type'] !== 'content') {
            continue;
        }
        $id = (int)$rule['target_id'];
        if ($rule['rule_type'] === 'exclude') {
            $excluded[$id] = true;
        } else {
            $included[$id] = (string)($rule['rationale'] ?? '');
        }
    }
    $ids = array_values(array_filter(array_keys($included), static fn(int $id): bool => !isset($excluded[$id])));
    if (!$ids) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare('SELECT id, title, short_text, button_url, attachment_path, video_url FROM content_posts WHERE id IN (' . $placeholders . ') AND status = "published" AND is_deleted = 0 ORDER BY FIELD(id, ' . $placeholders . ')');
    $stmt->execute(array_merge($ids, $ids));
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['reason_text'] = $included[(int)$row['id']] ?? '';
    }
    unset($row);
    return $rows;
}

function ai_cleanup_data(bool $dryRun = false): array
{
    $days = static fn(string $key, int $default): int => max(1, (int)(ai_setting($key, (string)$default) ?? $default));
    $targets = [
        ['ai_conversations', 'updated_at', $days('ai.retention.conversations_days', 365), null],
        ['ai_content_drafts', 'updated_at', $days('ai.retention.drafts_days', 180), 'status IN ("used","archived")'],
        ['ai_voice_jobs', 'created_at', $days('ai.retention.failed_jobs_days', 30), 'status IN ("failed","cancelled")'],
        ['ai_video_jobs', 'created_at', $days('ai.retention.failed_jobs_days', 30), 'status IN ("failed","cancelled")'],
        ['ai_usage_events', 'created_at', $days('ai.retention.usage_days', 1095), null],
    ];
    $result = [];
    foreach ($targets as [$table, $column, $retentionDays, $extra]) {
        $where = $column . ' < DATE_SUB(NOW(), INTERVAL ' . $retentionDays . ' DAY)' . ($extra ? ' AND ' . $extra : '');
        $count = (int)db()->query('SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $where)->fetchColumn();
        if (!$dryRun && $count > 0) {
            db()->exec('DELETE FROM ' . $table . ' WHERE ' . $where);
        }
        $result[$table] = ['matched' => $count, 'deleted' => $dryRun ? 0 : $count, 'retention_days' => $retentionDays];
    }
    $mediaDays = max(0, (int)(ai_setting('ai.retention.ready_media_days', '0') ?? 0));
    $mediaMatched = 0;
    $mediaDeleted = 0;
    if ($mediaDays > 0) {
        $configuredRoot = trim((string)(getenv('SWPRO_PRIVATE_STORAGE_PATH') ?: ''));
        $root = rtrim(str_replace('\\', '/', $configuredRoot !== '' ? $configuredRoot : dirname(__DIR__, 3) . '/storage/private'), '/');
        foreach (['ai_voice_jobs', 'ai_video_jobs'] as $table) {
            $rows = db()->query('SELECT id, output_path FROM ' . $table . ' WHERE status = "ready" AND completed_at < DATE_SUB(NOW(), INTERVAL ' . $mediaDays . ' DAY) AND output_path IS NOT NULL')->fetchAll();
            $mediaMatched += count($rows);
            if ($dryRun) {
                continue;
            }
            $delete = db()->prepare('DELETE FROM ' . $table . ' WHERE id = :id AND status = "ready"');
            foreach ($rows as $row) {
                $relative = ltrim(str_replace('\\', '/', (string)$row['output_path']), '/');
                if ($relative !== '' && !str_contains($relative, '..')) {
                    $path = $root . '/' . $relative;
                    if (is_file($path)) {
                        @unlink($path);
                    }
                }
                $delete->execute(['id' => (int)$row['id']]);
                $mediaDeleted++;
            }
        }
    }
    $result['ready_media'] = ['matched' => $mediaMatched, 'deleted' => $dryRun ? 0 : $mediaDeleted, 'retention_days' => $mediaDays];
    return $result;
}
