<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/permissions.php';
require_once __DIR__ . '/../app/core/ai_center.php';

$admin = require_auth();
if (($admin['role'] ?? '') !== 'superadmin') {
    http_response_code(403);
    exit('Access denied');
}

$title = 'Настройки ИИ';
$errors = [];
$success = (string)($_GET['success'] ?? '');
$testMessage = '';
$submittedValues = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $values = [
        'ai.enabled' => isset($_POST['ai_enabled']) ? '1' : '0',
        'ai.external_processing_enabled' => isset($_POST['external_processing_enabled']) ? '1' : '0',
        'ai.studio_external_enabled' => isset($_POST['studio_external_enabled']) ? '1' : '0',
        'ai.voice_external_enabled' => isset($_POST['voice_external_enabled']) ? '1' : '0',
        'ai.text_provider' => trim((string)($_POST['text_provider'] ?? 'swpro')),
        'ai.text_model' => trim((string)($_POST['text_model'] ?? 'gpt-5-mini')),
        'ai.complex_model' => trim((string)($_POST['complex_model'] ?? 'gpt-5')),
        'ai.studio_model' => trim((string)($_POST['studio_model'] ?? 'gpt-5-mini')),
        'ai.smart_routing_enabled' => isset($_POST['smart_routing_enabled']) ? '1' : '0',
        'ai.complexity_threshold' => (string)max(3, min(10, (int)($_POST['complexity_threshold'] ?? 4))),
        'ai.standard_max_output_tokens' => (string)max(300, min(2000, (int)($_POST['standard_max_output_tokens'] ?? 700))),
        'ai.complex_max_output_tokens' => (string)max(500, min(3000, (int)($_POST['complex_max_output_tokens'] ?? 1100))),
        'ai.openai_tts_model' => trim((string)($_POST['openai_tts_model'] ?? 'gpt-4o-mini-tts')),
        'ai.openai_voice' => trim((string)($_POST['openai_voice'] ?? 'marin')),
        'ai.openai_voice_instructions' => trim((string)($_POST['openai_voice_instructions'] ?? '')),
        'ai.video_provider' => trim((string)($_POST['video_provider'] ?? 'disabled')),
        'ai.voice_provider' => trim((string)($_POST['voice_provider'] ?? 'disabled')),
        'ai.minimum_source_score' => (string)max(1, min(20, (int)($_POST['minimum_source_score'] ?? 2))),
        'ai.default_plan_days' => in_array((int)($_POST['default_plan_days'] ?? 7), [7, 14, 30], true) ? (string)(int)$_POST['default_plan_days'] : '7',
        'ai.retest_after_days' => (string)max(7, min(365, (int)($_POST['retest_after_days'] ?? 30))),
        'ai.inactive_after_days' => (string)max(3, min(365, (int)($_POST['inactive_after_days'] ?? 14))),
        'ai.retention.conversations_days' => (string)max(1, min(3650, (int)($_POST['retention_conversations_days'] ?? 365))),
        'ai.retention.drafts_days' => (string)max(1, min(3650, (int)($_POST['retention_drafts_days'] ?? 180))),
        'ai.retention.failed_jobs_days' => (string)max(1, min(3650, (int)($_POST['retention_failed_jobs_days'] ?? 30))),
        'ai.retention.ready_media_days' => (string)max(0, min(3650, (int)($_POST['retention_ready_media_days'] ?? 0))),
        'ai.retention.usage_days' => (string)max(30, min(3650, (int)($_POST['retention_usage_days'] ?? 1095))),
        'ai.docs_sync_enabled' => isset($_POST['docs_sync_enabled']) ? '1' : '0',
        'ai.admin_system_prompt' => trim((string)($_POST['admin_system_prompt'] ?? '')),
        'ai.client_system_prompt' => trim((string)($_POST['client_system_prompt'] ?? '')),
    ];
    $submittedValues = $values;
    if (!in_array($values['ai.text_provider'], ['swpro', 'openai'], true)) {
        $errors[] = 'Выберите корректного текстового провайдера.';
    }
    if (!in_array($values['ai.video_provider'], ['heygen', 'tavus', 'disabled'], true)) {
        $errors[] = 'Выберите корректного видеопровайдера.';
    }
    if (!in_array($values['ai.voice_provider'], ['openai', 'disabled'], true)) {
        $errors[] = 'Выберите корректного голосового провайдера.';
    }
    if (!preg_match('/^[a-zA-Z0-9._:-]{1,100}$/', $values['ai.text_model'])) {
        $errors[] = 'Укажите корректное название основной модели.';
    }
    if (!preg_match('/^[a-zA-Z0-9._:-]{1,100}$/', $values['ai.complex_model'])) {
        $errors[] = 'Укажите корректное название модели сложных ответов.';
    }
    if (!preg_match('/^[a-zA-Z0-9._:-]{1,100}$/', $values['ai.studio_model'])) {
        $errors[] = 'Укажите корректное название модели AI-студии.';
    }
    if (!preg_match('/^[a-zA-Z0-9._:-]{1,100}$/', $values['ai.openai_tts_model'])) {
        $errors[] = 'Укажите корректное название голосовой модели OpenAI.';
    }
    if (!in_array($values['ai.openai_voice'], ['alloy','ash','ballad','coral','echo','fable','nova','onyx','sage','shimmer','verse','marin','cedar'], true)) {
        $errors[] = 'Выберите корректный стандартный голос OpenAI.';
    }
    $action = (string)($_POST['action'] ?? 'save');
    if ($action === 'save' && $values['ai.text_provider'] === 'openai' && !ai_openai_key_configured()) {
        $errors[] = 'OpenAI нельзя включить: OPENAI_API_KEY не найден на сервере.';
    }
    if ($action === 'save' && $values['ai.text_provider'] === 'openai' && $values['ai.external_processing_enabled'] !== '1') {
        $errors[] = 'Для OpenAI подтвердите разрешение передачи необходимого рабочего контекста.';
    }
    if ($action === 'save' && ($values['ai.studio_external_enabled'] === '1' || $values['ai.voice_external_enabled'] === '1')
        && ($values['ai.external_processing_enabled'] !== '1' || !ai_openai_key_configured())) {
        $errors[] = 'Для AI-студии и голоса сначала разрешите внешнюю обработку и настройте OPENAI_API_KEY.';
    }
    if ($action === 'save' && $values['ai.video_provider'] !== 'disabled' && !ai_video_provider_configured($values['ai.video_provider'])) {
        $errors[] = 'Для выбранного видеопровайдера не найден ' . strtoupper($values['ai.video_provider']) . '_API_KEY на сервере.';
    }
    if (!$errors && in_array($action, ['test_openai', 'test_complex'], true)) {
        try {
            $testedLabel = $action === 'test_complex' ? 'Усиленная модель' : 'Основная модель';
            $testedModel = $action === 'test_complex' ? $values['ai.complex_model'] : $values['ai.text_model'];
            $test = ai_openai_test($testedModel);
            $testMessage = $testedLabel . ' OpenAI подключена. Модель ' . $test['model'] . ' ответила, использовано токенов: ' . $test['total_tokens'] . '.';
        } catch (Throwable $error) {
            $errors[] = $error->getMessage();
        }
    } elseif (!$errors) {
        $stmt = db()->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        foreach ($values as $key => $value) {
            $stmt->execute(['key' => $key, 'value' => $value]);
        }
        log_activity('admin', (int)$admin['id'], 'update_ai_settings', 'settings', null, [
            'enabled' => $values['ai.enabled'],
            'external_processing_enabled' => $values['ai.external_processing_enabled'],
            'text_provider' => $values['ai.text_provider'],
            'video_provider' => $values['ai.video_provider'],
        ]);
        redirect('ai_settings.php?success=saved');
    }
}

