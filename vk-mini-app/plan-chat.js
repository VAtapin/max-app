(() => {
    const sentKey = (itemId) => `swpro_plan_chat_sent_${itemId}`;

    function showStatus(message, isError = false) {
        const existing = document.querySelector('#plan-chat-status');
        existing?.remove();
        const node = document.createElement('div');
        node.id = 'plan-chat-status';
        node.className = `plan-chat-status${isError ? ' error' : ''}`;
        node.textContent = message;
        Object.assign(node.style, {
            position: 'fixed',
            left: '50%',
            bottom: '24px',
            transform: 'translateX(-50%)',
            zIndex: '9999',
            padding: '12px 16px',
            borderRadius: '12px',
            background: isError ? '#fff1f2' : '#e1f7e8',
            color: isError ? '#9f1239' : '#17643a',
            boxShadow: '0 10px 30px rgba(23, 32, 51, 0.18)',
            fontSize: '14px',
            fontWeight: '700',
        });
        document.body.appendChild(node);
        window.setTimeout(() => node.remove(), 4500);
    }

    document.addEventListener('change', async (event) => {
        const input = event.target;
        if (!(input instanceof HTMLInputElement)
            || input.type !== 'checkbox'
            || input.dataset.action !== 'toggle-plan-item'
            || !input.checked) {
            return;
        }

        const itemId = Number(input.dataset.itemId || 0);
        if (!itemId) return;

        const key = sentKey(itemId);
        if (sessionStorage.getItem(key) === '1') {
            return;
        }

        const item = input.closest('.client-plan-item');
        const title = item?.querySelector('strong')?.textContent?.trim() || 'выбранный шаг';
        const instruction = item?.querySelector('span > small:last-child')?.textContent?.trim() || '';
        const message = instruction && /^напишите консультанту:/i.test(instruction)
            ? `Здравствуйте! Хочу обсудить с вами шаг «${title}». ${instruction.replace(/^напишите консультанту:\s*/i, '')}`
            : `Здравствуйте! Хочу обсудить с вами шаг «${title}». ${instruction}`.trim();

        try {
            const payload = typeof userPayload === 'function' ? userPayload() : {};
            const response = await fetch('../api/chat.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({...payload, message}),
            });
            if (!response.ok) {
                throw new Error('chat_send_failed');
            }
            sessionStorage.setItem(key, '1');
            showStatus('Сообщение консультанту отправлено.');
        } catch (_) {
            showStatus('Не удалось отправить сообщение консультанту.', true);
        }
    });
})();
