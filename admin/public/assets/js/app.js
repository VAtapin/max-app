function initAdminResultModals(root = document) {
    root.querySelectorAll('[data-result-modal]').forEach((element) => {
        if (element.dataset.resultModalBound === '1') {
            return;
        }
        element.dataset.resultModalBound = '1';
        element.addEventListener('click', (event) => {
            if (event.target instanceof HTMLAnchorElement) {
                return;
            }
            if (element instanceof HTMLButtonElement) {
                event.stopPropagation();
            }
            const modalId = element.dataset.resultModal;
            const modal = modalId ? document.getElementById(modalId) : null;
            if (modal && typeof modal.showModal === 'function' && !modal.open) {
                modal.showModal();
            }
        });
    });
}

function initAdminModalBackdrop(root = document) {
    root.querySelectorAll('.admin-modal').forEach((modal) => {
        if (modal.dataset.adminBackdropBound === '1') {
            return;
        }
        modal.dataset.adminBackdropBound = '1';
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                modal.close();
            }
        });
    });
}

function adminRemoteModal() {
    let modal = document.getElementById('admin-remote-modal');
    if (modal) {
        return modal;
    }

    modal = document.createElement('dialog');
    modal.id = 'admin-remote-modal';
    modal.className = 'admin-modal remote-admin-modal';
    modal.innerHTML = `
        <div class="modal-shell">
            <div class="modal-head">
                <div>
                    <span class="eyebrow">Загрузка</span>
                    <h2>Пожалуйста, подождите</h2>
                </div>
                <form method="dialog"><button class="icon-button" aria-label="Закрыть">&times;</button></form>
            </div>
            <div class="modal-body"><div class="empty-state">Загружаем данные...</div></div>
        </div>
    `;
    document.body.appendChild(modal);
    initAdminModalBackdrop(modal.parentElement || document);
    return modal;
}

