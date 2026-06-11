const API_BASE = '../api';

const state = {
    user: null,
    vkUser: null,
    page: 'home',
    activeTest: null,
    initialTestId: null,
    i18n: {},
};

const page = document.querySelector('#page');
const tabs = document.querySelectorAll('.tabs button');

function getReferralCode() {
    const hash = new URLSearchParams(window.location.hash.replace(/^#/, ''));
    const search = new URLSearchParams(window.location.search);
    return hash.get('ref') || search.get('ref') || search.get('startapp') || null;
}

function applyInitialRoute() {
    const search = new URLSearchParams(window.location.search);
    const pageName = search.get('page');
    const testId = Number(search.get('test_id') || 0);
    if (['home', 'profile', 'tests', 'products', 'recommendations', 'leads'].includes(pageName || '')) {
        state.page = pageName;
    }
    if (testId > 0) {
        state.page = 'tests';
        state.initialTestId = testId;
    }
}

async function loadI18n() {
    try {
        const response = await fetch('i18n/ru.json', {cache: 'no-store'});
        state.i18n = response.ok ? await response.json() : {};
        applyStaticI18n();
    } catch (_) {
        state.i18n = {};
        applyStaticI18n();
    }
}

function ui(key, fallback = '') {
    return state.i18n[key] || fallback || key;
}

function applyStaticI18n() {
    document.querySelectorAll('[data-i18n]').forEach((element) => {
        element.textContent = ui(element.dataset.i18n, element.textContent);
    });
    document.querySelectorAll('[data-i18n-attr]').forEach((element) => {
        element.dataset.i18nAttr.split(';').forEach((pair) => {
            const [attribute, key] = pair.split(':').map((part) => part.trim());
            if (attribute && key) {
                element.setAttribute(attribute, ui(key, element.getAttribute(attribute) || ''));
            }
        });
    });
}

function formatUi(key, params = {}, fallback = '') {
    let text = ui(key, fallback);
    Object.entries(params).forEach(([name, value]) => {
        text = text.replaceAll(`{${name}}`, String(value));
    });
    return text;
}

async function api(path, options = {}) {
    const response = await fetch(`${API_BASE}/${path}`, {
        headers: {'Content-Type': 'application/json'},
        ...options,
    });
    if (!response.ok) {
        let message = `API error ${response.status}`;
        try {
            const error = await response.json();
            message = error.error || message;
        } catch (_) {
            // Keep the default message when the response is not JSON.
        }
        throw new Error(message);
    }
    return response.json();
}

async function initVk() {
    if (!window.vkBridge) {
        return {id: 'dev-user', first_name: 'Dev', last_name: 'User', domain: 'dev-user'};
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
            <h2>${escapeHtml(ui('home.title'))}</h2>
            <p class="muted">${escapeHtml(ui('home.text'))}</p>
            <button class="primary" data-action="tests">${escapeHtml(ui('home.start_test'))}</button>
        </section>
        <section class="panel">
            <h2>${escapeHtml(ui('consultation.title'))}</h2>
            <p class="muted">${escapeHtml(ui('consultation.text'))}</p>
            <button class="secondary" data-action="contact">${escapeHtml(ui('lead.contact_manager'))}</button>
        </section>
    `;
}

async function renderProfile() {
    const result = await api(`user.php?${userQuery()}`);
    const user = result.user;
    page.innerHTML = `
        <section class="panel">
            <h2>${escapeHtml(ui('profile.title'))}</h2>
            <p>ID: ${user.id}</p>
            <p>${escapeHtml(ui('profile.platform'))}: vk</p>
            <p>${escapeHtml(ui('profile.status'))}: ${escapeHtml(user.status)}</p>
            <p class="muted">${escapeHtml(ui('profile.manager'))}: ${escapeHtml(user.manager_id || ui('profile.manager_later'))}</p>
        </section>
    `;
}

async function renderTests() {
    const result = await api(`tests.php?${userQuery()}`);
    page.innerHTML = result.tests.length
        ? result.tests.map((test) => `
            <article class="item">
                <strong>${escapeHtml(test.title)}</strong>
                <span class="muted">${escapeHtml(test.description || '')}</span>
                <button class="secondary" data-open-test-id="${test.id}">${escapeHtml(ui('tests.open'))}</button>
            </article>
        `).join('')
        : `<div class="empty">${escapeHtml(ui('tests.empty'))}</div>`;
}

async function renderTest(testId) {
    const result = await api(`tests.php?id=${encodeURIComponent(testId)}&${userQuery()}`);
    state.activeTest = result;
    page.innerHTML = `
        <section class="panel">
            <button class="secondary compact" data-action="back-to-tests">${escapeHtml(ui('tests.back'))}</button>
            <h2>${escapeHtml(result.test.title)}</h2>
            <p class="muted">${escapeHtml(result.test.description || '')}</p>
            <form id="test-form" class="test-form">
                ${result.questions.length ? result.questions.map((question) => renderQuestion(question)).join('') : `<div class="empty">${escapeHtml(ui('tests.no_questions'))}</div>`}
                <button class="primary" type="submit">${escapeHtml(ui('tests.submit'))}</button>
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
        : `<input class="text-answer" name="question_${question.id}" placeholder="${escapeHtml(ui('tests.text_placeholder'))}">`;

    return `
        <fieldset class="question" data-question-id="${question.id}" data-question-type="${type}">
            <legend>${escapeHtml(question.question_text)}</legend>
            ${controls}
        </fieldset>
    `;
}

async function submitTest(form) {
    const submitButton = form.querySelector('button[type="submit"]');
    if (submitButton) {
        submitButton.disabled = true;
        submitButton.dataset.originalText = submitButton.textContent || '';
        submitButton.textContent = ui('tests.submitting', '...');
    }
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

    let result;
    try {
        result = await api('tests.php?action=submit', {
            method: 'POST',
            body: JSON.stringify({
                ...userPayload(),
                test_id: state.activeTest.test.id,
                answers,
            }),
        });
    } catch (error) {
        form.insertAdjacentHTML('afterbegin', `<div class="empty">${escapeHtml(error.message)}</div>`);
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.textContent = submitButton.dataset.originalText || ui('tests.submit');
        }
        return;
    }

    page.innerHTML = `
        <section class="panel">
            <div class="result-card">
                <strong>${escapeHtml(result.result?.title || ui('result.default_title'))}</strong>
                <span class="result-score">${escapeHtml(ui('result.score'))}: ${escapeHtml(result.total_score)}</span>
                <p class="muted">${escapeHtml(result.summary)}</p>
            </div>
            <button class="primary" data-page-target="recommendations">${escapeHtml(ui('result.show_recommendations'))}</button>
            <button class="secondary" data-action="contact">${escapeHtml(ui('lead.contact_manager'))}</button>
        </section>
    `;
}

async function renderProducts() {
    const result = await api(`products.php?${userQuery()}`);
    page.innerHTML = result.products.length
        ? result.products.map((product) => `
            <article class="item">
                ${product.image_path ? `<img class="item-image" src="${escapeHtml(product.image_path)}" alt="">` : ''}
                <strong>${escapeHtml(product.title)}</strong>
                <span class="muted">${escapeHtml(product.short_description || '')}</span>
                <div class="item-links">
                    ${product.document_path ? `<a href="${escapeHtml(product.document_path)}" target="_blank" rel="noopener">PDF</a>` : ''}
                    ${product.video_url ? `<a href="${escapeHtml(product.video_url)}" target="_blank" rel="noopener">${escapeHtml(ui('products.video'))}</a>` : ''}
                    ${product.purchase_url ? `<a href="${escapeHtml(product.purchase_url)}" target="_blank" rel="noopener">${escapeHtml(ui('products.details'))}</a>` : ''}
                </div>
                <button class="secondary" data-product-id="${product.id}">${escapeHtml(ui('products.request_info'))}</button>
            </article>
        `).join('')
        : `<div class="empty">${escapeHtml(ui('products.empty'))}</div>`;
}

async function renderRecommendations() {
    const result = await api(`recommendations.php?${userQuery()}`);
    page.innerHTML = result.recommendations.length
        ? result.recommendations.map((item) => `
            <article class="item">
                <strong>${escapeHtml(item.product_title || ui('recommendations.default_title'))}</strong>
                <span class="muted">${escapeHtml(item.short_description || item.reason_text || '')}</span>
                ${item.product_id ? `<button class="secondary" data-product-id="${item.product_id}">${escapeHtml(ui('products.request_info'))}</button>` : ''}
            </article>
        `).join('')
        : `<div class="empty">${escapeHtml(ui('recommendations.empty'))}</div>`;
}

function responseAttachmentLinks(response) {
    const attachments = Array.isArray(response.attachments)
        ? response.attachments
        : (response.attachment_path ? [response.attachment_path] : []);

    return attachments.map((path, index) => (
        `<a href="${escapeHtml(path)}" target="_blank" rel="noopener">${escapeHtml(ui('lead.file'))} ${index + 1}</a>`
    )).join('');
}

async function renderLeads() {
    const result = await api(`leads.php?${userQuery()}`);
    page.innerHTML = result.leads.length
        ? result.leads.map((lead) => `
            <article class="item">
                <strong>${escapeHtml(ui('leads.title'))} #${lead.id}</strong>
                <span class="muted">${escapeHtml(ui('leads.status'))}: ${escapeHtml(lead.status)}</span>
                <span class="muted">${escapeHtml(ui('leads.platform'))}: ${escapeHtml(lead.source_platform)}</span>
                ${lead.product_title ? `<span>${escapeHtml(lead.product_title)}</span>` : ''}
                ${lead.message ? `<span class="muted">${escapeHtml(lead.message)}</span>` : ''}
                ${(lead.responses || []).map((response) => `
                    <div class="response">
                        <strong>${escapeHtml(ui('leads.manager_response'))}</strong>
                        <span>${escapeHtml(response.message_text || '')}</span>
                        ${responseAttachmentLinks(response)}
                        ${response.external_url ? `<a href="${escapeHtml(response.external_url)}" target="_blank" rel="noopener">${escapeHtml(ui('lead.link'))}</a>` : ''}
                        <span class="muted">${escapeHtml(response.sent_at || response.created_at || '')}</span>
                    </div>
                `).join('')}
            </article>
        `).join('')
        : `<div class="empty">${escapeHtml(ui('leads.empty'))}</div>`;
}

async function contactManager(productId = null) {
    const payload = {
        ...userPayload(),
        product_id: productId,
        message: productId ? ui('lead.product_request_message') : ui('lead.contact_request_message'),
    };
    const result = await api('contact_manager.php', {
        method: 'POST',
        body: JSON.stringify(payload),
    });
    page.insertAdjacentHTML('afterbegin', `
        <div class="panel">
            ${escapeHtml(formatUi('lead.created', {id: result.lead_id}))}
            <button class="secondary compact" data-page-target="leads">${escapeHtml(ui('lead.my_leads'))}</button>
        </div>
    `);
}

async function render() {
    page.innerHTML = `<div class="empty">${escapeHtml(ui('common.loading'))}</div>`;
    try {
        if (state.page === 'home') renderHome();
        if (state.page === 'profile') await renderProfile();
        if (state.page === 'tests') {
            if (state.initialTestId) {
                const testId = state.initialTestId;
                state.initialTestId = null;
                await renderTest(testId);
            } else {
                await renderTests();
            }
        }
        if (state.page === 'products') await renderProducts();
        if (state.page === 'recommendations') await renderRecommendations();
        if (state.page === 'leads') await renderLeads();
    } catch (error) {
        page.innerHTML = `<div class="empty">${escapeHtml(error.message)}</div>`;
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

applyInitialRoute();

Promise.all([loadI18n(), authorize()])
    .then(render)
    .catch((error) => {
        page.innerHTML = `<div class="empty">${escapeHtml(error.message)}</div>`;
    });
