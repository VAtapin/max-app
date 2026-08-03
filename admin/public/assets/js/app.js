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

document.querySelectorAll('button[disabled]').forEach((button) => {
    button.title = '';
});

initAdminResultModals();
initAdminModalBackdrop();
initAdminRemoteModals();
initUserMergeSearch();