$settings = ai_settings(true);
if ($submittedValues !== null && in_array((string)($_POST['action'] ?? ''), ['test_openai', 'test_complex'], true)) {
    $settings = array_merge($settings, $submittedValues);
}
$value = static fn(string $key, string $default = ''): string => (string)($settings[$key] ?? $default);
$usage = [];
$textUsage = ['requests' => 0, 'standard' => 0, 'complex' => 0, 'input_tokens' => 0, 'output_tokens' => 0];
try {
    $usage = db()->query(
        'SELECT event_type, COUNT(*) events_count, COALESCE(SUM(quantity), 0) quantity, COALESCE(SUM(cost_amount), 0) cost_amount
         FROM ai_usage_events WHERE created_at >= DATE_FORMAT(NOW(), "%Y-%m-01") GROUP BY event_type ORDER BY event_type'
    )->fetchAll();
    $textRows = db()->query(
        'SELECT metadata_json FROM ai_usage_events
         WHERE event_type = "text" AND provider = "openai" AND created_at >= DATE_FORMAT(NOW(), "%Y-%m-01")'
    )->fetchAll();
    foreach ($textRows as $row) {
        $meta = json_decode((string)($row['metadata_json'] ?? ''), true);
        if (!is_array($meta)) {
            continue;
        }
        $textUsage['requests']++;
        $route = ($meta['route'] ?? 'standard') === 'complex' ? 'complex' : 'standard';
        $textUsage[$route]++;
        $textUsage['input_tokens'] += (int)($meta['input_tokens'] ?? 0);
        $textUsage['output_tokens'] += (int)($meta['output_tokens'] ?? 0);
    }
} catch (Throwable) {
    $usage = [];
}