function escapeAdminAttribute(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function escapeAdminHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function initAdminRemoteModals(root = document) {
    root.querySelectorAll('[data-admin-modal-url]').forEach((link) => {
        if (link.dataset.adminModalBound === '1') {
            return;
        }
        link.dataset.adminModalBound = '1';
        link.addEventListener('click', async (event) => {
            event.preventDefault();
            const url = link.dataset.adminModalUrl || link.getAttribute('href');
            const fallbackUrl = link.getAttribute('href') || url;
            if (!url) {
                return;
            }

            const modal = adminRemoteModal();
            modal.innerHTML = `
                <div class="modal-shell">
                    <div class="modal-head">
                        <div>
                            <span class="eyebrow">Загрузка</span>
                            <h2>Пожалуйста, подождите</h2>
                        </div>
                        <form method="dialog"><button class="icon-button" aria-label="Закрыть">&times;</button></form>
                    </div>
                    <div class="modal-body"><div class="empty-state">Загружаем данные...</div></div>
                </div>
            `;
            if (typeof modal.showModal === 'function' && !modal.open) {
                modal.showModal();
            }

            try {
                const response = await fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                modal.innerHTML = await response.text();
                initAdminModalBackdrop(modal.parentElement || document);
                initAdminResultModals(modal);
                initAdminRemoteModals(modal);
                initUserMergeSearch(modal);
                initLimitChecks(modal);
                initImagePreviewModal(modal);
                initResponsiveTables(modal);
            } catch (error) {
                modal.innerHTML = `
                    <div class="modal-shell">
                        <div class="modal-head">
                            <div>
                                <span class="eyebrow">Ошибка</span>
                                <h2>Не удалось открыть окно</h2>
                            </div>
                            <form method="dialog"><button class="icon-button" aria-label="Закрыть">&times;</button></form>
                        </div>
                        <div class="modal-body">
                            <div class="alert">Попробуйте открыть ссылку отдельной страницей.</div>
                        </div>
                        <div class="modal-actions">
                            <a class="button secondary-button" href="${escapeAdminAttribute(fallbackUrl)}">Открыть страницей</a>
                            <form method="dialog"><button type="submit" class="secondary-button">Закрыть</button></form>
                        </div>
                    </div>
                `;
            }
        });
    });
}

function initUserMergeSearch(root = document) {
    root.querySelectorAll('[data-user-merge-search]').forEach((widget) => {
        if (widget.dataset.mergeSearchBound === '1') {
            return;
        }
        widget.dataset.mergeSearchBound = '1';

        const searchUrl = widget.dataset.searchUrl || '';
        const input = widget.querySelector('[data-merge-search-input]');
        const hidden = widget.querySelector('[data-merge-user-id]');
        const suggestions = widget.querySelector('[data-merge-suggestions]');
        const selected = widget.querySelector('[data-merge-selected]');
        const form = widget.closest('[data-user-merge-form]');
        const submit = form ? form.querySelector('[data-merge-submit]') : null;
        const loadingText = widget.dataset.loading || 'Загрузка...';
        const emptyText = widget.dataset.empty || 'Ничего не найдено.';
        const selectedText = widget.dataset.selected || 'Выбран:';
        const chooseFirstText = widget.dataset.chooseFirst || 'Сначала выберите пользователя.';
        let timer = null;
        let controller = null;

        if (!searchUrl || !input || !hidden || !suggestions) {
            return;
        }

        const setPending = () => {
            suggestions.innerHTML = `<div class="empty-state">${escapeAdminHtml(loadingText)}</div>`;
        };

        const setEmpty = (message = emptyText) => {
            suggestions.innerHTML = `<div class="empty-state">${escapeAdminHtml(message)}</div>`;
        };

        const clearChoice = () => {
            hidden.value = '';
            if (submit) {
                submit.disabled = true;
            }
            if (selected) {
                selected.hidden = true;
                selected.textContent = '';
            }
        };

        const renderItems = (items) => {
            if (!items.length) {
                setEmpty();
                return;
            }

            suggestions.innerHTML = items.map((item) => `
                <button
                    type="button"
                    class="merge-suggestion"
                    data-merge-user-option
                    data-id="${escapeAdminAttribute(item.id)}"
                    data-label="${escapeAdminAttribute(item.label || '')}"
                >
                    <strong>${escapeAdminHtml(item.label || '')}</strong>
                    ${item.meta ? `<span>${escapeAdminHtml(item.meta)}</span>` : ''}
                    ${item.reason ? `<small>${escapeAdminHtml(item.reason)}</small>` : ''}
                </button>
            `).join('');
        };

        const loadItems = async (query = '') => {
            if (controller) {
                controller.abort();
            }
            controller = new AbortController();
            setPending();

            const separator = searchUrl.includes('?') ? '&' : '?';
            try {
                const response = await fetch(`${searchUrl}${separator}q=${encodeURIComponent(query)}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    signal: controller.signal,
                });
                const payload = await response.json();
                if (!response.ok) {
                    throw new Error(payload.error || `HTTP ${response.status}`);
                }
                renderItems(Array.isArray(payload.items) ? payload.items : []);
            } catch (error) {
                if (error.name !== 'AbortError') {
                    setEmpty(error.message || emptyText);
                }
            }
        };

        input.addEventListener('input', () => {
            clearChoice();
            window.clearTimeout(timer);
            timer = window.setTimeout(() => loadItems(input.value.trim()), 260);
        });

        suggestions.addEventListener('click', (event) => {
            const option = event.target.closest('[data-merge-user-option]');
            if (!option) {
                return;
            }

            hidden.value = option.dataset.id || '';
            input.value = option.dataset.label || '';
            if (selected) {
                selected.hidden = false;
                selected.innerHTML = `<span>${escapeAdminHtml(selectedText)}</span> <strong>${escapeAdminHtml(option.dataset.label || '')}</strong>`;
            }
            if (submit) {
                submit.disabled = hidden.value === '';
            }
        });

        if (form) {
            form.addEventListener('submit', (event) => {
                if (!hidden.value) {
                    event.preventDefault();
                    alert(chooseFirstText);
                }
            });
        }

        loadItems('');
    });
}

function initLimitChecks(root = document) {
    root.querySelectorAll('form[data-limit-check-url]').forEach((form) => {
        if (form.dataset.limitCheckBound === '1') {
            return;
        }
        form.dataset.limitCheckBound = '1';

        const url = form.dataset.limitCheckUrl || '';
        const message = form.querySelector('[data-limit-check-message]');
        const submit = form.querySelector('button[type="submit"]');
        const submitInitiallyDisabled = submit ? submit.disabled : false;
        let timer = null;
        let controller = null;
        let lastErrors = [];

        if (!url || !message) {
            return;
        }

        const limitInputs = Array.from(form.querySelectorAll('[data-limit-field]'));
        const fieldMessages = new Map(
            Array.from(form.querySelectorAll('[data-limit-field-message]')).map((node) => [node.dataset.limitFieldMessage, node])
        );

        const setSubmitBlocked = (blocked) => {
            if (!submit || submitInitiallyDisabled) {
                return;
            }
            submit.disabled = blocked;
        };

        const numericValue = (control) => {
            if (!control || control.value === '') {
                return null;
            }
            const value = Number(control.value);
            return Number.isFinite(value) ? value : null;
        };

        const fieldLabel = (control) => {
            const label = control?.closest('.field')?.querySelector('span')?.textContent?.trim();
            return label || 'Поле';
        };

        const maxCandidates = (control) => {
            const candidates = [];
            const configuredMax = Number(control.dataset.limitMax || control.getAttribute('max') || '');
            if (Number.isFinite(configuredMax)) {
                candidates.push({
                    max: configuredMax,
                    source: control.dataset.limitSource || `Максимум: ${configuredMax}.`,
                });
            }

            const name = control.name;
            const branchLeaderLimit = numericValue(form.elements.branch_leader_limit);
            const branchManagerLimit = numericValue(form.elements.branch_manager_limit);
            if (name === 'direct_leader_limit' && branchLeaderLimit !== null) {
                candidates.push({
                    max: branchLeaderLimit,
                    source: 'Лимит прямых лидеров не может быть больше лимита лидеров во всей ветке этого лидера.',
                });
            }
            if (name === 'direct_manager_limit' && branchManagerLimit !== null) {
                candidates.push({
                    max: branchManagerLimit,
                    source: 'Лимит прямых консультантов не может быть больше лимита консультантов во всей ветке этого лидера.',
                });
            }

            return candidates.filter((item) => Number.isFinite(item.max));
        };

        const activeCap = (control) => {
            const candidates = maxCandidates(control);
            if (!candidates.length) {
                return null;
            }
            return candidates.reduce((current, item) => item.max < current.max ? item : current, candidates[0]);
        };

        const setFieldState = (control, state, text) => {
            const note = fieldMessages.get(control.name);
            control.classList.remove('is-limit-error', 'is-limit-info');
            if (!note || !text) {
                if (note) {
                    note.hidden = true;
                    note.textContent = '';
                    note.classList.remove('is-error', 'is-info');
                }
                return;
            }

            control.classList.add(state === 'error' ? 'is-limit-error' : 'is-limit-info');
            note.hidden = false;
            note.textContent = text;
            note.classList.toggle('is-error', state === 'error');
            note.classList.toggle('is-info', state !== 'error');
        };

        const enforceLimitInput = (control, showInfo = false) => {
            const cap = activeCap(control);
            if (!cap) {
                setFieldState(control, '', '');
                control.removeAttribute('max');
                return false;
            }

            control.setAttribute('max', String(cap.max));
            const value = numericValue(control);
            if (value !== null && value > cap.max) {
                control.value = String(cap.max);
                setFieldState(control, 'error', `${fieldLabel(control)}: максимум ${cap.max}. ${cap.source}`);
                return true;
            }

            if (showInfo || value !== null) {
                setFieldState(control, 'info', `${fieldLabel(control)}: можно поставить до ${cap.max}.`);
            } else {
                setFieldState(control, '', '');
            }
            return false;
        };

        const enforceAllLimitInputs = (showInfo = false) => {
            let clamped = false;
            limitInputs.forEach((control) => {
                clamped = enforceLimitInput(control, showInfo) || clamped;
            });
            return clamped;
        };

        const applyFieldLimits = (limits = {}) => {
            limitInputs.forEach((control) => {
                const limit = limits[control.name] || null;
                if (limit && Number.isFinite(Number(limit.max))) {
                    control.dataset.limitMax = String(limit.max);
                    control.dataset.limitSource = limit.source || '';
                    control.setAttribute('max', String(limit.max));
                } else {
                    delete control.dataset.limitMax;
                    delete control.dataset.limitSource;
                    control.removeAttribute('max');
                }
            });
            enforceAllLimitInputs();
        };

        const renderMessage = (errors = [], pending = false) => {
            message.classList.remove('is-error', 'is-pending');

            if (pending) {
                setSubmitBlocked(lastErrors.length > 0);
                return;
            }

            if (!errors.length) {
                message.hidden = true;
                message.textContent = '';
                setSubmitBlocked(false);
                return;
            }

            message.hidden = false;
            message.classList.add('is-error');
            message.innerHTML = `<strong>Нельзя сохранить с такими лимитами.</strong><br>${errors.map(escapeAdminHtml).join('<br>')}`;
            setSubmitBlocked(true);
        };

        const runCheck = async () => {
            if (controller) {
                controller.abort();
            }
            controller = new AbortController();

            try {
                const payload = new FormData(form);
                const response = await fetch(url, {
                    method: 'POST',
                    body: payload,
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    signal: controller.signal,
                });
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                const data = await response.json();
                lastErrors = Array.isArray(data.errors) ? data.errors.filter(Boolean) : [];
                applyFieldLimits(data.field_limits || {});
                renderMessage(lastErrors);
            } catch (error) {
                if (error.name === 'AbortError') {
                    return;
                }
                lastErrors = [];
                renderMessage([]);
            } finally {
                controller = null;
            }
        };

        const scheduleCheck = () => {
            window.clearTimeout(timer);
            enforceAllLimitInputs();
            renderMessage(lastErrors, true);
            timer = window.setTimeout(runCheck, 350);
        };

        form.querySelectorAll('input, select').forEach((control) => {
            if (control.type === 'hidden' || control.type === 'file') {
                return;
            }
            control.addEventListener('input', () => {
                enforceAllLimitInputs(true);
                scheduleCheck();
            });
            control.addEventListener('change', () => {
                enforceAllLimitInputs(true);
                scheduleCheck();
            });
        });

        form.addEventListener('submit', (event) => {
            if (enforceAllLimitInputs(true)) {
                event.preventDefault();
                const firstError = form.querySelector('.is-limit-error');
                firstError?.scrollIntoView({behavior: 'smooth', block: 'center'});
                return;
            }
            if (!lastErrors.length) {
                return;
            }
            event.preventDefault();
            renderMessage(lastErrors);
            message.scrollIntoView({behavior: 'smooth', block: 'center'});
        });

        enforceAllLimitInputs();
        scheduleCheck();
    });
}

function initLeadMediaModal(root = document) {
    const modal = document.getElementById('lead-media-modal');
    if (!modal || modal.dataset.leadMediaBound === '1') {
        return;
    }

    modal.dataset.leadMediaBound = '1';
    const titleNode = modal.querySelector('[data-lead-media-title]');
    const bodyNode = modal.querySelector('[data-lead-media-body]');

    const openExternalLink = (url, label = 'Открыть вложение') => {
        const wrapper = document.createElement('div');
        wrapper.className = 'lead-media-link-state';
        const link = document.createElement('a');
        link.className = 'button';
        link.href = url;
        link.target = '_blank';
        link.rel = 'noopener';
        link.textContent = label;
        wrapper.appendChild(link);
        bodyNode.appendChild(wrapper);
    };

    root.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-lead-media]');
        if (!trigger || !bodyNode) {
            return;
        }

        event.preventDefault();
        const url = trigger.dataset.mediaUrl || '';
        const type = trigger.dataset.mediaType || 'link';
        const title = trigger.dataset.mediaTitle || 'Вложение';
        if (!url) {
            return;
        }

        if (titleNode) {
            titleNode.textContent = title;
        }
        bodyNode.replaceChildren();

        if (type === 'image') {
            const image = document.createElement('img');
            image.src = url;
            image.alt = title;
            image.loading = 'eager';
            bodyNode.appendChild(image);
        } else if (type === 'audio') {
            const audio = document.createElement('audio');
            audio.controls = true;
            audio.autoplay = true;
            audio.src = url;
            bodyNode.appendChild(audio);
            openExternalLink(url, 'Открыть аудио');
        } else if (type === 'video' && /\.(mp4|webm|ogg)(\?|#|$)/i.test(url)) {
            const video = document.createElement('video');
            video.controls = true;
            video.autoplay = true;
            video.src = url;
            bodyNode.appendChild(video);
        } else if (type === 'video') {
            openExternalLink(url, 'Открыть видео');
        } else {
            openExternalLink(url);
        }

        if (typeof modal.showModal === 'function' && !modal.open) {
            modal.showModal();
        }
    });
}

function adminImagePreviewModal() {
    let modal = document.getElementById('admin-image-preview-modal');
    if (modal) {
        return modal;
    }

    modal = document.createElement('dialog');
    modal.id = 'admin-image-preview-modal';
    modal.className = 'admin-modal image-preview-modal';
    modal.innerHTML = `
        <div class="modal-shell image-preview-shell">
            <div class="modal-head">
                <div><span class="eyebrow">Скриншот</span><h2 data-image-preview-title>Просмотр</h2></div>
                <form method="dialog"><button class="icon-button" aria-label="Закрыть">&times;</button></form>
            </div>
            <div class="modal-body image-preview-body" data-image-preview-body></div>
        </div>
    `;
    document.body.appendChild(modal);
    initAdminModalBackdrop(modal.parentElement || document);
    return modal;
}

function initImagePreviewModal(root = document) {
    if (root.dataset && root.dataset.imagePreviewBound === '1') {
        return;
    }
    if (root.dataset) {
        root.dataset.imagePreviewBound = '1';
    }

    root.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-image-preview]');
        if (!trigger) {
            return;
        }

        event.preventDefault();
        const url = trigger.dataset.imageSrc || '';
        if (!url) {
            return;
        }

        const modal = adminImagePreviewModal();
        const titleNode = modal.querySelector('[data-image-preview-title]');
        const bodyNode = modal.querySelector('[data-image-preview-body]');
        if (titleNode) {
            titleNode.textContent = trigger.dataset.imageTitle || 'Скриншот';
        }
        if (bodyNode) {
            bodyNode.replaceChildren();
            const frame = document.createElement('div');
            frame.className = 'image-preview-frame';
            const imageNode = document.createElement('img');
            imageNode.src = url;
            imageNode.alt = trigger.dataset.imageTitle || 'Скриншот';
            frame.appendChild(imageNode);
            bodyNode.appendChild(frame);
            if (trigger.dataset.imageCaption) {
                const caption = document.createElement('p');
                caption.className = 'image-preview-caption';
                caption.textContent = trigger.dataset.imageCaption;
                bodyNode.appendChild(caption);
            }
        }
        if (typeof modal.showModal === 'function' && !modal.open) {
            modal.showModal();
        }
    });
}

function initResponsiveTables(root = document) {
    root.querySelectorAll('table').forEach((table) => {
        if (table.dataset.responsiveTableBound === '1') {
            return;
        }

        const headerCells = Array.from(table.querySelectorAll('thead th'));
        if (!headerCells.length) {
            return;
        }

        const headers = headerCells.map((header, index) => {
            const label = header.textContent.trim();
            return label || (index === headerCells.length - 1 ? 'Действия' : 'Данные');
        });

        table.dataset.responsiveTableBound = '1';
        table.classList.add('responsive-table');
        table.querySelectorAll('tbody tr').forEach((row) => {
            let headerIndex = 0;
            row.querySelectorAll('td').forEach((cell) => {
                const colspan = Math.max(1, Number(cell.getAttribute('colspan') || 1));
                if (!cell.dataset.label) {
                    cell.dataset.label = headers[headerIndex] || headers[headers.length - 1] || 'Данные';
                }
                headerIndex += colspan;
            });
        });
    });
}

function initAiChat(root = document) {
    root.querySelectorAll('[data-ai-chat]').forEach((widget) => {
        if (widget.dataset.aiChatBound === '1') return;
        widget.dataset.aiChatBound = '1';
        const toggle = widget.querySelector('[data-ai-chat-toggle]');
        const close = widget.querySelector('[data-ai-chat-close]');
        const panel = widget.querySelector('[data-ai-chat-panel]');
        const form = widget.querySelector('[data-ai-chat-form]');
        const messages = widget.querySelector('[data-ai-chat-messages]');
        const input = form?.querySelector('textarea[name="message"]');
        const submit = form?.querySelector('button[type="submit"]');
        if (!toggle || !panel || !form || !messages || !input || !submit) return;

        const setOpen = (open) => {
            panel.hidden = !open;
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) window.setTimeout(() => input.focus(), 0);
        };
        toggle.addEventListener('click', () => setOpen(panel.hidden));
        close?.addEventListener('click', () => setOpen(false));

        const addMessage = (text, role, citations = []) => {
            const node = document.createElement('div');
            node.className = `ai-chat-message ${role}`;
            const body = document.createElement('div');
            body.textContent = text;
            node.appendChild(body);
            if (citations.length) {
                const sourceList = document.createElement('small');
                sourceList.className = 'ai-chat-sources';
                sourceList.textContent = `Источники: ${citations.map((item) => item.label).join('; ')}`;
                node.appendChild(sourceList);
            }
            messages.appendChild(node);
            messages.scrollTop = messages.scrollHeight;
        };

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const question = input.value.trim();
            if (!question) return;
            addMessage(question, 'user');
            input.value = '';
            input.disabled = true;
            submit.disabled = true;
            const pending = document.createElement('div');
            pending.className = 'ai-chat-message assistant pending';
            pending.textContent = 'Ищу ответ в материалах SWPro…';
            messages.appendChild(pending);
            messages.scrollTop = messages.scrollHeight;
            try {
                const payload = new FormData();
                payload.set('csrf_token', widget.dataset.csrf || '');
                payload.set('message', question);
                payload.set('page_context', `${location.pathname}${location.search}`);
                const response = await fetch(widget.dataset.endpoint || 'ai_chat.php', {
                    method: 'POST',
                    body: payload,
                    headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                });
                const data = await response.json();
                pending.remove();
                if (!response.ok || !data.ok) throw new Error(data.error || `HTTP ${response.status}`);
                addMessage(data.answer || 'Ответ не получен.', 'assistant', Array.isArray(data.citations) ? data.citations : []);
            } catch (error) {
                pending.remove();
                addMessage(error.message || 'Помощник временно недоступен.', 'assistant error');
            } finally {
                input.disabled = false;
                submit.disabled = false;
                input.focus();
            }
        });
    });
}

document.querySelectorAll('button[disabled]').forEach((button) => {
    button.title = '';
});

initAdminResultModals();
initAdminModalBackdrop();
initAdminRemoteModals();
initUserMergeSearch();
initLimitChecks();
initLeadMediaModal();
initImagePreviewModal();
initResponsiveTables();
initAiChat();
