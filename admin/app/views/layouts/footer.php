        </section>
    </main>
</div>
<?php
require_once __DIR__ . '/../../core/ai_center.php';
$aiWidgetVisible = isset($admin) && ai_enabled() && (ai_entitlements_for_admin($admin)['text'] ?? false);
?>
<?php if ($aiWidgetVisible): ?>
<aside class="ai-chat-widget" data-ai-chat data-endpoint="ai_chat.php" data-csrf="<?= h(csrf_token()) ?>">
    <button type="button" class="ai-chat-toggle" data-ai-chat-toggle aria-expanded="false" aria-label="Открыть помощника SWPro">
        <span>AI</span><small>Помощник</small>
    </button>
    <section class="ai-chat-panel" data-ai-chat-panel hidden>
        <header><div><strong>Помощник SWPro</strong><small>Отвечает по материалам SWPro</small></div><button type="button" data-ai-chat-close aria-label="Закрыть">×</button></header>
        <div class="ai-chat-messages" data-ai-chat-messages>
            <div class="ai-chat-message assistant">Здравствуйте! Спросите, как выполнить нужное действие в админке.</div>
        </div>
        <form data-ai-chat-form>
            <textarea name="message" rows="2" maxlength="4000" placeholder="Например: как подключить VK?" required></textarea>
            <button type="submit">Отправить</button>
        </form>
    </section>
</aside>
<?php endif; ?>
<script src="assets/js/app.js?v=<?= (int)filemtime(__DIR__ . '/../../../public/assets/js/app.js') ?>"></script>
</body>
</html>