require __DIR__ . '/../app/views/layouts/header.php';
?>
<div class="page-title-row">
    <div><h1>Настройки ИИ</h1><p class="cell-muted">Единые правила AI-центра SWPro, провайдеры и безопасная обработка данных.</p></div>
    <div class="form-actions"><a class="button secondary-button" href="ai_content_control.php">Контроль наполнения</a><a class="button secondary-button" href="ai_knowledge.php">База знаний</a></div>
</div>

<?php if ($success === 'saved'): ?><div class="notice success">Настройки ИИ сохранены.</div><?php endif; ?>
<?php if ($testMessage !== ''): ?><div class="notice success"><?= h($testMessage) ?></div><?php endif; ?>
<?php foreach ($errors as $error): ?><div class="alert"><?= h($error) ?></div><?php endforeach; ?>

<section class="grid stats-grid">
    <?php if ($usage): ?>
        <?php foreach ($usage as $row): ?>
            <article class="stat"><span><?= h((string)$row['event_type']) ?> за месяц</span><strong><?= h((string)$row['quantity']) ?></strong></article>
        <?php endforeach; ?>
    <?php else: ?>
        <article class="stat"><span>Использование за месяц</span><strong>0</strong></article>
    <?php endif; ?>
</section>

<?php if ($textUsage['requests'] > 0): ?>
<section class="grid stats-grid">
    <article class="stat"><span>OpenAI-запросы</span><strong><?= $textUsage['requests'] ?></strong></article>
    <article class="stat"><span>Экономичные / сложные</span><strong><?= $textUsage['standard'] ?> / <?= $textUsage['complex'] ?></strong></article>
    <article class="stat"><span>Токены вход / выход</span><strong><?= $textUsage['input_tokens'] ?> / <?= $textUsage['output_tokens'] ?></strong></article>
</section>
<?php endif; ?>

