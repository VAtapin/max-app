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
        'ai.text_provider' => trim((string)($_POST['text_provider'] ?? 'swpro')),
        'ai.text_model' => trim((string)($_POST['text_model'] ?? 'gpt-5-mini')),
        'ai.complex_model' => trim((string)($_POST['complex_model'] ?? 'gpt-5')),
        'ai.video_provider' => trim((string)($_POST['video_provider'] ?? 'disabled')),
        'ai.voice_provider' => trim((string)($_POST['voice_provider'] ?? 'disabled')),
        'ai.minimum_source_score' => (string)max(1, min(20, (int)($_POST['minimum_source_score'] ?? 2))),
        'ai.default_plan_days' => in_array((int)($_POST['default_plan_days'] ?? 7), [7, 14, 30], true) ? (string)(int)$_POST['default_plan_days'] : '7',
        'ai.retest_after_days' => (string)max(7, min(365, (int)($_POST['retest_after_days'] ?? 30))),
        'ai.inactive_after_days' => (string)max(3, min(365, (int)($_POST['inactive_after_days'] ?? 14))),
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
    if (!in_array($values['ai.voice_provider'], ['openai', 'elevenlabs', 'disabled'], true)) {
        $errors[] = 'Выберите корректного голосового провайдера.';
    }
    if (!preg_match('/^[a-zA-Z0-9._:-]{1,100}$/', $values['ai.text_model'])) {
        $errors[] = 'Укажите корректное название основной модели.';
    }
    if (!preg_match('/^[a-zA-Z0-9._:-]{1,100}$/', $values['ai.complex_model'])) {
        $errors[] = 'Укажите корректное название модели сложных ответов.';
    }
    $action = (string)($_POST['action'] ?? 'save');
    if ($action === 'save' && $values['ai.text_provider'] === 'openai' && !ai_openai_key_configured()) {
        $errors[] = 'OpenAI нельзя включить: OPENAI_API_KEY не найден на сервере.';
    }
    if ($action === 'save' && $values['ai.text_provider'] === 'openai' && $values['ai.external_processing_enabled'] !== '1') {
        $errors[] = 'Для OpenAI подтвердите разрешение внешней обработки обезличенных материалов.';
    }
    if (!$errors && $action === 'test_openai') {
        try {
            $test = ai_openai_test($values['ai.text_model']);
            $testMessage = 'OpenAI подключён. Модель ' . $test['model'] . ' ответила, использовано токенов: ' . $test['total_tokens'] . '.';
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
if ($submittedValues !== null && (string)($_POST['action'] ?? '') === 'test_openai') {
    $settings = array_merge($settings, $submittedValues);
}
$value = static fn(string $key, string $default = ''): string => (string)($settings[$key] ?? $default);
$usage = [];
try {
    $usage = db()->query(
        'SELECT event_type, COUNT(*) events_count, COALESCE(SUM(quantity), 0) quantity, COALESCE(SUM(cost_amount), 0) cost_amount
         FROM ai_usage_events WHERE created_at >= DATE_FORMAT(NOW(), "%Y-%m-01") GROUP BY event_type ORDER BY event_type'
    )->fetchAll();
} catch (Throwable) {
    $usage = [];
}

require __DIR__ . '/../app/views/layouts/header.php';
?>
<div class="page-title-row">
    <div><h1>Настройки ИИ</h1><p class="cell-muted">Единые правила AI-центра SWPro, провайдеры и безопасная обработка данных.</p></div>
    <a class="button secondary-button" href="ai_knowledge.php">База знаний</a>
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
    <label class="field"><span>Основная модель</span><input name="text_model" value="<?= h($value('ai.text_model', 'gpt-5-mini')) ?>"></label>
    <label class="field"><span>Модель сложных ответов</span><input name="complex_model" value="<?= h($value('ai.complex_model', 'gpt-5')) ?>"></label>
    <label class="field">
        <span>Видеоаватары</span>
        <select name="video_provider">
            <option value="disabled" <?= $value('ai.video_provider') === 'disabled' ? 'selected' : '' ?>>Выключены</option>
            <option value="heygen" <?= $value('ai.video_provider') === 'heygen' ? 'selected' : '' ?>>HeyGen</option>
            <option value="tavus" <?= $value('ai.video_provider') === 'tavus' ? 'selected' : '' ?>>Tavus</option>
        </select>
    </label>
    <label class="field"><span>Голосовые сообщения</span><select name="voice_provider"><option value="disabled" <?= $value('ai.voice_provider', 'disabled') === 'disabled' ? 'selected' : '' ?>>Выключены</option><option value="openai" <?= $value('ai.voice_provider') === 'openai' ? 'selected' : '' ?>>OpenAI Voice</option><option value="elevenlabs" <?= $value('ai.voice_provider') === 'elevenlabs' ? 'selected' : '' ?>>ElevenLabs</option></select></label>
    <label class="field"><span>Минимальная точность источника</span><input type="number" min="1" max="20" name="minimum_source_score" value="<?= (int)$value('ai.minimum_source_score', '2') ?>"><small class="field-hint">Чем выше значение, тем чаще помощник честно откажется отвечать.</small></label>
    <label class="field"><span>Стандартный план клиента</span><select name="default_plan_days"><?php foreach ([7,14,30] as $days): ?><option value="<?= $days ?>" <?= (int)$value('ai.default_plan_days', '7') === $days ? 'selected' : '' ?>><?= $days ?> дней</option><?php endforeach; ?></select></label>
    <label class="field"><span>Повторный чек-ап через, дней</span><input type="number" min="7" max="365" name="retest_after_days" value="<?= (int)$value('ai.retest_after_days', '30') ?>"></label>
    <label class="field"><span>Считать клиента неактивным через, дней</span><input type="number" min="3" max="365" name="inactive_after_days" value="<?= (int)$value('ai.inactive_after_days', '14') ?>"></label>

    <div class="alert wide">
        <strong>Внешняя обработка данных по умолчанию выключена.</strong><br>
        На первом этапе OpenAI используется только помощником админки. Клиентские вопросы, анкеты, результаты опросов, история клиента и профиль во внешний сервис не передаются.
    </div>
    <label class="check-row wide"><input type="checkbox" name="external_processing_enabled" value="1" <?= $value('ai.external_processing_enabled', '0') === '1' ? 'checked' : '' ?>><span>Разрешить внешний провайдер для отдельно утверждённых обезличенных материалов</span></label>

    <label class="field wide"><span>Правила помощника админки</span><textarea name="admin_system_prompt" rows="5"><?= h($value('ai.admin_system_prompt')) ?></textarea></label>
    <label class="field wide"><span>Правила помощника клиентов</span><textarea name="client_system_prompt" rows="5"><?= h($value('ai.client_system_prompt')) ?></textarea></label>
    <div class="form-actions">
        <button type="submit" name="action" value="save">Сохранить</button>
        <button type="submit" name="action" value="test_openai" class="secondary-button">Проверить OpenAI</button>
    </div>
</form>
<?php require __DIR__ . '/../app/views/layouts/footer.php'; ?>
