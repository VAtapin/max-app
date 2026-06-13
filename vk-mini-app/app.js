const API_BASE = '../api';

const state = {
    user: null,
    auth: null,
    vkUser: null,
    platform: null,
    platformUserId: null,
    authBlocked: null,
    page: 'home',
    activeTest: null,
    initialTestId: null,
    i18n: {},
    defaultManager: null,
    consultantProfile: null,
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

function apiErrorMessage(code, fallback = '') {
    return ui(`api_error.${code}`, fallback || code);
}

class AppApiError extends Error {
    constructor(code, message) {
        super(message);
        this.code = code;
    }
}

async function api(path, options = {}) {
    const response = await fetch(`${API_BASE}/${path}`, {
        headers: {'Content-Type': 'application/json'},
        ...options,
    });
    if (!response.ok) {
        let message = `API error ${response.status}`;
        let code = null;
        try {
            const error = await response.json();
            if (error.error) {
                code = error.error;
                message = apiErrorMessage(code, code);
            }
        } catch (_) {
            // Keep the default message when the response is not JSON.
        }
        throw new AppApiError(code, message);
    }
    return response.json();
}

function getLinkToken() {
    const hash = new URLSearchParams(window.location.hash.replace(/^#/, ''));
    const search = new URLSearchParams(window.location.search);
    return hash.get('link_token') || search.get('link_token') || null;
}

function getTelegramApp() {
    return window.Telegram && window.Telegram.WebApp ? window.Telegram.WebApp : null;
}

async function initTelegram() {
    const tg = getTelegramApp();
    if (!tg || !tg.initData) {
        return null;
    }

    tg.ready();
    tg.expand();
    const result = await api('telegram_auth.php', {
        method: 'POST',
        body: JSON.stringify({
            init_data: tg.initData,
            referral_code: getReferralCode(),
            link_token: getLinkToken(),
        }),
    });

    state.auth = result.auth;
    state.platform = result.auth.platform;
    state.platformUserId = result.auth.platform_user_id;
    state.user = result.user;
    state.defaultManager = result.default_manager || null;
    return result.user;
}

function telegramInitData() {
    const tg = getTelegramApp();
    return tg && tg.initData ? tg.initData : '';
}

function vkLaunchParams() {
    return new URLSearchParams(window.location.search);
}

function hasVkLaunchParams() {
    const params = vkLaunchParams();
    return params.has('vk_app_id') || params.has('vk_user_id') || params.has('vk_ok_user_id');
}

async function initVk() {
    if (!window.vkBridge || !hasVkLaunchParams()) {
        return null;
    }

    await vkBridge.send('VKWebAppInit');
    return vkBridge.send('VKWebAppGetUserInfo');
}

function buildVkOkIdentity(vkUser) {
    const params = vkLaunchParams();
    const vkClient = params.get('vk_client') || '';
    const vkPlatform = params.get('vk_platform') || '';
    const okUserId = params.get('vk_ok_user_id') || '';
    const isOk = vkClient === 'ok' || vkPlatform.includes('ok') || okUserId !== '';
    const platform = isOk ? 'OK' : 'VK';
    const platformUserId = isOk && okUserId !== '' ? okUserId : String(vkUser.id);

    return {platform, platformUserId};
}

async function authorize() {
    if (await initTelegram()) {
        return state.user;
    }

    state.vkUser = await initVk();
    if (!state.vkUser) {
        return null;
    }

    const identity = buildVkOkIdentity(state.vkUser);
    state.platform = identity.platform;
    state.platformUserId = identity.platformUserId;
    state.auth = {
        platform: state.platform,
        platform_user_id: state.platformUserId,
    };
    const payload = {
        platform: state.platform,
        platform_user_id: state.platformUserId,
        first_name: state.vkUser.first_name,
        last_name: state.vkUser.last_name,
        username: state.vkUser.domain,
        referral_code: getReferralCode(),
        link_token: getLinkToken(),
        platform_meta: Object.fromEntries(vkLaunchParams().entries()),
    };
    const result = await api('auth.php', {
        method: 'POST',
        body: JSON.stringify(payload),
    });
    state.user = result.user;
    state.defaultManager = result.default_manager || null;
    return result.user;
}

async function authorizeWithReferral(referralCode) {
    if (telegramInitData()) {
        const result = await api('telegram_auth.php', {
            method: 'POST',
            body: JSON.stringify({
                init_data: telegramInitData(),
                referral_code: referralCode,
            }),
        });
        state.auth = result.auth;
        state.platform = result.auth.platform;
        state.platformUserId = result.auth.platform_user_id;
        state.user = result.user;
        state.defaultManager = result.default_manager || null;
        return result.user;
    }

    if (!state.vkUser || !state.platformUserId) {
        throw new Error(ui('auth.required_text'));
    }

    const result = await api('auth.php', {
        method: 'POST',
        body: JSON.stringify({
            platform: state.platform,
            platform_user_id: state.platformUserId,
            first_name: state.vkUser.first_name,
            last_name: state.vkUser.last_name,
            username: state.vkUser.domain,
            referral_code: referralCode,
            link_token: getLinkToken(),
            platform_meta: Object.fromEntries(vkLaunchParams().entries()),
        }),
    });
    state.user = result.user;
    state.defaultManager = result.default_manager || null;
    return result.user;
}

function userQuery() {
    const params = new URLSearchParams({
        platform: state.auth.platform,
        platform_user_id: state.auth.platform_user_id,
    });
    if (state.auth.auth_token) {
        params.set('auth_token', state.auth.auth_token);
    }
    return params.toString();
}

function userPayload() {
    const payload = {
        platform: state.auth.platform,
        platform_user_id: String(state.auth.platform_user_id),
    };
    if (state.auth.auth_token) {
        payload.auth_token = state.auth.auth_token;
    }
    return payload;
}

function hasTeamAccess() {
    return Boolean(state.user && (state.user.reseller_id || state.user.manager_id));
}

function profileBlockEnabled(blockType) {
    const blocks = state.consultantProfile?.blocks || [];
    const block = blocks.find((item) => item.block_type === blockType);
    return !block || Number(block.is_enabled) === 1;
}

function profileBlockTitle(blockType, fallbackKey) {
    const blocks = state.consultantProfile?.blocks || [];
    const block = blocks.find((item) => item.block_type === blockType);
    return block?.title || ui(fallbackKey);
}

function profileContactLink(profile) {
    return profile.telegram_url || profile.whatsapp_url || profile.vk_url || profile.ok_url || '';
}

function platformLabel(platform) {
    return ui(`platform.${String(platform || '').toLowerCase()}`, platform || '');
}

function leadStatusLabel(status) {
    return ui(`lead_status.${status}`, status || '');
}

function userDisplayName(user) {
    return [user.first_name, user.last_name].filter(Boolean).join(' ') || user.username || ui('profile.client');
}

function friendlyError(error) {
    if (error instanceof AppApiError && error.message && !error.message.startsWith('API error')) {
        return error.message;
    }
    return ui('common.load_failed');
}

async function loadConsultantProfile() {
    if (!hasTeamAccess()) {
        state.consultantProfile = null;
        return null;
    }

    const result = await api(`profile.php?${userQuery()}`);
    state.consultantProfile = result;
    return result;
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
    if (!state.user || !hasTeamAccess()) return;
    state.page = nextPage;
    tabs.forEach((tab) => tab.classList.toggle('active', tab.dataset.page === nextPage));
    render();
}

function renderAuthGate() {
    document.body.classList.add('auth-required');
    document.body.classList.remove('referral-required');
    tabs.forEach((tab) => {
        tab.disabled = true;
        tab.classList.remove('active');
    });
    page.innerHTML = `
        <section class="panel auth-panel">
            <h2>${escapeHtml(ui('auth.required_title'))}</h2>
            <p class="muted">${escapeHtml(ui('auth.required_text'))}</p>
            <div class="auth-platforms">
                <span>Telegram</span>
                <span>VK</span>
                <span>OK</span>
                <span>MAX</span>
            </div>
        </section>
    `;
}

function renderStaffGate() {
    document.body.classList.add('auth-required');
    document.body.classList.remove('referral-required');
    tabs.forEach((tab) => {
        tab.disabled = true;
        tab.classList.remove('active');
    });
    page.innerHTML = `
        <section class="panel auth-panel">
            <h2>${escapeHtml(ui('staff.blocked_title'))}</h2>
            <p class="muted">${escapeHtml(ui('staff.blocked_text'))}</p>
        </section>
    `;
}

function renderReferralGate() {
    document.body.classList.add('referral-required');
    document.body.classList.remove('auth-required');
    tabs.forEach((tab) => {
        tab.disabled = true;
        tab.classList.remove('active');
    });
    const manager = state.defaultManager;
    page.innerHTML = `
        <section class="panel auth-panel">
            <h2>${escapeHtml(ui('referral.required_title'))}</h2>
            <p class="muted">${escapeHtml(ui('referral.required_text'))}</p>
            ${manager ? `
                <div class="default-manager">
                    <div class="default-avatar">${escapeHtml((manager.manager_name || 'SW').slice(0, 2).toUpperCase())}</div>
                    <div>
                        <strong>${escapeHtml(manager.manager_name || ui('referral.default_manager'))}</strong>
                        ${manager.reseller_name ? `<span class="muted">${escapeHtml(manager.reseller_name)}</span>` : ''}
                        <span class="code">${escapeHtml(manager.referral_code || '')}</span>
                    </div>
                </div>
            ` : ''}
            <form class="referral-form" id="referral-form">
                <input name="referral_code" autocomplete="one-time-code" required value="${escapeHtml(manager?.referral_code || '')}" placeholder="${escapeHtml(ui('referral.code_placeholder'))}">
                <button class="primary" type="submit">${escapeHtml(ui('referral.submit'))}</button>
                <div class="form-error" id="referral-error"></div>
            </form>
        </section>
    `;
}

function renderHome() {
    const data = state.consultantProfile || {};
    const profile = data.profile || {};
    const products = data.products || [];
    const tests = data.tests || [];
    const materials = data.materials || [];
    const initials = String(profile.display_name || 'SW').slice(0, 2).toUpperCase();
    const contactLink = profileContactLink(profile);

    page.innerHTML = `
        <section class="home-hero">
            ${profile.banner_path ? `<img class="home-banner" src="${escapeHtml(profile.banner_path)}" alt="">` : ''}
            <div class="consultant-strip">
                ${profile.photo_path ? `<img class="consultant-photo" src="${escapeHtml(profile.photo_path)}" alt="">` : `<div class="consultant-photo placeholder">${escapeHtml(initials)}</div>`}
                <div class="consultant-meta">
                    <span class="eyebrow">${escapeHtml(profile.title || ui('home.consultant'))}</span>
                    <h2>${escapeHtml(profile.display_name || ui('home.title'))}</h2>
                    <p>${escapeHtml(profile.subtitle || ui('home.default_subtitle'))}</p>
                </div>
            </div>
            ${profile.short_description ? `<p class="consultant-note">${escapeHtml(profile.short_description)}</p>` : ''}
            <div class="hero-actions">
                <button class="primary" data-action="contact">${escapeHtml(ui('home.ask_manager'))}</button>
                ${contactLink ? `<a class="soft-link" href="${escapeHtml(contactLink)}" target="_blank" rel="noopener">${escapeHtml(ui('home.open_contact'))}</a>` : ''}
                ${profile.video_url ? `<a class="soft-link" href="${escapeHtml(profile.video_url)}" target="_blank" rel="noopener">${escapeHtml(ui('home.watch_video'))}</a>` : ''}
            </div>
        </section>

        <section class="action-row">
            <button class="action-card" data-action="tests">
                <span>01</span>
                <strong>${escapeHtml(ui('home.start_test'))}</strong>
                <small>${escapeHtml(ui('home.start_test_hint'))}</small>
            </button>
            <button class="action-card" data-page-target="recommendations">
                <span>02</span>
                <strong>${escapeHtml(ui('home.show_recommendations'))}</strong>
                <small>${escapeHtml(ui('home.recommendations_hint'))}</small>
            </button>
            <button class="action-card" data-action="contact">
                <span>03</span>
                <strong>${escapeHtml(ui('home.write_manager'))}</strong>
                <small>${escapeHtml(ui('home.write_manager_hint'))}</small>
            </button>
        </section>

        ${profileBlockEnabled('tests') ? `
            <section class="home-section">
                <div class="section-title">
                    <h2>${escapeHtml(profileBlockTitle('tests', 'home.recommended_tests'))}</h2>
                    <button class="text-button" data-action="tests">${escapeHtml(ui('common.all'))}</button>
                </div>
                ${tests.length ? `
                    <div class="horizontal-list">
                        ${tests.slice(0, 4).map((test) => `
                            <article class="diagnostic-card">
                                <span class="diagnostic-icon">✓</span>
                                <strong>${escapeHtml(test.title)}</strong>
                                <span class="muted">${escapeHtml(test.description || '')}</span>
                                <button class="secondary compact" data-open-test-id="${test.id}">${escapeHtml(ui('tests.open'))}</button>
                            </article>
                        `).join('')}
                    </div>
                ` : `<div class="empty-card">${escapeHtml(ui('home.no_tests'))}</div>`}
            </section>
        ` : ''}

        ${profileBlockEnabled('products') ? `
            <section class="home-section">
                <h2>${escapeHtml(profileBlockTitle('products', 'home.consultant_recommendations'))}</h2>
                ${products.length ? `<div class="horizontal-list">
                    ${products.slice(0, 4).map((product) => `
                        <article class="recommend-card">
                            ${product.image_path ? `<img src="${escapeHtml(product.image_path)}" alt="">` : ''}
                            <strong>${escapeHtml(product.title)}</strong>
                            <span class="muted">${escapeHtml(product.short_description || '')}</span>
                            <button class="secondary compact" data-product-id="${product.id}">${escapeHtml(ui('products.request_info'))}</button>
                        </article>
                    `).join('')}
                </div>` : `<div class="empty-card">${escapeHtml(ui('home.no_products'))}</div>`}
            </section>
        ` : ''}

        ${profileBlockEnabled('materials') ? `
            <section class="home-section">
                <h2>${escapeHtml(profileBlockTitle('materials', 'home.materials'))}</h2>
                ${materials.length ? `<div class="card-list">
                    ${materials.slice(0, 3).map((material) => `
                        <article class="material-card">
                            ${material.image_path ? `<img src="${escapeHtml(material.image_path)}" alt="">` : ''}
                            <strong>${escapeHtml(material.title)}</strong>
                            <span class="muted">${escapeHtml(material.short_text || '')}</span>
                        </article>
                    `).join('')}
                </div>` : `<div class="empty-card">${escapeHtml(ui('home.no_materials'))}</div>`}
            </section>
        ` : ''}
    `;
}

async function renderProfile() {
    const result = await api(`user.php?${userQuery()}`);
    const user = result.user;
    const accounts = result.platform_accounts || [];
    const profile = state.consultantProfile?.profile || {};
    page.innerHTML = `
        <section class="profile-card">
            <span class="eyebrow">${escapeHtml(ui('profile.title'))}</span>
            <h2>${escapeHtml(userDisplayName(user))}</h2>
            <div class="profile-lines">
                <div>
                    <span>${escapeHtml(ui('profile.manager'))}</span>
                    <strong>${escapeHtml(profile.display_name || ui('profile.manager_later'))}</strong>
                </div>
                <div>
                    <span>${escapeHtml(ui('profile.platform'))}</span>
                    <strong>${escapeHtml(platformLabel(state.platform))}</strong>
                </div>
            </div>
        </section>
        <section class="home-section">
            <h2>${escapeHtml(ui('profile.accounts'))}</h2>
            ${accounts.length ? accounts.map((account) => `
                <article class="platform-card">
                    <span class="platform-pill">${escapeHtml(platformLabel(account.platform))}</span>
                    <strong>${escapeHtml(account.display_name || account.username || ui('profile.platform_account'))}</strong>
                    ${account.username ? `<span class="muted">${escapeHtml(account.username)}</span>` : ''}
                </article>
            `).join('') : `<div class="empty-card">${escapeHtml(ui('profile.no_accounts'))}</div>`}
            <button class="secondary" data-action="create-link-token">${escapeHtml(ui('profile.connect_platform'))}</button>
            <div class="link-panel" id="link-panel"></div>
        </section>
    `;
}

async function renderAccountLinkPanel() {
    const panel = document.querySelector('#link-panel');
    if (!panel) return;
    panel.innerHTML = `<div class="empty">${escapeHtml(ui('common.loading'))}</div>`;
    try {
        const result = await api('account_link.php', {
            method: 'POST',
            body: JSON.stringify(userPayload()),
        });
        const miniAppLink = result.links?.mini_app || '';
        const telegramLink = result.links?.telegram || '';
        panel.innerHTML = `
            <div class="link-card">
                <strong>${escapeHtml(ui('profile.link_title', 'Ссылка для подключения'))}</strong>
                <span class="muted">${escapeHtml(ui('profile.link_hint', 'Откройте ссылку на другой платформе и подтвердите вход.'))}</span>
                ${miniAppLink ? `<a href="${escapeHtml(miniAppLink)}" target="_blank" rel="noopener">${escapeHtml(ui('profile.open_mini_app', 'Открыть Mini App'))}</a>` : ''}
                ${telegramLink ? `<a href="${escapeHtml(telegramLink)}" target="_blank" rel="noopener">${escapeHtml(ui('profile.open_telegram', 'Подключить Telegram'))}</a>` : ''}
            </div>
        `;
    } catch (error) {
        panel.innerHTML = `<div class="empty">${escapeHtml(error.message)}</div>`;
    }
}

async function renderTests() {
    const result = await api(`tests.php?${userQuery()}`);
    page.innerHTML = result.tests.length
        ? result.tests.map((test) => `
            <article class="diagnostic-card">
                <span class="diagnostic-icon">✓</span>
                <strong>${escapeHtml(test.title)}</strong>
                <span class="muted">${escapeHtml(test.description || '')}</span>
                <button class="secondary" data-open-test-id="${test.id}">${escapeHtml(ui('tests.open'))}</button>
            </article>
        `).join('')
        : `<div class="empty-card">${escapeHtml(ui('tests.empty'))}</div>`;
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
            <article class="recommendation-card">
                <span class="eyebrow">${escapeHtml(ui('recommendations.reason'))}</span>
                <strong>${escapeHtml(item.product_title || ui('recommendations.default_title'))}</strong>
                <span class="muted">${escapeHtml(item.short_description || item.reason_text || '')}</span>
                <div class="recommendation-actions">
                    ${item.product_id ? `<button class="secondary compact" data-product-id="${item.product_id}">${escapeHtml(ui('products.request_info'))}</button>` : ''}
                    <button class="secondary compact" data-action="contact">${escapeHtml(ui('home.write_manager'))}</button>
                </div>
            </article>
        `).join('')
        : `<div class="empty-card">${escapeHtml(ui('recommendations.empty'))}</div>`;
}

function responseAttachmentLinks(response) {
    const attachments = Array.isArray(response.attachments)
        ? response.attachments
        : (response.attachment_path ? [response.attachment_path] : []);

    return attachments.map((path, index) => (
        `<a class="response-file-link" href="${escapeHtml(path)}" target="_blank" rel="noopener">${escapeHtml(ui('lead.file'))} ${index + 1}</a>`
    )).join('');
}

function responseTextParagraphs(response) {
    const content = response.content || null;
    const contentTexts = [
        content?.short_text,
        content?.full_text,
        content?.title ? `${ui('lead_response.material')}: ${content.title}` : null,
        content?.title ? `Материал: ${content.title}` : null,
    ].filter(Boolean).map((value) => String(value).trim());

    return String(response.message_text || '')
        .split(/\n{2,}/)
        .map((item) => item.trim())
        .filter(Boolean)
        .filter((item) => !/^(Источник заявки|Материал|Рекомендуем пройти тест):/i.test(item))
        .filter((item) => !contentTexts.includes(item));
}

function renderResponseText(response) {
    const paragraphs = responseTextParagraphs(response);
    if (!paragraphs.length) {
        return '';
    }

    return `
        <div class="response-text">
            ${paragraphs.map((paragraph) => `<p>${escapeHtml(paragraph)}</p>`).join('')}
        </div>
    `;
}

function renderResponseMaterial(response) {
    const content = response.content;
    if (!content) {
        return '';
    }

    const text = content.short_text || content.full_text || '';
    return `
        <article class="response-resource">
            <span class="response-resource-type">${escapeHtml(ui('lead_response.material'))}</span>
            ${content.image_path ? `<img src="${escapeHtml(content.image_path)}" alt="">` : ''}
            <strong>${escapeHtml(content.title || ui('lead_response.material'))}</strong>
            ${text ? `<p>${escapeHtml(text)}</p>` : ''}
            <div class="response-resource-actions">
                ${content.attachment_path ? `<a href="${escapeHtml(content.attachment_path)}" target="_blank" rel="noopener">${escapeHtml(ui('lead_response.open_file'))}</a>` : ''}
                ${content.video_url ? `<a href="${escapeHtml(content.video_url)}" target="_blank" rel="noopener">${escapeHtml(ui('lead_response.open_video'))}</a>` : ''}
                ${content.button_url ? `<a href="${escapeHtml(content.button_url)}" target="_blank" rel="noopener">${escapeHtml(content.button_text || ui('lead_response.open_material'))}</a>` : ''}
            </div>
        </article>
    `;
}

function renderResponseTest(response) {
    const test = response.test;
    if (!test) {
        return '';
    }

    return `
        <article class="response-resource">
            <span class="response-resource-type">${escapeHtml(ui('lead_response.test'))}</span>
            <strong>${escapeHtml(test.title || ui('tests.open'))}</strong>
            ${test.description ? `<p>${escapeHtml(test.description)}</p>` : ''}
            <button class="secondary compact" data-open-test-id="${test.id}">${escapeHtml(ui('tests.open'))}</button>
        </article>
    `;
}

function renderResponseFiles(response) {
    const files = responseAttachmentLinks(response);
    if (!files && !response.external_url) {
        return '';
    }

    return `
        <div class="response-files">
            <span>${escapeHtml(ui('lead_response.attachments'))}</span>
            <div class="item-links">
                ${files}
                ${response.external_url ? `<a class="response-file-link" href="${escapeHtml(response.external_url)}" target="_blank" rel="noopener">${escapeHtml(ui('lead.link'))}</a>` : ''}
            </div>
        </div>
    `;
}

function leadTitle(lead) {
    if (lead.product_title) {
        return formatUi('leads.product_question', {product: lead.product_title});
    }
    return ui('leads.question');
}

function leadHasUnreadResponse(lead) {
    return (lead.responses || []).some((response) => !response.read_at);
}

async function markLeadRead(leadId) {
    await api('leads.php?action=mark_read', {
        method: 'POST',
        body: JSON.stringify({
            ...userPayload(),
            lead_id: leadId,
        }),
    });
}

async function renderLeads() {
    const result = await api(`leads.php?${userQuery()}`);
    const unreadLeadIds = result.leads.filter(leadHasUnreadResponse).map((lead) => lead.id);
    page.innerHTML = result.leads.length
        ? result.leads.map((lead) => `
            <article class="lead-chat-card">
                <div class="lead-chat-head">
                    <div>
                        <strong>${escapeHtml(leadTitle(lead))}</strong>
                        <span class="muted">${escapeHtml(platformLabel(lead.source_platform))} · ${escapeHtml(lead.created_at || '')}</span>
                    </div>
                    <span class="status-pill">${escapeHtml(leadStatusLabel(lead.status))}</span>
                </div>
                ${leadHasUnreadResponse(lead) ? `<span class="badge standalone">${escapeHtml(ui('leads.new_response'))}</span>` : ''}
                ${lead.message ? `
                    <div class="chat-bubble client">
                        <span>${escapeHtml(ui('leads.client_message'))}</span>
                        <p>${escapeHtml(lead.message)}</p>
                    </div>
                ` : ''}
                ${(lead.responses || []).map((response) => `
                    <div class="chat-bubble manager">
                        <span>${escapeHtml(ui('leads.manager_response'))}</span>
                        ${renderResponseText(response)}
                        ${renderResponseMaterial(response)}
                        ${renderResponseTest(response)}
                        ${renderResponseFiles(response)}
                        <small class="muted">${escapeHtml(response.sent_at || response.created_at || '')}</small>
                    </div>
                `).join('')}
            </article>
        `).join('')
        : `<div class="empty-card">${escapeHtml(ui('leads.empty'))}</div>`;

    await Promise.allSettled(unreadLeadIds.map(markLeadRead));
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
    if (state.authBlocked === 'staff') {
        renderStaffGate();
        return;
    }
    if (!hasTeamAccess()) {
        renderReferralGate();
        return;
    }
    if (!state.consultantProfile) {
        await loadConsultantProfile();
    }
    document.body.classList.remove('auth-required', 'referral-required');
    tabs.forEach((tab) => {
        tab.disabled = false;
        tab.classList.toggle('active', tab.dataset.page === state.page);
    });
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
        page.innerHTML = `<div class="empty-card">${escapeHtml(friendlyError(error))}</div>`;
    }
}

tabs.forEach((tab) => {
    tab.addEventListener('click', () => setPage(tab.dataset.page));
});

page.addEventListener('click', async (event) => {
    const clicked = event.target;
    if (!(clicked instanceof HTMLElement)) return;
    const target = clicked.closest('[data-action], [data-page-target], [data-open-test-id], [data-product-id]');
    if (!(target instanceof HTMLElement)) return;
    if (target.dataset.action === 'tests') setPage('tests');
    if (target.dataset.action === 'back-to-tests') await renderTests();
    if (target.dataset.action === 'contact') await contactManager();
    if (target.dataset.action === 'create-link-token') await renderAccountLinkPanel();
    if (target.dataset.pageTarget) setPage(target.dataset.pageTarget);
    if (target.dataset.openTestId) await renderTest(Number(target.dataset.openTestId));
    if (target.dataset.productId) await contactManager(Number(target.dataset.productId));
});

page.addEventListener('submit', async (event) => {
    const target = event.target;
    if (target instanceof HTMLFormElement && target.id === 'referral-form') {
        event.preventDefault();
        const error = target.querySelector('#referral-error');
        const button = target.querySelector('button[type="submit"]');
        const formData = new FormData(target);
        const referralCode = String(formData.get('referral_code') || '').trim();
        if (!referralCode) return;
        if (error) error.textContent = '';
        if (button) button.disabled = true;
        try {
            await authorizeWithReferral(referralCode);
            if (!hasTeamAccess()) {
                throw new Error(ui('referral.invalid_code'));
            }
            state.page = 'home';
            await render();
        } catch (exception) {
            if (error) error.textContent = exception instanceof Error ? exception.message : ui('referral.invalid_code');
        } finally {
            if (button) button.disabled = false;
        }
        return;
    }
    if (!(target instanceof HTMLFormElement) || target.id !== 'test-form') return;
    event.preventDefault();
    await submitTest(target);
});

applyInitialRoute();

loadI18n()
    .then(() => authorize())
    .then(() => {
        if (!state.user) {
            renderAuthGate();
            return;
        }
        render();
    })
    .catch((error) => {
        if (error instanceof AppApiError && error.code === 'staff_client_registration_blocked') {
            state.authBlocked = 'staff';
            renderStaffGate();
            return;
        }
        page.innerHTML = `<div class="empty-card">${escapeHtml(friendlyError(error))}</div>`;
    });
