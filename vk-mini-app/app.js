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
    consultantProfilePromise: null,
};

const page = document.querySelector('#page');
const tabs = document.querySelectorAll('.tabs button');

function getReferralCode() {
    const hash = new URLSearchParams(window.location.hash.replace(/^#/, ''));
    const search = new URLSearchParams(window.location.search);
    return hash.get('ref') || search.get('ref') || search.get('startapp') || null;
}

function normalizeReferralCodeInput(value) {
    const code = String(value || '').trim();
    return code.startsWith('ref_') ? code.slice(4).trim() : code;
}

function applyInitialRoute() {
    const search = new URLSearchParams(window.location.search);
    const pageName = search.get('page');
    const testId = Number(search.get('test_id') || 0);
    if (['home', 'profile', 'tests', 'products', 'recommendations', 'leads', 'results'].includes(pageName || '')) {
        state.page = pageName;
    }
    if (testId > 0) {
        state.page = 'tests';
        state.initialTestId = testId;
    }
}

async function loadI18n() {
    try {
        const response = await fetch('i18n/ru.json?v=20260629-1', {cache: 'force-cache'});
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

function hasTelegramLaunchParams() {
    const hash = new URLSearchParams(window.location.hash.replace(/^#/, ''));
    const search = new URLSearchParams(window.location.search);
    return hash.has('tgWebAppData') || search.has('tgWebAppData') || hash.has('tgWebAppVersion') || search.has('tgWebAppVersion');
}

async function waitForTelegramApp(timeoutMs = 900) {
    if (getTelegramApp() || !hasTelegramLaunchParams()) {
        return getTelegramApp();
    }

    const startedAt = Date.now();
    while (Date.now() - startedAt < timeoutMs) {
        await new Promise((resolve) => setTimeout(resolve, 30));
        const tg = getTelegramApp();
        if (tg) {
            return tg;
        }
    }
    return getTelegramApp();
}

async function initTelegram() {
    const tg = getTelegramApp() || await waitForTelegramApp();
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
    if (!hasVkLaunchParams()) {
        return null;
    }

    if (window.vkBridge) {
        try {
            await vkBridge.send('VKWebAppInit');
            return await vkBridge.send('VKWebAppGetUserInfo');
        } catch (_) {
            // VK moderation can open the app with launch params before bridge user info is available.
        }
    }

    const params = vkLaunchParams();
    const fallbackId = params.get('vk_user_id') || params.get('vk_ok_user_id');
    return fallbackId ? {
        id: fallbackId,
        first_name: 'VK',
        last_name: 'User',
        domain: '',
    } : null;
}

async function initWebUser() {
    const storedReferralCode = localStorage.getItem('swpro_pending_referral_code') || '';
    const referralCode = getReferralCode() || storedReferralCode;
    const linkToken = getLinkToken();
    let webUserId = localStorage.getItem('swpro_web_user_id');
    if (!webUserId && (referralCode || linkToken)) {
        webUserId = `web-${crypto.randomUUID ? crypto.randomUUID() : Date.now()}`;
        localStorage.setItem('swpro_web_user_id', webUserId);
    }
    if (!webUserId) {
        return null;
    }

    const result = await api('auth.php', {
        method: 'POST',
        body: JSON.stringify({
            platform: 'web',
            platform_user_id: webUserId,
            first_name: 'Web',
            last_name: 'User',
            referral_code: referralCode,
            link_token: linkToken,
        }),
    });

    state.platform = 'web';
    state.platformUserId = webUserId;
    state.auth = {platform: 'web', platform_user_id: webUserId};
    state.user = result.user;
    state.defaultManager = result.default_manager || null;
    if (referralCode && hasTeamAccess()) {
        localStorage.setItem('swpro_last_referral_code', referralCode);
        localStorage.removeItem('swpro_pending_referral_code');
    }
    return result.user;
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
        return await initWebUser();
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

function youtubeEmbedUrl(url) {
    try {
        const parsed = new URL(url);
        const host = parsed.hostname.toLowerCase();
        let videoId = '';
        if (host.includes('youtu.be')) {
            videoId = parsed.pathname.replace(/^\/+/, '').split('/')[0] || '';
        } else if (host.includes('youtube.com')) {
            if (parsed.pathname === '/watch') {
                videoId = parsed.searchParams.get('v') || '';
            } else if (parsed.pathname.startsWith('/shorts/')) {
                videoId = parsed.pathname.replace('/shorts/', '').split('/')[0] || '';
            } else if (parsed.pathname.startsWith('/embed/')) {
                videoId = parsed.pathname.replace('/embed/', '').split('/')[0] || '';
            }
        }
        return /^[a-zA-Z0-9_-]{6,}$/.test(videoId) ? `https://www.youtube.com/embed/${encodeURIComponent(videoId)}` : '';
    } catch (_) {
        return '';
    }
}

function consultantAboutSections(profile) {
    return [
        ['bio', ui('consultant.bio')],
        ['specialization', ui('consultant.specialization')],
        ['experience_text', ui('consultant.experience')],
        ['certificates_text', ui('consultant.certificates')],
        ['achievements_text', ui('consultant.achievements')],
    ]
        .map(([field, title]) => ({field, title, text: String(profile[field] || '').trim()}))
        .filter((section) => section.text !== '');
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
    if (state.consultantProfile) {
        return state.consultantProfile;
    }
    if (state.consultantProfilePromise) {
        return state.consultantProfilePromise;
    }

    state.consultantProfilePromise = api(`profile.php?${userQuery()}`)
        .then((result) => {
            state.consultantProfile = result;
            applyTheme(result.profile?.theme_key || 'classic');
            return result;
        })
        .finally(() => {
            state.consultantProfilePromise = null;
        });

    return state.consultantProfilePromise;
}

function pageNeedsConsultantProfile(pageName = state.page) {
    return pageName === 'home' || pageName === 'profile';
}

function runWhenIdle(callback) {
    if ('requestIdleCallback' in window) {
        window.requestIdleCallback(callback, {timeout: 1800});
        return;
    }
    window.setTimeout(callback, 350);
}

function prefetchConsultantProfile() {
    if (!hasTeamAccess() || state.consultantProfile || state.consultantProfilePromise) {
        return;
    }
    runWhenIdle(() => {
        loadConsultantProfile()
            .then(() => {
                if (state.page === 'home') {
                    renderHome();
                }
            })
            .catch(() => {});
    });
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function renderTextBlocks(value) {
    return String(value || '')
        .split(/\n{2,}/)
        .map((item) => item.trim())
        .filter(Boolean)
        .map((paragraph) => `<p>${escapeHtml(paragraph).replaceAll('\n', '<br>')}</p>`)
        .join('');
}

function renderVideoBlock(url, title) {
    if (!url) {
        return '';
    }

    const embed = youtubeEmbedUrl(url);
    if (embed) {
        return `
            <div class="detail-video">
                <iframe src="${escapeHtml(embed)}" title="${escapeHtml(title)}" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
            </div>
        `;
    }

    return `<a class="soft-link" href="${escapeHtml(url)}" target="_blank" rel="noopener">${escapeHtml(ui('products.video'))}</a>`;
}

function openPlatformUrl(url) {
    if (!url) {
        return;
    }

    const absoluteUrl = new URL(url, window.location.href).toString();
    const tg = getTelegramApp();
    if (tg && typeof tg.openLink === 'function') {
        tg.openLink(absoluteUrl);
        return;
    }
    if (window.vkBridge) {
        vkBridge.send('VKWebAppOpenLink', {url: absoluteUrl}).catch(() => {
            window.open(absoluteUrl, '_blank', 'noopener');
        });
        return;
    }
    window.open(absoluteUrl, '_blank', 'noopener');
}

function lazyImageAttrs() {
    return 'loading="lazy" decoding="async"';
}

function applyTheme(themeKey) {
    const allowed = ['classic', 'ocean', 'berry', 'graphite'];
    const key = allowed.includes(String(themeKey || '')) ? String(themeKey) : 'classic';
    document.body.dataset.theme = key;
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
    const linkReferralCode = normalizeReferralCodeInput(getReferralCode());
    const suggestedCode = linkReferralCode || manager?.referral_code || '';
    page.innerHTML = `
        <section class="panel auth-panel">
            <h2>${escapeHtml(ui('referral.required_title'))}</h2>
            <p class="muted">${escapeHtml(ui('referral.required_text'))}</p>
            ${linkReferralCode ? `
                <div class="link-card">
                    <strong>${escapeHtml(ui('referral.link_code_title'))}</strong>
                    <span class="code">${escapeHtml(linkReferralCode)}</span>
                    <span class="muted">${escapeHtml(ui('referral.link_code_hint'))}</span>
                </div>
            ` : ''}
            ${!linkReferralCode && manager ? `
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
                <input name="referral_code" autocomplete="one-time-code" required value="${escapeHtml(suggestedCode)}" placeholder="${escapeHtml(ui('referral.code_placeholder'))}">
                <button class="primary" type="submit">${escapeHtml(ui('referral.submit'))}</button>
                <div class="form-error" id="referral-error"></div>
            </form>
        </section>
    `;
}

function renderHome() {
    const data = state.consultantProfile || {};
    const profileReady = Boolean(state.consultantProfile);
    const profile = data.profile || {};
    const products = data.products || [];
    const tests = data.tests || [];
    const materials = data.materials || [];
    const initials = String(profile.display_name || 'SW').slice(0, 2).toUpperCase();
    const contactLink = profileContactLink(profile);
    const videoEmbed = profile.video_url ? youtubeEmbedUrl(profile.video_url) : '';
    const aboutSections = consultantAboutSections(profile);

    page.innerHTML = `
        <section class="home-hero">
            ${profile.banner_path ? `<img class="home-banner" src="${escapeHtml(profile.banner_path)}" alt="" ${lazyImageAttrs()}>` : ''}
            <div class="consultant-strip">
                ${profile.photo_path ? `<img class="consultant-photo" src="${escapeHtml(profile.photo_path)}" alt="" ${lazyImageAttrs()}>` : `<div class="consultant-photo placeholder">${escapeHtml(initials)}</div>`}
                <div class="consultant-meta">
                    <span class="eyebrow">${escapeHtml(profile.title || ui('home.consultant'))}</span>
                    <h2>${escapeHtml(profile.display_name || ui('home.title'))}</h2>
                    <p>${escapeHtml(profile.subtitle || ui('home.default_subtitle'))}</p>
                </div>
            </div>
            ${profile.short_description ? `<p class="consultant-note">${escapeHtml(profile.short_description)}</p>` : ''}
            <div class="home-metrics">
                ${tests.length ? `<span><strong>${tests.length}</strong>${escapeHtml(ui('nav.tests'))}</span>` : ''}
                ${products.length ? `<span><strong>${products.length}</strong>${escapeHtml(ui('nav.recommendations'))}</span>` : ''}
                ${materials.length ? `<span><strong>${materials.length}</strong>${escapeHtml(ui('home.materials'))}</span>` : ''}
            </div>
            <div class="hero-actions">
                <button class="primary" data-action="contact">${escapeHtml(ui('home.ask_manager'))}</button>
                ${contactLink ? `<a class="soft-link" href="${escapeHtml(contactLink)}" target="_blank" rel="noopener">${escapeHtml(ui('home.open_contact'))}</a>` : ''}
            </div>
        </section>

        ${profileReady && profileBlockEnabled('video') && profile.video_url ? `
            <section class="home-section">
                <h2>${escapeHtml(profileBlockTitle('video', 'home.watch_video'))}</h2>
                ${videoEmbed ? `
                    <div class="mini-video">
                        <iframe src="${escapeHtml(videoEmbed)}" title="${escapeHtml(ui('home.watch_video'))}" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
                    </div>
                ` : `<a class="soft-link" href="${escapeHtml(profile.video_url)}" target="_blank" rel="noopener">${escapeHtml(ui('home.watch_video'))}</a>`}
            </section>
        ` : ''}

        ${profileReady && profileBlockEnabled('about') && aboutSections.length ? `
            <section class="home-section">
                <h2>${escapeHtml(profileBlockTitle('about', 'consultant.about'))}</h2>
                <div class="about-mini-grid">
                    ${aboutSections.slice(0, 4).map((section) => `
                        <article class="about-mini-card">
                            <strong>${escapeHtml(section.title)}</strong>
                            <p>${escapeHtml(section.text)}</p>
                        </article>
                    `).join('')}
                </div>
            </section>
        ` : ''}

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

        ${profileReady && profileBlockEnabled('tests') ? `
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

        ${profileReady && profileBlockEnabled('products') ? `
            <section class="home-section">
                <h2>${escapeHtml(profileBlockTitle('products', 'home.consultant_recommendations'))}</h2>
                ${products.length ? `<div class="horizontal-list">
                    ${products.slice(0, 4).map((product) => `
                        <article class="recommend-card">
                            ${product.image_path ? `<img src="${escapeHtml(product.image_path)}" alt="" ${lazyImageAttrs()}>` : ''}
                            <strong>${escapeHtml(product.title)}</strong>
                            <span class="muted">${escapeHtml(product.short_description || '')}</span>
                            <div class="item-links">
                                ${product.document_path ? `<a href="${escapeHtml(product.document_path)}" target="_blank" rel="noopener">${escapeHtml(ui('lead.file'))}</a>` : ''}
                                ${product.video_url ? `<a href="${escapeHtml(product.video_url)}" target="_blank" rel="noopener">${escapeHtml(ui('products.video'))}</a>` : ''}
                            </div>
                            <div class="card-actions">
                                <button class="secondary compact" data-open-product-id="${product.id}">${escapeHtml(ui('products.details'))}</button>
                                <button class="secondary compact" data-product-id="${product.id}">${escapeHtml(ui('products.request_info'))}</button>
                            </div>
                        </article>
                    `).join('')}
                </div>` : `<div class="empty-card">${escapeHtml(ui('home.no_products'))}</div>`}
            </section>
        ` : ''}

        ${profileReady && profileBlockEnabled('materials') ? `
            <section class="home-section">
                <h2>${escapeHtml(profileBlockTitle('materials', 'home.materials'))}</h2>
                ${materials.length ? `<div class="card-list">
                    ${materials.slice(0, 3).map((material) => `
                        <article class="material-card">
                            ${material.image_path ? `<img src="${escapeHtml(material.image_path)}" alt="" ${lazyImageAttrs()}>` : ''}
                            <span class="eyebrow">${escapeHtml(material.content_type || ui('lead_response.material'))}</span>
                            <strong>${escapeHtml(material.title)}</strong>
                            <span class="muted">${escapeHtml(material.short_text || '')}</span>
                            <div class="item-links">
                                ${material.attachment_path ? `<a href="${escapeHtml(material.attachment_path)}" target="_blank" rel="noopener">${escapeHtml(ui('lead.file'))}</a>` : ''}
                                ${material.video_url ? `<a href="${escapeHtml(material.video_url)}" target="_blank" rel="noopener">${escapeHtml(ui('products.video'))}</a>` : ''}
                            </div>
                            <button class="secondary compact" data-open-material-id="${material.id}">${escapeHtml(ui('materials.read'))}</button>
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
            <div class="section-title">
                <h2>${escapeHtml(ui('profile.accounts'))}</h2>
            </div>
            <p class="muted">${escapeHtml(ui('profile.accounts_hint'))}</p>
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
                <span class="muted">${escapeHtml(ui('profile.link_warning'))}</span>
                ${miniAppLink ? `<a href="${escapeHtml(miniAppLink)}" target="_blank" rel="noopener">${escapeHtml(ui('profile.open_mini_app', 'Открыть Mini App'))}</a>` : ''}
                ${telegramLink ? `<a href="${escapeHtml(telegramLink)}" target="_blank" rel="noopener">${escapeHtml(ui('profile.open_telegram', 'Подключить Telegram'))}</a>` : ''}
            </div>
        `;
    } catch (error) {
        panel.innerHTML = `<div class="empty">${escapeHtml(error.message)}</div>`;
    }
}

async function renderProducts() {
    const result = await api(`products.php?${userQuery()}`);
    page.innerHTML = result.products.length
        ? result.products.map((product) => `
            <article class="item">
                ${product.image_path ? `<img class="item-image" src="${escapeHtml(product.image_path)}" alt="" ${lazyImageAttrs()}>` : ''}
                <span class="eyebrow">${escapeHtml(product.category_title || ui('home.consultant_recommendations'))}</span>
                <strong>${escapeHtml(product.title)}</strong>
                <span class="muted">${escapeHtml(product.short_description || '')}</span>
                <div class="item-links">
                    ${product.document_path ? `<a href="${escapeHtml(product.document_path)}" target="_blank" rel="noopener">PDF</a>` : ''}
                    ${product.video_url ? `<a href="${escapeHtml(product.video_url)}" target="_blank" rel="noopener">${escapeHtml(ui('products.video'))}</a>` : ''}
                    ${product.purchase_url ? `<a href="${escapeHtml(product.purchase_url)}" target="_blank" rel="noopener">${escapeHtml(ui('products.details'))}</a>` : ''}
                </div>
                <div class="card-actions">
                    <button class="secondary" data-open-product-id="${product.id}">${escapeHtml(ui('products.details'))}</button>
                    <button class="secondary" data-product-id="${product.id}">${escapeHtml(ui('products.request_info'))}</button>
                </div>
            </article>
        `).join('')
        : `<div class="empty">${escapeHtml(ui('products.empty'))}</div>`;
}

async function renderProductDetail(productId) {
    const result = await api(`products.php?id=${encodeURIComponent(productId)}&${userQuery()}`);
    const product = result.product;
    page.innerHTML = `
        <section class="detail-page">
            <button class="secondary compact back-button" data-page-target="products">${escapeHtml(ui('common.back'))}</button>
            ${product.image_path ? `<img class="detail-cover" src="${escapeHtml(product.image_path)}" alt="" ${lazyImageAttrs()}>` : ''}
            <div class="detail-header">
                <span class="eyebrow">${escapeHtml(product.category_title || ui('home.consultant_recommendations'))}</span>
                <h2>${escapeHtml(product.title)}</h2>
                ${product.price ? `<span class="price-pill">${escapeHtml(product.price)}</span>` : ''}
            </div>
            ${product.short_description ? `<div class="detail-lead">${renderTextBlocks(product.short_description)}</div>` : ''}
            ${product.full_description ? `<div class="detail-body">${renderTextBlocks(product.full_description)}</div>` : ''}
            ${renderVideoBlock(product.video_url, product.title)}
            <div class="detail-actions">
                ${product.document_path ? `<a class="soft-link" href="${escapeHtml(product.document_path)}" target="_blank" rel="noopener">${escapeHtml(ui('products.open_file'))}</a>` : ''}
                ${product.purchase_url ? `<a class="soft-link" href="${escapeHtml(product.purchase_url)}" target="_blank" rel="noopener">${escapeHtml(ui('products.open_link'))}</a>` : ''}
                <button class="primary" data-product-id="${product.id}">${escapeHtml(ui('products.request_info'))}</button>
            </div>
        </section>
    `;
}

async function renderMaterialDetail(materialId) {
    const result = await api(`content.php?id=${encodeURIComponent(materialId)}&${userQuery()}`);
    const material = result.content;
    page.innerHTML = `
        <section class="detail-page">
            <button class="secondary compact back-button" data-page-target="home">${escapeHtml(ui('common.back'))}</button>
            ${material.image_path ? `<img class="detail-cover" src="${escapeHtml(material.image_path)}" alt="" ${lazyImageAttrs()}>` : ''}
            <div class="detail-header">
                <span class="eyebrow">${escapeHtml(material.category_title || material.content_type || ui('lead_response.material'))}</span>
                <h2>${escapeHtml(material.title)}</h2>
            </div>
            ${material.short_text ? `<div class="detail-lead">${renderTextBlocks(material.short_text)}</div>` : ''}
            ${material.full_text ? `<div class="detail-body">${renderTextBlocks(material.full_text)}</div>` : ''}
            ${renderVideoBlock(material.video_url, material.title)}
            <div class="detail-actions">
                ${material.attachment_path ? `<a class="soft-link" href="${escapeHtml(material.attachment_path)}" target="_blank" rel="noopener">${escapeHtml(ui('products.open_file'))}</a>` : ''}
                ${material.button_url ? `<a class="soft-link" href="${escapeHtml(material.button_url)}" target="_blank" rel="noopener">${escapeHtml(material.button_text || ui('materials.open_link'))}</a>` : ''}
                <button class="secondary" data-action="contact">${escapeHtml(ui('home.write_manager'))}</button>
            </div>
        </section>
    `;
}

async function renderRecommendations() {
    const result = await api(`recommendations.php?${userQuery()}`);
    page.innerHTML = result.recommendations.length
        ? result.recommendations.map((item) => `
            <article class="recommendation-card">
                <span class="eyebrow">${escapeHtml(item.category_title || ui('recommendations.reason'))}</span>
                ${item.image_path ? `<img class="item-image" src="${escapeHtml(item.image_path)}" alt="" ${lazyImageAttrs()}>` : ''}
                <strong>${escapeHtml(item.product_title || ui('recommendations.default_title'))}</strong>
                ${item.reason_text ? `
                    <div class="recommendation-section">
                        <span>${escapeHtml(ui('recommendations.reason'))}</span>
                        ${renderTextBlocks(item.reason_text)}
                    </div>
                ` : ''}
                ${item.short_description ? `
                    <div class="recommendation-section">
                        <span>${escapeHtml(ui('recommendations.details'))}</span>
                        ${renderTextBlocks(item.short_description)}
                    </div>
                ` : ''}
                ${item.full_description ? `
                    <details class="recommendation-section">
                        <summary>${escapeHtml(ui('recommendations.product_text'))}</summary>
                        ${renderTextBlocks(item.full_description)}
                    </details>
                ` : ''}
                ${renderVideoBlock(item.video_url, item.product_title || ui('recommendations.default_title'))}
                <div class="item-links">
                    ${item.document_path ? `<a href="${escapeHtml(item.document_path)}" target="_blank" rel="noopener">${escapeHtml(ui('products.open_file'))}</a>` : ''}
                    ${item.purchase_url ? `<a href="${escapeHtml(item.purchase_url)}" target="_blank" rel="noopener">${escapeHtml(ui('products.open_link'))}</a>` : ''}
                </div>
                <div class="recommendation-actions">
                    ${item.product_id ? `<button class="secondary compact" data-open-product-id="${item.product_id}">${escapeHtml(ui('products.details'))}</button>` : ''}
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
            ${content.image_path ? `<img src="${escapeHtml(content.image_path)}" alt="" ${lazyImageAttrs()}>` : ''}
            <strong>${escapeHtml(content.title || ui('lead_response.material'))}</strong>
            ${text ? `<p>${escapeHtml(text)}</p>` : ''}
            <div class="response-resource-actions">
                ${content.id ? `<button class="secondary compact" data-open-material-id="${content.id}">${escapeHtml(ui('lead_response.open_material'))}</button>` : ''}
                ${content.attachment_path ? `<a href="${escapeHtml(content.attachment_path)}" target="_blank" rel="noopener">${escapeHtml(ui('lead_response.open_file'))}</a>` : ''}
                ${content.video_url ? `<a href="${escapeHtml(content.video_url)}" target="_blank" rel="noopener">${escapeHtml(ui('lead_response.open_video'))}</a>` : ''}
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

function openContactModal(productId = null, presetMessage = '', titleOverride = '') {
    document.querySelector('.modal-backdrop')?.remove();
    const productTitle = productId
        ? document.querySelector(`[data-product-id="${productId}"]`)?.closest('article')?.querySelector('strong')?.textContent || ''
        : '';
    const modalTitle = titleOverride || (productId ? ui('lead.modal_product_title') : ui('lead.modal_title'));
    document.body.insertAdjacentHTML('beforeend', `
        <div class="modal-backdrop">
            <section class="modal-card" role="dialog" aria-modal="true" aria-labelledby="contact-modal-title">
                <button class="modal-close" type="button" data-action="close-modal" aria-label="${escapeHtml(ui('common.close'))}">×</button>
                <h2 id="contact-modal-title">${escapeHtml(modalTitle)}</h2>
                ${productTitle ? `<p class="muted">${escapeHtml(productTitle)}</p>` : ''}
                <p class="muted">${escapeHtml(ui('lead.modal_hint'))}</p>
                <form id="contact-form">
                    <input type="hidden" name="product_id" value="${productId ? Number(productId) : ''}">
                    <textarea name="message" rows="5" required placeholder="${escapeHtml(ui('lead.message_placeholder'))}">${escapeHtml(presetMessage)}</textarea>
                    <div class="form-error" id="contact-error"></div>
                    <div class="modal-actions">
                        <button class="secondary" type="button" data-action="close-modal">${escapeHtml(ui('lead.cancel'))}</button>
                        <button class="primary" type="submit">${escapeHtml(ui('lead.send'))}</button>
                    </div>
                </form>
            </section>
        </div>
    `);
    document.querySelector('#contact-form textarea')?.focus();
}

function closeModal() {
    document.querySelector('.modal-backdrop')?.remove();
}

async function createLeadFromMessage(productId = null, message = '') {
    const text = String(message || '').trim();
    if (!text) {
        throw new Error(ui('lead.message_required'));
    }

    const result = await api('contact_manager.php', {
        method: 'POST',
        body: JSON.stringify({
            ...userPayload(),
            product_id: productId,
            message: text,
        }),
    });

    page.insertAdjacentHTML('afterbegin', `
        <div class="panel">
            ${escapeHtml(formatUi('lead.created', {id: result.lead_id}))}
            <button class="secondary compact" data-page-target="leads">${escapeHtml(ui('lead.my_leads'))}</button>
        </div>
    `);
}

async function renderTests() {
    const testsResponse = await api(`tests.php?${userQuery()}`);
    page.innerHTML = testsResponse.tests.length
        ? testsResponse.tests.map((test) => {
            const status = test.status || 'new';
            const actionText = status === 'completed'
                ? ui('tests.show_result', 'Посмотреть результат')
                : (status === 'draft' ? ui('tests.resume', 'Продолжить') : ui('tests.start', 'Начать тест'));
            const statusText = status === 'completed'
                ? ui('tests.completed_badge', 'Тест уже пройден')
                : (status === 'draft' ? ui('tests.draft_badge', 'Тест начат') : '');
            return `
                <article class="diagnostic-card">
                    ${test.intro_image_path ? `<img class="diagnostic-cover" src="${escapeHtml(test.intro_image_path)}" alt="" ${lazyImageAttrs()}>` : ''}
                    <span class="diagnostic-icon">${escapeHtml(test.emoji || '🌿')}</span>
                    <span class="eyebrow">${escapeHtml(test.category_title || ui('tests.diagnostic'))}</span>
                    <strong>${escapeHtml(test.title)}</strong>
                    <span class="muted">${escapeHtml(test.description || '')}</span>
                    <span class="test-meta">${escapeHtml(test.scoring_type === 'multiscale' ? ui('tests.matrix_type', 'Матрица здоровья') : ui('tests.simple_type', 'Тест'))}</span>
                    <span class="test-meta">${escapeHtml(formatUi('tests.questions_count', {count: test.questions_count || 0}))}</span>
                    ${statusText ? `<span class="status-pill status-${escapeHtml(status)}">${escapeHtml(statusText)}</span>` : ''}
                    ${status === 'draft' && test.progress ? renderProgress(test.progress) : ''}
                    <button class="${status === 'completed' ? 'primary' : 'secondary'}" data-open-test-id="${test.id}">${escapeHtml(actionText)}</button>
                </article>
            `;
        }).join('')
        : `<div class="empty-card">${escapeHtml(ui('tests.empty'))}</div>`;
}

async function renderTest(testId) {
    const result = await api(`tests.php?id=${encodeURIComponent(testId)}&${userQuery()}`);
    state.activeTest = result;
    if (result.session && result.question) {
        renderResumeTest(result);
        return;
    }
    if (result.completed_result) {
        renderCompletedTest(result);
        return;
    }
    renderTestIntro(result);
}

async function renderTestResultsList() {
    const result = await api(`tests.php?${userQuery()}`);
    const completed = result.tests.filter((test) => test.status === 'completed');
    page.innerHTML = completed.length
        ? `
            <section class="home-section">
                <div class="section-title">
                    <h2>${escapeHtml(ui('results.title', 'Результаты тестов'))}</h2>
                    <button class="text-button" data-page-target="tests">${escapeHtml(ui('nav.tests', 'Тесты'))}</button>
                </div>
                ${completed.map((test) => `
                    <article class="diagnostic-card">
                        <span class="diagnostic-icon">${escapeHtml(test.emoji || '🌿')}</span>
                        <span class="eyebrow">${escapeHtml(ui('tests.completed_badge', 'Тест уже пройден'))}</span>
                        <strong>${escapeHtml(test.title)}</strong>
                        <span class="muted">${escapeHtml(test.description || '')}</span>
                        <button class="primary" data-open-test-id="${test.id}">${escapeHtml(ui('tests.show_result', 'Посмотреть результат'))}</button>
                    </article>
                `).join('')}
            </section>
        `
        : `
            <section class="panel">
                <h2>${escapeHtml(ui('results.title', 'Результаты тестов'))}</h2>
                <p class="muted">${escapeHtml(ui('results.empty', 'Вы пока не завершили ни один тест.'))}</p>
                <button class="primary" data-page-target="tests">${escapeHtml(ui('home.start_test', 'Пройти тест'))}</button>
            </section>
        `;
}

function renderCompletedTest(result) {
    const test = result.test;
    page.innerHTML = `
        <section class="panel test-panel">
            <button class="secondary compact" data-action="back-to-tests">${escapeHtml(ui('tests.back'))}</button>
            <div class="resume-card completed-card">
                <span class="test-emoji">${escapeHtml(test.emoji || '🌿')}</span>
                <h2>${escapeHtml(test.title)}</h2>
                <p class="muted">${escapeHtml(ui('tests.completed_hint', 'Вы уже проходили этот тест. Можно посмотреть результат или пройти заново.'))}</p>
                <button class="primary" data-action="show-test-result">${escapeHtml(ui('tests.show_result', 'Посмотреть результат'))}</button>
                <button class="secondary" data-action="restart-test">${escapeHtml(ui('tests.retake', 'Пройти заново'))}</button>
            </div>
        </section>
    `;
}

async function showCompletedTestResult() {
    if (state.activeTest?.completed_result) {
        renderTestResult(state.activeTest.completed_result);
        return;
    }

    const testId = state.activeTest?.test?.id;
    if (!testId) {
        return;
    }

    const result = await api(`tests.php?action=result&test_id=${encodeURIComponent(testId)}&${userQuery()}`);
    renderTestResult(result);
}

function renderTestIntro(result) {
    const test = result.test;
    page.innerHTML = `
        <section class="panel test-panel">
            <button class="secondary compact" data-action="back-to-tests">${escapeHtml(ui('tests.back'))}</button>
            <div class="test-intro">
                ${test.intro_image_path ? `<img class="test-intro-media" src="${escapeHtml(test.intro_image_path)}" alt="" ${lazyImageAttrs()}>` : ''}
                ${renderVideoBlock(test.intro_video_url, test.title)}
                <span class="test-emoji">${escapeHtml(test.emoji || '🌿')}</span>
                <span class="eyebrow">${escapeHtml(test.scoring_type === 'multiscale' ? ui('tests.matrix_type', 'Матрица здоровья') : ui('tests.simple_type', 'Тест'))}</span>
                <h2>${escapeHtml(test.title)}</h2>
                ${test.intro_text ? `<div class="detail-lead">${renderTextBlocks(test.intro_text)}</div>` : ''}
                <div class="test-progress">${escapeHtml(formatUi('tests.questions_count', {count: test.questions_count || result.questions.length || 0}))}</div>
                <button class="primary" data-action="start-test">${escapeHtml(ui('tests.start', 'Начать тест'))}</button>
            </div>
        </section>
    `;
}

function renderResumeTest(result) {
    const progress = result.progress || {answered: 0, total: result.test.questions_count || 0, percent: 0};
    page.innerHTML = `
        <section class="panel test-panel">
            <button class="secondary compact" data-action="back-to-tests">${escapeHtml(ui('tests.back'))}</button>
            <div class="resume-card">
                <span class="test-emoji">${escapeHtml(result.test.emoji || '🌿')}</span>
                <h2>${escapeHtml(result.test.title)}</h2>
                <p class="muted">${escapeHtml(ui('tests.resume_hint', 'У вас есть незавершенный тест. Можно продолжить с того вопроса, где остановились, или начать заново.'))}</p>
                ${renderProgress(progress)}
                <button class="primary" data-action="resume-test">${escapeHtml(ui('tests.resume', 'Продолжить'))}</button>
                <button class="secondary" data-action="restart-test">${escapeHtml(ui('tests.restart', 'Начать заново'))}</button>
            </div>
        </section>
    `;
}

function renderProgress(progress = {}) {
    const answered = Number(progress.answered || 0);
    const total = Math.max(0, Number(progress.total || 0));
    const percent = total ? Math.round((answered / total) * 100) : 0;
    return `
        <div class="test-progress-box" aria-label="${escapeHtml(ui('tests.progress', 'Прогресс'))}">
            <div class="test-progress-line">
                <span style="width: ${Math.max(0, Math.min(100, percent))}%"></span>
            </div>
            <small>${escapeHtml(formatUi('tests.progress_count', {answered, total}, `${answered} из ${total}`))}</small>
        </div>
    `;
}

async function startTestSession(reset = false) {
    const result = await api('tests.php?action=start', {
        method: 'POST',
        body: JSON.stringify({
            ...userPayload(),
            test_id: state.activeTest.test.id,
            reset,
        }),
    });
    state.activeTest = result;
    renderTestQuestion(result);
}

function renderTestQuestion(result) {
    if (!result.question) {
        renderTestResult(result);
        return;
    }

    state.activeTest = result;
    const question = result.question;
    const progress = result.progress || {answered: 0, total: result.test.questions_count || 0};
    const currentNumber = Math.min(Number(progress.answered || 0) + 1, Number(progress.total || 1));
    const answers = question.answers || [];
    const isMultiple = question.question_type === 'multiple_choice';
    const controls = isMultiple
        ? `
            <form id="test-question-form" class="answer-button-list">
                ${answers.map((answer) => `
                    <label class="answer">
                        <input type="checkbox" name="answer_ids" value="${answer.id}">
                        <span>${escapeHtml(answer.answer_text)}</span>
                    </label>
                `).join('')}
                <button class="primary" type="submit">${escapeHtml(ui('tests.next', 'Дальше'))}</button>
            </form>
        `
        : answers.length
            ? `
                <div class="answer-button-list">
                    ${answers.map((answer) => `
                        <button class="answer-button" type="button" data-action="answer-test" data-answer-id="${answer.id}">
                            ${escapeHtml(answer.answer_text)}
                        </button>
                    `).join('')}
                </div>
            `
            : `
                <form id="test-question-form" class="answer-button-list">
                    <textarea class="text-answer" name="text_answer" rows="4" required placeholder="${escapeHtml(ui('tests.text_placeholder'))}"></textarea>
                    <button class="primary" type="submit">${escapeHtml(ui('tests.next', 'Дальше'))}</button>
                </form>
            `;

    page.innerHTML = `
        <section class="panel test-panel">
            <button class="secondary compact" data-action="back-to-tests">${escapeHtml(ui('tests.back'))}</button>
            <div class="question-step">
                <span class="test-emoji">${escapeHtml(result.test.emoji || '🌿')}</span>
                <span class="eyebrow">${escapeHtml(formatUi('tests.question_short', {number: currentNumber, total: progress.total}, `Вопрос ${currentNumber} из ${progress.total}`))}</span>
                ${renderProgress(progress)}
                <h2>${escapeHtml(question.question_text)}</h2>
                ${controls}
            </div>
        </section>
    `;
}

async function answerCurrentQuestion(answerId = null, answerIds = [], textAnswer = '') {
    const question = state.activeTest.question;
    const result = await api('tests.php?action=answer', {
        method: 'POST',
        body: JSON.stringify({
            ...userPayload(),
            session_id: state.activeTest.session.id,
            question_id: question.id,
            answer_id: answerId,
            answer_ids: answerIds,
            text_answer: textAnswer,
        }),
    });

    if (result.done && result.session_id) {
        renderTestResult(result);
        return;
    }
    renderTestQuestion(result);
}

async function submitCurrentTextAnswer(form) {
    const button = form.querySelector('button[type="submit"]');
    if (button) {
        button.disabled = true;
        button.dataset.originalText = button.textContent || '';
        button.textContent = ui('tests.submitting', 'Отправляем...');
    }

    try {
        const formData = new FormData(form);
        const answerIds = formData.getAll('answer_ids').map((value) => Number(value)).filter(Boolean);
        const textAnswer = String(formData.get('text_answer') || '').trim();
        if (!answerIds.length && !textAnswer) {
            throw new Error(ui('tests.answer_required'));
        }
        await answerCurrentQuestion(null, answerIds, textAnswer);
    } catch (error) {
        form.insertAdjacentHTML('afterbegin', `<div class="form-error">${escapeHtml(friendlyError(error))}</div>`);
        if (button) {
            button.disabled = false;
            button.textContent = button.dataset.originalText || ui('tests.next', 'Дальше');
        }
    }
}

function scaleSeverityLabel(severity) {
    return {
        excellent: 'Очень хорошо',
        good: 'Хорошо',
        risk: 'Зона риска',
        critical: 'Требует внимания',
    }[severity] || 'Результат';
}

function renderScaleResults(scaleResults = []) {
    if (!scaleResults.length) {
        return '';
    }

    return `
        <div class="scale-results">
            ${scaleResults.map((item) => {
                const result = item.result || {};
                const severity = result.severity || 'good';
                return `
                    <article class="scale-result scale-result-${escapeHtml(severity)}">
                        <div>
                            <strong>${escapeHtml(item.title)}</strong>
                            <span>${escapeHtml(scaleSeverityLabel(severity))}</span>
                        </div>
                        <span class="scale-score">${escapeHtml(item.score)}</span>
                        ${result.summary_text ? `<p>${escapeHtml(result.summary_text)}</p>` : ''}
                        ${result.advice_text ? `<p class="muted">${escapeHtml(result.advice_text)}</p>` : ''}
                    </article>
                `;
            }).join('')}
        </div>
    `;
}

function renderResultMaterials(materials = []) {
    if (!materials.length) {
        return '';
    }

    return `
        <div class="result-materials">
            <strong>${escapeHtml(ui('result.materials_title', 'Что посмотреть дальше'))}</strong>
            ${materials.map((item) => `
                <article class="result-material">
                    ${item.image_path ? `<img class="item-image" src="${escapeHtml(item.image_path)}" alt="" ${lazyImageAttrs()}>` : ''}
                    ${renderVideoBlock(item.video_url, item.title)}
                    <span>${escapeHtml(item.content_type || '')}</span>
                    <b>${escapeHtml(item.title)}</b>
                    ${item.short_text ? `<p>${escapeHtml(item.short_text)}</p>` : ''}
                    <div class="material-actions">
                        <button class="secondary compact" data-open-material-id="${item.id}">${escapeHtml(ui('materials.read'))}</button>
                        ${item.button_url ? `<a class="soft-link" href="${escapeHtml(item.button_url)}" target="_blank" rel="noopener">${escapeHtml(item.button_text || ui('materials.open_link'))}</a>` : ''}
                        ${item.button_text && !item.button_url ? `<button class="secondary compact" data-action="contact-result">${escapeHtml(item.button_text)}</button>` : ''}
                        ${item.attachment_path ? `<a class="soft-link" href="${escapeHtml(item.attachment_path)}" target="_blank" rel="noopener">${escapeHtml(ui('products.open_file'))}</a>` : ''}
                    </div>
                </article>
            `).join('')}
        </div>
    `;
}

function renderTestResult(result) {
    page.innerHTML = `
        <section class="panel">
            <div class="result-card">
                <strong>${escapeHtml(result.result?.title || ui('result.default_title'))}</strong>
                <span class="result-score">${escapeHtml(ui('result.score'))}: ${escapeHtml(result.total_score)}</span>
                <div class="result-summary">${renderTextBlocks(result.summary)}</div>
            </div>
            ${renderScaleResults(result.scale_results || [])}
            ${renderResultMaterials(result.materials || [])}
            <div class="result-actions">
                <button class="primary" data-action="contact-result">${escapeHtml(ui('result.contact_manager', 'Разобрать с консультантом'))}</button>
                <button class="secondary" data-page-target="recommendations">${escapeHtml(ui('result.show_recommendations'))}</button>
                <button class="secondary" data-page-target="products">${escapeHtml(ui('products.title', 'Продукты'))}</button>
            </div>
        </section>
    `;
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
    document.body.classList.remove('auth-required', 'referral-required');
    tabs.forEach((tab) => {
        tab.disabled = false;
        tab.classList.toggle('active', tab.dataset.page === state.page);
    });
    page.innerHTML = `<div class="empty">${escapeHtml(ui('common.loading'))}</div>`;
    try {
        if (state.page === 'home') {
            if (!state.consultantProfile) {
                renderHome();
                loadConsultantProfile()
                    .then(() => {
                        if (state.page === 'home') {
                            renderHome();
                        }
                    })
                    .catch(() => {});
                return;
            }
            renderHome();
        }
        if (state.page === 'profile') {
            if (!state.consultantProfile) {
                await loadConsultantProfile();
            }
            await renderProfile();
        }
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
        if (state.page === 'results') await renderTestResultsList();
        if (state.page === 'leads') await renderLeads();
        if (!pageNeedsConsultantProfile()) {
            prefetchConsultantProfile();
        }
    } catch (error) {
        page.innerHTML = `<div class="empty-card">${escapeHtml(friendlyError(error))}</div>`;
    }
}

tabs.forEach((tab) => {
    tab.addEventListener('click', () => setPage(tab.dataset.page));
});

document.addEventListener('click', (event) => {
    const clicked = event.target;
    if (!(clicked instanceof HTMLElement)) return;
    const link = clicked.closest('a[href]');
    if (!(link instanceof HTMLAnchorElement)) return;
    if (!link.target && !link.classList.contains('soft-link') && !link.classList.contains('response-file-link')) return;

    event.preventDefault();
    openPlatformUrl(link.getAttribute('href') || '');
});

page.addEventListener('click', async (event) => {
    const clicked = event.target;
    if (!(clicked instanceof HTMLElement)) return;
    const target = clicked.closest('[data-action], [data-page-target], [data-open-test-id], [data-open-product-id], [data-open-material-id], [data-product-id]');
    if (!(target instanceof HTMLElement)) return;
    if (target.dataset.action === 'tests') setPage('tests');
    if (target.dataset.action === 'back-to-tests') await renderTests();
    if (target.dataset.action === 'contact') openContactModal();
    if (target.dataset.action === 'contact-result') {
        openContactModal(
            null,
            ui('result.contact_message', 'Здравствуйте! Хочу разобрать результаты диагностики и понять, с чего начать.'),
            ui('result.contact_manager', 'Разобрать с консультантом')
        );
    }
    if (target.dataset.action === 'start-test') await startTestSession(false);
    if (target.dataset.action === 'resume-test') renderTestQuestion(state.activeTest);
    if (target.dataset.action === 'restart-test') await startTestSession(true);
    if (target.dataset.action === 'show-test-result') await showCompletedTestResult();
    if (target.dataset.action === 'answer-test') await answerCurrentQuestion(Number(target.dataset.answerId || 0));
    if (target.dataset.action === 'close-modal') closeModal();
    if (target.dataset.action === 'create-link-token') await renderAccountLinkPanel();
    if (target.dataset.pageTarget) setPage(target.dataset.pageTarget);
    if (target.dataset.openTestId) await renderTest(Number(target.dataset.openTestId));
    if (target.dataset.openProductId) await renderProductDetail(Number(target.dataset.openProductId));
    if (target.dataset.openMaterialId) await renderMaterialDetail(Number(target.dataset.openMaterialId));
    if (target.dataset.productId) openContactModal(Number(target.dataset.productId));
});

document.addEventListener('click', (event) => {
    const clicked = event.target;
    if (!(clicked instanceof HTMLElement)) {
        return;
    }
    if (clicked.dataset.action === 'close-modal' || clicked.classList.contains('modal-backdrop')) {
        closeModal();
    }
});

page.addEventListener('submit', async (event) => {
    const target = event.target;
    if (target instanceof HTMLFormElement && target.id === 'referral-form') {
        event.preventDefault();
        const error = target.querySelector('#referral-error');
        const button = target.querySelector('button[type="submit"]');
        const formData = new FormData(target);
        const referralCode = normalizeReferralCodeInput(formData.get('referral_code'));
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
    if (!(target instanceof HTMLFormElement) || target.id !== 'test-question-form') return;
    event.preventDefault();
    await submitCurrentTextAnswer(target);
});

document.addEventListener('submit', async (event) => {
    const target = event.target;
    if (!(target instanceof HTMLFormElement) || target.id !== 'contact-form') {
        return;
    }

    event.preventDefault();
    const error = target.querySelector('#contact-error');
    const button = target.querySelector('button[type="submit"]');
    const formData = new FormData(target);
    const productId = Number(formData.get('product_id') || 0) || null;
    const message = String(formData.get('message') || '').trim();

    if (!message) {
        if (error) error.textContent = ui('lead.message_required');
        return;
    }

    if (button) {
        button.disabled = true;
        button.dataset.originalText = button.textContent || '';
        button.textContent = ui('lead.sending');
    }

    try {
        await createLeadFromMessage(productId, message);
        closeModal();
    } catch (exception) {
        if (error) error.textContent = exception instanceof Error ? exception.message : ui('common.load_failed');
    } finally {
        if (button) {
            button.disabled = false;
            button.textContent = button.dataset.originalText || ui('lead.send');
        }
    }
});

applyInitialRoute();

Promise.all([loadI18n(), authorize()])
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
