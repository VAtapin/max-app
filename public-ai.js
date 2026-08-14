(() => {
    const root = document.querySelector('[data-public-ai]');
    if (!root) return;

    const dialog = root.querySelector('.public-ai-dialog');
    const messages = root.querySelector('.public-ai-messages');
    const form = root.querySelector('.public-ai-form');
    const input = form?.querySelector('textarea');
    const submit = form?.querySelector('button[type="submit"]');
    const openButtons = document.querySelectorAll('.public-ai-open');
    const closeButton = root.querySelector('.public-ai-close');
    const referralCode = root.dataset.referralCode || '';
    const storageKey = 'swpro_web_user_id';

    const setOpen = (open) => {
        dialog.hidden = !open;
        openButtons.forEach((button) => button.setAttribute('aria-expanded', open ? 'true' : 'false'));
        if (open) window.setTimeout(() => input?.focus(), 50);
    };

    const addMessage = (text, role = 'assistant') => {
        const item = document.createElement('div');
        item.className = `public-ai-message ${role}`;
        item.textContent = text;
        messages.appendChild(item);
        messages.scrollTop = messages.scrollHeight;
    };

    const webUserId = () => {
        let value = localStorage.getItem(storageKey);
        if (!value) {
            const suffix = globalThis.crypto?.randomUUID?.() || `${Date.now()}-${Math.random().toString(16).slice(2)}`;
            value = `web-${suffix}`;
            localStorage.setItem(storageKey, value);
        }
        return value;
    };

    openButtons.forEach((button) => button.addEventListener('click', () => setOpen(true)));
    closeButton?.addEventListener('click', () => setOpen(false));

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const question = input.value.trim();
        if (!question || submit.disabled) return;

        addMessage(question, 'user');
        input.value = '';
        submit.disabled = true;
        submit.textContent = 'Думаю…';
        try {
            const response = await fetch('/api/public_ai_chat.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({platform_user_id: webUserId(), referral_code: referralCode, message: question}),
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok || !result.ok) throw new Error(result.error || 'Не удалось получить ответ.');
            addMessage(result.answer || 'Не удалось сформировать ответ.');
        } catch (error) {
            addMessage(error.message || 'Помощник временно недоступен.', 'error');
        } finally {
            submit.disabled = false;
            submit.textContent = 'Отправить';
            input.focus();
        }
    });
})();
