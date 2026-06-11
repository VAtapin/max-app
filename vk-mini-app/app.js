const API_BASE = '../api';

const state = {
    user: null,
    vkUser: null,
    page: 'home',
    activeTest: null,
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

function userPayload() {
    return {
        platform: 'vk',
        platform_user_id: String(state.vkUser.id),
    };
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
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
                <strong>${escapeHtml(test.title)}</strong>
                <span class="muted">${escapeHtml(test.description || '')}</span>
                <button class="secondary" data-open-test-id="${test.id}">Открыть тест</button>
            </article>
        `).join('')
        : '<div class="empty">Активных тестов пока нет.</div>';
}

async function renderTest(testId) {
    const result = await api(`tests.php?id=${encodeURIComponent(testId)}`);
    state.activeTest = result;
    page.innerHTML = `
        <section class="panel">
            <button class="secondary compact" data-action="back-to-tests">Назад</button>
            <h2>${escapeHtml(result.test.title)}</h2>
            <p class="muted">${escapeHtml(result.test.description || '')}</p>
            <form id="test-form" class="test-form">
                ${result.questions.map((question) => renderQuestion(question)).join('')}
                <button class="primary" type="submit">Получить рекомендации</button>
            </form>
        </section>
    `;
}

function renderQuestion(question) {
    const answers = question.answers || [];
    const type = question.question_type;
    const controls = answers.length
        ? answers.map((answer) => `
            <label class="answer">
                <input type="${type === 'multiple_choice' ? 'checkbox' : 'radio'}" name="question_${question.id}" value="${answer.id}">
                <span>${escapeHtml(answer.answer_text)}</span>
            </label>
        `).join('')
        : `<input class="text-answer" name="question_${question.id}" placeholder="Ваш ответ">`;

    return `
        <fieldset class="question" data-question-id="${question.id}" data-question-type="${type}">
            <legend>${escapeHtml(question.question_text)}</legend>
            ${controls}
        </fieldset>
    `;
}

async function submitTest(form) {
    const answers = [];
    form.querySelectorAll('.question').forEach((question) => {
        const questionId = Number(question.dataset.questionId);
        const checked = question.querySelectorAll('input[type="radio"]:checked, input[type="checkbox"]:checked');
        if (checked.length) {
            checked.forEach((input) => answers.push({question_id: questionId, answer_id: Number(input.value)}));
            return;
        }

        const textInput = question.querySelector('.text-answer');
        if (textInput && textInput.value.trim()) {
            answers.push({question_id: questionId, text_answer: textInput.value.trim()});
        }
    });

    const result = await api('tests.php?action=submit', {
        method: 'POST',
        body: JSON.stringify({
            ...userPayload(),
            test_id: state.activeTest.test.id,
            answers,
        }),
    });

    page.innerHTML = `
        <section class="panel">
            <h2>Рекомендации готовы</h2>
            <p class="muted">${escapeHtml(result.summary)}</p>
            <button class="primary" data-page-target="recommendations">Показать рекомендации</button>
            <button class="secondary" data-action="contact">Связаться с менеджером</button>
        </section>
    `;
}

async function renderProducts() {
    const result = await api('products.php');
    page.innerHTML = result.products.length
        ? result.products.map((product) => `
            <article class="item">
                ${product.image_path ? `<img class="item-image" src="${escapeHtml(product.image_path)}" alt="">` : ''}
                <strong>${escapeHtml(product.title)}</strong>
                <span class="muted">${escapeHtml(product.short_description || '')}</span>
                <div class="item-links">
                    ${product.document_path ? `<a href="${escapeHtml(product.document_path)}" target="_blank" rel="noopener">PDF</a>` : ''}
                    ${product.video_url ? `<a href="${escapeHtml(product.video_url)}" target="_blank" rel="noopener">Видео</a>` : ''}
                    ${product.purchase_url ? `<a href="${escapeHtml(product.purchase_url)}" target="_blank" rel="noopener">Подробнее</a>` : ''}
                </div>
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
                <strong>${escapeHtml(item.product_title || 'Рекомендация')}</strong>
                <span class="muted">${escapeHtml(item.short_description || item.reason_text || '')}</span>
                ${item.product_id ? `<button class="secondary" data-product-id="${item.product_id}">Запросить информацию</button>` : ''}
            </article>
        `).join('')
        : '<div class="empty">Рекомендаций пока нет.</div>';
}

async function contactManager(productId = null) {
    const payload = {
        ...userPayload(),
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
    if (target.dataset.action === 'back-to-tests') await renderTests();
    if (target.dataset.action === 'contact') await contactManager();
    if (target.dataset.pageTarget) setPage(target.dataset.pageTarget);
    if (target.dataset.openTestId) await renderTest(Number(target.dataset.openTestId));
    if (target.dataset.productId) await contactManager(Number(target.dataset.productId));
});

page.addEventListener('submit', async (event) => {
    const target = event.target;
    if (!(target instanceof HTMLFormElement) || target.id !== 'test-form') return;
    event.preventDefault();
    await submitTest(target);
});

authorize()
    .then(render)
    .catch((error) => {
        page.innerHTML = `<div class="empty">${error.message}</div>`;
    });
