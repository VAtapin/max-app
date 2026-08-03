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

document.querySelectorAll('button[disabled]').forEach((button) => {
    button.title = '';
});

initAdminResultModals();
initAdminModalBackdrop();
initAdminRemoteModals();
