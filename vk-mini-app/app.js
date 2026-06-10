const API_BASE = '../api';

const state = {
    user: null,
    vkUser: null,
    page: 'home',
};

const page = document.querySelector('#page');
const tabs = document.querySelectorAll('.tabs button');

function getReferralCode() {
    const hash = new URLSearchParams(window.location.hash.replace(/^#/, ''));
    return hash.get('ref');
}

async function api(path, options = {}) {
    const response = await fetch(`${API_BASE}/${path}`, {
        headers: {'Content-Type': 'application/json'},
        ...options,
    });
    if (!response.ok) {
        throw new Error(`API error ${response.status}`);
    }
    return response.json();
}

async function initVk() {
    if (!window.vkBridge) {
        return {id: 'dev-user', first_name: 'Dev', last_name: 'User'};
    }

    await vkBridge.send('VKWebAppInit');
    return vkBridge.send('VKWebAppGetUserInfo');
}

async function authorize() {
    state.vkUser = await initVk();
    const payload = {
        platform: 'vk',
        platform_user_id: String(state.vkUser.id),
        first_name: state.vkUser.first_name,
        last_name: state.vkUser.last_name,
        username: state.vkUser.domain,
        referral_code: getReferralCode(),
    };
    const result = await api('auth.php', {
        method: 'POST',
        body: JSON.stringify(payload),
    });
    state.user = result.user;
}

function userQuery() {
    return `platform=vk&platform_user_id=${encodeURIComponent(String(state.vkUser.id))}`;
}

function setPage(nextPage) {
    state.page = nextPage;
    tabs.forEach((tab) => tab.classList.toggle('active', tab.dataset.page === nextPage));
    render();
}

function renderHome() {
    page.innerHTML = `
        <section class="panel">
            <h2>Главная</h2>
            <p class="muted">Пройдите тест, посмотрите рекомендации или отправьте заявку менеджеру.</p>
            <button class="primary" data-action="tests">Пройти тест</button>
        </section>
        <section class="panel">
            <h2>Консультация</h2>
            <p class="muted">Оставьте заявку, если хотите уточнить информацию по продуктам.</p>
            <button class="secondary" data-action="contact">Связаться с менеджером</button>
        </section>
    `;
}

async function renderProfile() {
    const result = await api(`user.php?${userQuery()}`);
    const user = result.user;
    page.innerHTML = `
        <section class="panel">
            <h2>Профиль</h2>
            <p>ID: ${user.id}</p>
            <p>Платформа: ${user.platform}</p>
            <p>Статус: ${user.status}</p>
            <p class="muted">Менеджер: ${user.manager_id || 'будет назначен позже'}</p>
        </section>
    `;
}

async function renderTests() {
    const result = await api('tests.php');
    page.innerHTML = result.tests.length
        ? result.tests.map((test) => `
            <article class="item">
                <strong>${test.title}</strong>
                <span class="muted">${test.description || ''}</span>
                <button class="secondary" data-test-id="${test.id}">Открыть тест</button>
            </article>
        `).join('')
        : '<div class="empty">Активных тестов пока нет.</div>';
}

async function renderProducts() {
    const result = await api('products.php');
    page.innerHTML = result.products.length
        ? result.products.map((product) => `
            <article class="item">
                <strong>${product.title}</strong>
                <span class="muted">${product.short_description || ''}</span>
                <button class="secondary" data-product-id="${product.id}">Запросить информацию</button>
            </article>
        `).join('')
        : '<div class="empty">Продуктов пока нет.</div>';
}

async function renderRecommendations() {
    const result = await api(`recommendations.php?${userQuery()}`);
    page.innerHTML = result.recommendations.length
        ? result.recommendations.map((item) => `
            <article class="item">
                <strong>${item.product_title || 'Рекомендация'}</strong>
                <span class="muted">${item.short_description || item.reason_text || ''}</span>
            </article>
        `).join('')
        : '<div class="empty">Рекомендаций пока нет.</div>';
}

async function contactManager(productId = null) {
    const payload = {
        platform: 'vk',
        platform_user_id: String(state.vkUser.id),
        product_id: productId,
        message: productId ? 'Пользователь запросил информацию о продукте.' : 'Пользователь запросил связь с менеджером.',
    };
    const result = await api('contact_manager.php', {
        method: 'POST',
        body: JSON.stringify(payload),
    });
    page.insertAdjacentHTML('afterbegin', `<div class="panel">Заявка #${result.lead_id} создана.</div>`);
}

async function render() {
    page.innerHTML = '<div class="empty">Загрузка...</div>';
    try {
        if (state.page === 'home') renderHome();
        if (state.page === 'profile') await renderProfile();
        if (state.page === 'tests') await renderTests();
        if (state.page === 'products') await renderProducts();
        if (state.page === 'recommendations') await renderRecommendations();
    } catch (error) {
        page.innerHTML = `<div class="empty">${error.message}</div>`;
    }
}

tabs.forEach((tab) => {
    tab.addEventListener('click', () => setPage(tab.dataset.page));
});

page.addEventListener('click', async (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;
    if (target.dataset.action === 'tests') setPage('tests');
    if (target.dataset.action === 'contact') await contactManager();
    if (target.dataset.productId) await contactManager(Number(target.dataset.productId));
});

authorize()
    .then(render)
    .catch((error) => {
        page.innerHTML = `<div class="empty">${error.message}</div>`;
    });
