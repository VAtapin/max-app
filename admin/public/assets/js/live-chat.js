(() => {
    const root = document.querySelector('[data-live-chat]');
    if (!root) return;
    const endpoint = root.dataset.endpoint || 'chat_api.php';
    const csrf = root.dataset.csrf || '';
    const elements = {
        tabs: [...root.querySelectorAll('[data-chat-tab]')], threads: root.querySelector('[data-chat-threads]'), search: root.querySelector('[data-chat-search]'),
        title: root.querySelector('[data-chat-title]'), subtitle: root.querySelector('[data-chat-subtitle]'), messages: root.querySelector('[data-chat-messages]'),
        form: root.querySelector('[data-chat-form]'), input: root.querySelector('[data-chat-input]'), send: root.querySelector('[data-chat-send]'), error: root.querySelector('[data-chat-error]'),
        connection: root.querySelector('[data-chat-connection]'), channelWrap: root.querySelector('[data-chat-channel-wrap]'), channel: root.querySelector('[data-chat-channel]'),
        attach: root.querySelector('[data-chat-attach]'), file: root.querySelector('[data-chat-file]'), fileName: root.querySelector('[data-chat-file-name]'),
        clientUnread: root.querySelector('[data-client-unread]'), teamUnread: root.querySelector('[data-team-unread]'),
    };
    const state = {kind: 'client', clients: [], team: null, selected: null, initialUserId: Number(root.dataset.initialUser || 0), lastMessageId: 0, polling: false};
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));
    const dateLabel = (value) => value ? new Intl.DateTimeFormat('ru-RU', {day:'2-digit', month:'2-digit', hour:'2-digit', minute:'2-digit'}).format(new Date(String(value).replace(' ', 'T'))) : '';
    const api = async (url, options = {}) => {
        const response = await fetch(`${endpoint}${url}`, {credentials: 'same-origin', ...options});
        const result = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(result.error || 'Не удалось выполнить запрос.');
        return result;
    };
    const unreadBadge = (count) => Number(count) > 0 ? `<b>${Number(count)}</b>` : '';
    const selectedKey = (item) => state.kind === 'team' ? 'team' : `client:${item.end_user_id}`;
    const currentKey = () => state.selected ? selectedKey(state.selected) : '';
    const renderThreads = () => {
        const query = String(elements.search?.value || '').trim().toLowerCase();
        const items = state.kind === 'team' ? (state.team ? [state.team] : []) : state.clients.filter(item => String(item.client_name || `Клиент #${item.end_user_id}`).toLowerCase().includes(query));
        if (!items.length) { elements.threads.innerHTML = `<div class="empty-state">${state.kind === 'team' ? 'Командный чат недоступен.' : 'Клиенты не найдены.'}</div>`; return; }
        elements.threads.innerHTML = items.map(item => {
            const name = state.kind === 'team' ? (item.title || 'Чат команды') : (item.client_name || `Клиент #${item.end_user_id}`);
            const active = currentKey() === selectedKey(item);
            return `<button type="button" class="dashboard-chat-thread${active ? ' is-active' : ''}" data-thread-key="${escapeHtml(selectedKey(item))}"><span><strong>${escapeHtml(name)}</strong><small>${escapeHtml(item.last_message || (state.kind === 'team' ? 'Общий чат ветки' : 'Начните диалог'))}</small></span><span>${unreadBadge(item.unread_count)}<small>${escapeHtml(dateLabel(item.last_message_at))}</small></span></button>`;
        }).join('');
        elements.threads.querySelectorAll('[data-thread-key]').forEach(button => button.addEventListener('click', () => {
            state.selected = items.find(item => selectedKey(item) === button.dataset.threadKey) || null;
            state.lastMessageId = 0; renderThreads(); loadMessages();
        }));
    };
    const renderMessages = (messages) => {
        if (!messages.length) { elements.messages.innerHTML = '<div class="empty-state">Сообщений пока нет. Напишите первое сообщение.</div>'; state.lastMessageId = 0; return; }
        elements.messages.innerHTML = messages.map(message => {
            const attachments = (message.attachments || []).map(path => typeof path === 'string' ? `<a href="${escapeHtml(path)}" target="_blank" rel="noopener">Вложение</a>` : '').join('');
            const status = message.status === 'failed' ? `<small class="chat-message-error">Ошибка отправки</small>` : '';
            return `<article class="dashboard-chat-message ${message.sender_type === 'client' ? 'is-client' : 'is-admin'}"><div><strong>${escapeHtml(message.sender_name || (message.sender_type === 'admin' ? 'Команда' : 'Клиент'))}</strong><span>${escapeHtml(message.channel === 'internal' ? '' : message.channel)} ${escapeHtml(dateLabel(message.created_at))}</span></div><p>${escapeHtml(message.message_text).replace(/\n/g, '<br>')}</p>${attachments ? `<div class="dashboard-chat-attachments">${attachments}</div>` : ''}${status}</article>`;
        }).join('');
        state.lastMessageId = Math.max(...messages.map(message => Number(message.id) || 0));
        elements.messages.scrollTop = elements.messages.scrollHeight;
    };
    const loadMessages = async () => {
        if (!state.selected) return;
        const isTeam = state.kind === 'team';
        const query = isTeam ? '&kind=team' : `&end_user_id=${Number(state.selected.end_user_id)}`;
        try {
            const result = await api(`?action=messages${query}`);
            renderMessages(result.messages || []);
            const title = isTeam ? (state.team?.title || 'Чат команды') : (state.selected.client_name || `Клиент #${state.selected.end_user_id}`);
            elements.title.textContent = title;
            elements.subtitle.textContent = isTeam ? 'Все лидеры и консультанты ветки' : 'Личный диалог с клиентом';
            elements.channelWrap.hidden = isTeam;
            elements.input.disabled = false; elements.send.disabled = false;
        } catch (error) { elements.error.textContent = error.message; }
    };
    const loadList = async (preserveSelection = true) => {
        try {
            const previous = preserveSelection ? currentKey() : '';
            const result = await api('?action=list');
            state.clients = result.clients || []; state.team = result.team || null;
            const clientUnread = state.clients.reduce((sum, item) => sum + Number(item.unread_count || 0), 0);
            const teamUnread = Number(state.team?.unread_count || 0);
            elements.clientUnread.innerHTML = unreadBadge(clientUnread); elements.teamUnread.innerHTML = unreadBadge(teamUnread);
            const items = state.kind === 'team' ? (state.team ? [state.team] : []) : state.clients;
            state.selected = items.find(item => state.kind === 'client' && Number(item.end_user_id) === state.initialUserId)
                || items.find(item => selectedKey(item) === previous)
                || (state.selected && items.find(item => selectedKey(item) === currentKey()))
                || items[0]
                || null;
            state.initialUserId = 0;
            renderThreads();
            elements.connection.textContent = 'На связи'; elements.connection.classList.add('is-online');
            if (!preserveSelection && state.selected) await loadMessages();
        } catch (error) { elements.connection.textContent = 'Нет связи'; elements.connection.classList.remove('is-online'); elements.error.textContent = error.message; }
    };
    elements.tabs.forEach(tab => tab.addEventListener('click', async () => {
        state.kind = tab.dataset.chatTab === 'team' ? 'team' : 'client'; state.selected = null; state.lastMessageId = 0;
        elements.tabs.forEach(item => item.classList.toggle('is-active', item === tab));
        elements.search.closest('label').hidden = state.kind === 'team';
        await loadList(false);
    }));
    elements.search?.addEventListener('input', renderThreads);
    elements.attach?.addEventListener('click', () => elements.file.click());
    elements.file?.addEventListener('change', () => { elements.fileName.textContent = [...elements.file.files].map(file => file.name).join(', '); });
    elements.input?.addEventListener('keydown', event => { if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); elements.form.requestSubmit(); } });
    elements.form?.addEventListener('submit', async event => {
        event.preventDefault(); if (!state.selected) return;
        const text = elements.input.value.trim(); if (!text && !elements.file.files.length) return;
        const body = new FormData(); body.set('csrf_token', csrf); body.set('action', 'send'); body.set('kind', state.kind); body.set('message', text);
        if (state.kind === 'client') { body.set('end_user_id', state.selected.end_user_id); body.set('channel', elements.channel.value); }
        [...elements.file.files].forEach(file => body.append('attachments[]', file));
        elements.send.disabled = true; elements.error.textContent = '';
        try { await api('?action=send', {method:'POST', body}); elements.input.value = ''; elements.file.value = ''; elements.fileName.textContent = ''; await loadList(); await loadMessages(); }
        catch (error) { elements.error.textContent = error.message; }
        finally { elements.send.disabled = false; elements.input.focus(); }
    });
    const poll = async () => { if (state.polling || document.hidden) return; state.polling = true; try { await loadList(); if (state.selected) await loadMessages(); } finally { state.polling = false; } };
    loadList(false); setInterval(poll, 3000);
})();