<form method="post" class="panel form-panel crud-form">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <label class="check-row wide"><input type="checkbox" name="ai_enabled" value="1" <?= $value('ai.enabled', '0') === '1' ? 'checked' : '' ?>><span><strong>Включить AI-центр</strong><small>Показывает текстового помощника всем пользователям админки. Видео, голос и другие дополнительные возможности регулируются тарифом.</small></span></label>

    <div class="<?= ai_openai_key_configured() ? 'notice success' : 'alert' ?> wide">
        <strong>Ключ OpenAI: <?= ai_openai_key_configured() ? 'найден на сервере' : 'не найден' ?>.</strong>
        <?= ai_openai_key_configured() ? 'Можно выполнить безопасную тестовую проверку.' : 'Добавьте OPENAI_API_KEY в deploy/plesk/live.env.' ?>
    </div>

    <label class="field">
        <span>Текстовый провайдер</span>
        <select name="text_provider">
            <option value="swpro" <?= $value('ai.text_provider', 'swpro') === 'swpro' ? 'selected' : '' ?>>Собственный поиск SWPro</option>
            <option value="openai" <?= $value('ai.text_provider') === 'openai' ? 'selected' : '' ?>>OpenAI (после отдельного разрешения)</option>
        </select>
    </label>
    <h2 class="wide">Экономичная маршрутизация</h2>
    <label class="check-row wide"><input type="checkbox" name="smart_routing_enabled" value="1" <?= $value('ai.smart_routing_enabled', '1') === '1' ? 'checked' : '' ?>><span><strong>Автоматически выбирать модель по сложности</strong><small>Обычные HELP- и продуктовые вопросы обслуживает экономичная модель. Усиленная модель используется только при объединении нескольких результатов, продуктов и ограничений.</small></span></label>
    <label class="field"><span>Основная экономичная модель</span><input name="text_model" value="<?= h($value('ai.text_model', 'gpt-5-mini')) ?>"><small class="field-hint">Используется для большинства коротких ответов.</small></label>
    <label class="field"><span>Модель сложных ответов</span><input name="complex_model" value="<?= h($value('ai.complex_model', 'gpt-5')) ?>"><small class="field-hint">Вызывается только при достижении порога сложности.</small></label>
    <label class="field"><span>Порог сложности</span><input type="number" min="3" max="10" name="complexity_threshold" value="<?= (int)$value('ai.complexity_threshold', '4') ?>"><small class="field-hint">4 — экономичный баланс. Чем выше, тем реже используется усиленная модель.</small></label>
    <label class="field"><span>Лимит обычного ответа, токенов</span><input type="number" min="300" max="2000" name="standard_max_output_tokens" value="<?= (int)$value('ai.standard_max_output_tokens', '700') ?>"></label>
    <label class="field"><span>Лимит сложного ответа, токенов</span><input type="number" min="500" max="3000" name="complex_max_output_tokens" value="<?= (int)$value('ai.complex_max_output_tokens', '1100') ?>"></label>
    <label class="field"><span>Модель AI-студии</span><input name="studio_model" value="<?= h($value('ai.studio_model', 'gpt-5-mini')) ?>"></label>
    <label class="field">
        <span>Видеоаватары</span>
        <select name="video_provider">
            <option value="disabled" <?= $value('ai.video_provider') === 'disabled' ? 'selected' : '' ?>>Выключены</option>
            <option value="heygen" <?= $value('ai.video_provider') === 'heygen' ? 'selected' : '' ?>>HeyGen</option>
            <option value="tavus" <?= $value('ai.video_provider') === 'tavus' ? 'selected' : '' ?>>Tavus</option>
        </select>
        <small class="field-hint">Выберите провайдера после добавления его серверного ключа.</small>
    </label>
    <div class="notice wide">
        <strong>Подключение видеоаватара</strong><br>
        HeyGen: <?= ai_video_provider_configured('heygen') ? '<strong>ключ найден</strong>' : 'нет <code>HEYGEN_API_KEY</code>' ?> ·
        Tavus: <?= ai_video_provider_configured('tavus') ? '<strong>ключ найден</strong>' : 'нет <code>TAVUS_API_KEY</code>' ?>.<br>
        Секретный ключ хранится только на сервере в <code>deploy/plesk/live.env</code> и никогда не показывается в браузере.
        <a href="/docs/#/ai/avatar" target="_blank" rel="noopener">Открыть пошаговую инструкцию</a>.
    </div>
    <label class="field"><span>Голосовые сообщения</span><select name="voice_provider"><option value="disabled" <?= $value('ai.voice_provider', 'disabled') === 'disabled' ? 'selected' : '' ?>>Выключены</option><option value="openai" <?= $value('ai.voice_provider') === 'openai' ? 'selected' : '' ?>>OpenAI Voice</option></select></label>
    <label class="field"><span>Модель голоса OpenAI</span><input name="openai_tts_model" value="<?= h($value('ai.openai_tts_model', 'gpt-4o-mini-tts')) ?>"></label>
    <label class="field"><span>Стандартный голос OpenAI</span><select name="openai_voice"><?php foreach (['marin','cedar','coral','alloy','ash','ballad','echo','fable','nova','onyx','sage','shimmer','verse'] as $voice): ?><option value="<?= h($voice) ?>" <?= $value('ai.openai_voice', 'marin') === $voice ? 'selected' : '' ?>><?= h($voice) ?><?= in_array($voice, ['marin', 'cedar'], true) ? ' — высокое качество' : '' ?></option><?php endforeach; ?></select><small class="field-hint">Для наиболее естественного звучания OpenAI рекомендует marin или cedar. Все голоса можно послушать на <a href="https://openai.fm" target="_blank" rel="noopener">OpenAI.fm</a> — там выберите русский текст и сравните варианты.</small></label>
    <label class="field wide"><span>Манера стандартного голоса</span><textarea name="openai_voice_instructions" rows="5"><?= h($value('ai.openai_voice_instructions', 'Говори по-русски как в личном голосовом сообщении знакомому человеку: тепло, живо и естественно. Избегай дикторской, рекламной и торжественной подачи. Используй разговорную интонацию, лёгкие изменения темпа и высоты голоса, короткие естественные паузы между мыслями. Не растягивай окончания и не делай одинаковые паузы после каждого предложения.')) ?></textarea><small class="field-hint">Инструкция применяется ко всем новым аудиосообщениям.</small></label>
    <label class="field"><span>Минимальная точность источника</span><input type="number" min="1" max="20" name="minimum_source_score" value="<?= (int)$value('ai.minimum_source_score', '2') ?>"><small class="field-hint">Чем выше значение, тем чаще помощник честно откажется отвечать.</small></label>
    <label class="field"><span>Стандартный план клиента</span><select name="default_plan_days"><?php foreach ([7,14,30] as $days): ?><option value="<?= $days ?>" <?= (int)$value('ai.default_plan_days', '7') === $days ? 'selected' : '' ?>><?= $days ?> дней</option><?php endforeach; ?></select></label>
    <label class="field"><span>Повторный чек-ап через, дней</span><input type="number" min="7" max="365" name="retest_after_days" value="<?= (int)$value('ai.retest_after_days', '30') ?>"></label>
    <label class="field"><span>Считать клиента неактивным через, дней</span><input type="number" min="3" max="365" name="inactive_after_days" value="<?= (int)$value('ai.inactive_after_days', '14') ?>"></label>
    <label class="check-row wide"><input type="checkbox" name="docs_sync_enabled" value="1" <?= $value('ai.docs_sync_enabled', '1') === '1' ? 'checked' : '' ?>><span><strong>Синхронизировать документацию с базой знаний ИИ</strong><small>Русский README и Markdown из docs остаются источниками HELP; копия для поиска ИИ обновляется автоматически при развёртывании или по команде из раздела «Контроль наполнения».</small></span></label>
    <h2 class="wide">Сроки хранения AI-данных</h2>
    <label class="field"><span>Диалоги, дней</span><input type="number" min="1" max="3650" name="retention_conversations_days" value="<?= (int)$value('ai.retention.conversations_days', '365') ?>"></label>
    <label class="field"><span>Архивные черновики, дней</span><input type="number" min="1" max="3650" name="retention_drafts_days" value="<?= (int)$value('ai.retention.drafts_days', '180') ?>"></label>
    <label class="field"><span>Ошибочные задания, дней</span><input type="number" min="1" max="3650" name="retention_failed_jobs_days" value="<?= (int)$value('ai.retention.failed_jobs_days', '30') ?>"></label>
    <label class="field"><span>Готовые MP3/MP4, дней</span><input type="number" min="0" max="3650" name="retention_ready_media_days" value="<?= (int)$value('ai.retention.ready_media_days', '0') ?>"><small class="field-hint">0 — хранить бессрочно.</small></label>
    <label class="field"><span>Статистика использования, дней</span><input type="number" min="30" max="3650" name="retention_usage_days" value="<?= (int)$value('ai.retention.usage_days', '1095') ?>"></label>

    <div class="alert wide">
        <strong>Передача в OpenAI контролируется настройками ниже.</strong><br>
        Помощник админки, клиентский помощник и AI-студия могут использовать утверждённые материалы и рабочий контекст. Для персонализации передаются имя, пол, возраст или дата рождения, город и содержание чек-апа. Контактные данные, точный адрес, внутренние и платформенные ID, логины и токены не передаются.
    </div>
    <label class="check-row wide"><input type="checkbox" name="external_processing_enabled" value="1" <?= $value('ai.external_processing_enabled', '0') === '1' ? 'checked' : '' ?>><span>Разрешить передачу необходимого рабочего контекста внешнему AI-провайдеру</span></label>
    <label class="check-row wide" id="openai-studio-access"><input type="checkbox" name="studio_external_enabled" value="1" <?= $value('ai.studio_external_enabled', '0') === '1' ? 'checked' : '' ?>><span><strong>Разрешить OpenAI создавать тексты в AI-студии</strong><small>После включения лидеры и консультанты смогут отправлять тему, утверждённый источник и выбранные сведения для персонализации. Контакты, точный адрес, ID, логины и токены исключаются.</small></span></label>
    <label class="check-row wide"><input type="checkbox" name="voice_external_enabled" value="1" <?= $value('ai.voice_external_enabled', '0') === '1' ? 'checked' : '' ?>><span><strong>Разрешить внешний синтез голоса</strong><small>Передаётся только проверенный вручную сценарий после нажатия кнопки создания аудио.</small></span></label>

    <label class="field wide"><span>Правила помощника админки</span><textarea name="admin_system_prompt" rows="5"><?= h($value('ai.admin_system_prompt')) ?></textarea></label>
    <label class="field wide"><span>Правила помощника клиентов</span><textarea name="client_system_prompt" rows="5"><?= h($value('ai.client_system_prompt')) ?></textarea></label>
    <div class="form-actions">
        <button type="submit" name="action" value="save">Сохранить</button>
        <button type="submit" name="action" value="test_openai" class="secondary-button">Проверить основную модель</button>
        <button type="submit" name="action" value="test_complex" class="secondary-button">Проверить усиленную модель</button>
    </div>
</form>
<?php require __DIR__ . '/../app/views/layouts/footer.php'; ?>
