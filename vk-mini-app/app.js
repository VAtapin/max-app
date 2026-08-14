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
    initialMaterialId: null,
    bridgeLaunchParams: new URLSearchParams(),
    bridgeLocationSubscribed: false,
    i18n: {},
    consultantProfile: null,
    consultantProfilePromise: null,
    messagingConfig: null,
    messagingConfigPromise: null,
    onboarding: null,
    notifications: [],
    today: null,
    accountSuggestions: null,
    webMergeLink: null,
    answerPending: false,
};

const page = document.querySelector('#page');
const tabs = document.querySelectorAll('.tabs button');
const homeLink = document.querySelector('#home-link');
const staffPreviewBanner = document.querySelector('#staff-preview-banner');
const clientAiChat = document.querySelector('#client-ai-chat');
const clientAiToggle = document.querySelector('#client-ai-toggle');
const clientAiPanel = document.querySelector('#client-ai-panel');
const clientAiClose = document.querySelector('#client-ai-close');
const clientAiForm = document.querySelector('#client-ai-form');
const clientAiMessages = document.querySelector('#client-ai-messages');

function decodeRouteValue(value) {
    let decoded = String(value || '').trim();
    for (let index = 0; index < 2; index += 1) {
        try {
            const next = decodeURIComponent(decoded);
            if (next === decoded) break;
            decoded = next;
        } catch (_) {
            break;
        }
    }
    return decoded.replace(/^#/, '');
}

function routeParamsFromValue(value) {
    let raw = decodeRouteValue(value);
    if (!raw) {
        return new URLSearchParams();
    }

    try {
        const parsedUrl = new URL(raw);
        raw = parsedUrl.search || parsedUrl.hash.replace(/^#/, '');
    } catch (_) {
        // Not a full URL, keep parsing as a route fragment.
    }

    if (raw.startsWith('/')) {
        const questionIndex = raw.indexOf('?');
        raw = questionIndex >= 0 ? raw.slice(questionIndex + 1) : raw.slice(1);
    }
    if (raw.includes('?')) {
        raw = raw.slice(raw.indexOf('?') + 1);
    }

    return raw.includes('=') || raw.includes('&') ? new URLSearchParams(raw) : new URLSearchParams();
}

function mergeRouteParams(params, value) {
    routeParamsFromValue(value).forEach((routeValue, routeKey) => {
        if (!params.has(routeKey)) {
            params.set(routeKey, routeValue);
        }
    });
}

function hashLaunchParams() {
    let hash = window.location.hash.replace(/^#/, '');
    if (hash.startsWith('/')) {
        const questionIndex = hash.indexOf('?');
        hash = questionIndex >= 0 ? hash.slice(questionIndex + 1) : '';
    }
    return new URLSearchParams(hash);
}

function launchParams() {
    const params = new URLSearchParams(window.location.search);
    hashLaunchParams().forEach((value, key) => {
        if (!params.has(key)) {
            params.set(key, value);
        }
    });
    state.bridgeLaunchParams.forEach((value, key) => {
        if (!params.has(key)) {
            params.set(key, value);
        }
    });
    ['startapp', 'start_param', 'vk_ref', 'vk_start_param', 'location', 'route', 'payload', 'hash', 'vk_hash', 'fragment'].forEach((key) => {
        if (params.has(key)) {
            mergeRouteParams(params, params.get(key));
        }
    });
    return params;
}

function getReferralCode() {
    const params = launchParams();
    const startApp = normalizeReferralCodeInput(params.get('startapp') || '');
    const vkRef = normalizeReferralCodeInput(params.get('vk_ref') || '');
    const routeCode = params.get('ref') || (startApp && !startApp.includes('=') ? startApp : null) || (vkRef.startsWith('SWPRO_') ? vkRef : null);
    return normalizeReferralCodeInput(state.user?.referral_code_used || routeCode);
}

function normalizeReferralCodeInput(value) {
    const code = String(value || '').trim();
    return code.startsWith('ref_') ? code.slice(4).trim() : code;
}

function rememberCurrentReferralCode(fallbackCode = '') {
    const currentCode = normalizeReferralCodeInput(state.user?.referral_code_used || fallbackCode);
    if (!currentCode || !hasTeamAccess()) {
        return;
    }
    localStorage.setItem('swpro_last_referral_code', currentCode);
    localStorage.removeItem('swpro_pending_referral_code');
    const currentUrl = new URL(window.location.href);
    if (currentUrl.searchParams.has('ref') && normalizeReferralCodeInput(currentUrl.searchParams.get('ref')) !== currentCode) {
        currentUrl.searchParams.set('ref', currentCode);
        window.history.replaceState({}, '', `${currentUrl.pathname}${currentUrl.search}${currentUrl.hash}`);
    }
}

function applyInitialRoute() {
    const params = launchParams();
    const pageName = params.get('page');
    const testId = Number(params.get('test_id') || 0);
    const materialId = Number(params.get('material_id') || 0);
    if (pageName || testId > 0 || materialId > 0) {
        state.initialTestId = null;
        state.initialMaterialId = null;
    }
    if (['home', 'tests', 'cashback', 'contact', 'cooperation'].includes(pageName || '')) {
        state.page = pageName;
    }
    if (testId > 0) {
        state.page = 'tests';
        state.initialTestId = testId;
    }
    if (materialId > 0) {
        state.page = 'material';
        state.initialMaterialId = materialId;
    }
}

function applyBridgeLocation(value) {
    const location = decodeRouteValue(value);
    if (!location) {
        return;
    }
    state.bridgeLaunchParams.set('location', location);
    applyInitialRoute();
    if (state.user && state.onboarding?.complete) {
        render();
    }
}

async function loadI18n() {
    try {
        const response = await fetch('i18n/ru.json?v=20260804-2', {cache: 'force-cache'});
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
    return launchParams().get('link_token') || null;
}

function getTelegramApp() {
    return window.Telegram && window.Telegram.WebApp ? window.Telegram.WebApp : null;
}

function hasTelegramLaunchParams() {
    const params = launchParams();
    return params.has('tgWebAppData') || params.has('tgWebAppVersion');
}

function vkLaunchParams() {
    return launchParams();
}

function hasOkLaunchParams() {
    const params = vkLaunchParams();
    const vkClient = String(params.get('vk_client') || '').toLowerCase();
    const vkPlatform = String(params.get('vk_platform') || '').toLowerCase();
    return vkClient === 'ok'
        || vkPlatform.includes('ok')
        || params.has('vk_ok_user_id')
        || params.has('ok_app_id')
        || params.has('logged_user_id')
        || params.has('session_key')
        || params.has('application_key')
        || params.has('apiconnection');
}

function hasVkLaunchParams() {
    const params = vkLaunchParams();
    return params.has('vk_app_id') || params.has('vk_user_id') || params.has('sign') || params.has('vk_ok_user_id');
}

function hasVkOkLaunchParams() {
    return hasVkLaunchParams() || hasOkLaunchParams();
}

function isVkOkContext() {
    return hasVkOkLaunchParams() || ['VK', 'OK'].includes(state.platform);
}

function shouldUseTelegramRuntime() {
    return hasTelegramLaunchParams() && !hasVkOkLaunchParams();
}

function loadTelegramSdk() {
    if (!shouldUseTelegramRuntime()) {
        return Promise.resolve(null);
    }
    if (getTelegramApp()) {
        return Promise.resolve(getTelegramApp());
    }

    return new Promise((resolve) => {
        const existing = document.querySelector('script[data-telegram-sdk]');
        if (existing) {
            existing.addEventListener('load', () => resolve(getTelegramApp()), {once: true});
            existing.addEventListener('error', () => resolve(null), {once: true});
            return;
        }

        const script = document.createElement('script');
        script.src = 'https://telegram.org/js/telegram-web-app.js';
        script.async = true;
        script.dataset.telegramSdk = '1';
        script.onload = () => resolve(getTelegramApp());
        script.onerror = () => resolve(null);
        document.head.appendChild(script);
    });
}

async function waitForTelegramApp(timeoutMs = 900) {
    if (!shouldUseTelegramRuntime()) {
        return null;
    }
    if (getTelegramApp()) {
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
    if (!shouldUseTelegramRuntime()) {
        return null;
    }
    const tg = getTelegramApp() || await loadTelegramSdk() || await waitForTelegramApp();
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
    rememberCurrentReferralCode(getReferralCode());
    return result.user;
}

function telegramInitData() {
    if (!shouldUseTelegramRuntime()) {
        return '';
    }
    const tg = getTelegramApp();
    return tg && tg.initData ? tg.initData : '';
}

function loadVkBridge() {
    if (window.vkBridge) {
        return Promise.resolve(window.vkBridge);
    }
    if (!hasVkOkLaunchParams()) {
        return Promise.resolve(null);
    }
    return new Promise((resolve) => {
        const script = document.createElement('script');
        script.src = 'vendor/vk-bridge.min.js?v=3.0.2';
        script.async = true;
        script.onload = () => resolve(window.vkBridge || null);
        script.onerror = () => resolve(null);
        document.head.appendChild(script);
    });
}

function withTimeout(promise, timeoutMs) {
    return Promise.race([
        promise,
        new Promise((resolve) => {
            window.setTimeout(() => resolve(null), timeoutMs);
        }),
    ]);
}

async function initVk() {
    if (!hasVkOkLaunchParams()) {
        return null;
    }

    await withTimeout(loadVkBridge(), 1200);
    if (window.vkBridge) {
        if (!state.bridgeLocationSubscribed) {
            state.bridgeLocationSubscribed = true;
            vkBridge.subscribe((event) => {
                const type = event?.detail?.type || '';
                if (type === 'VKWebAppLocationChanged' || type === 'VKWebAppChangeFragment') {
                    applyBridgeLocation(event?.detail?.data?.location || '');
                }
            });
        }
        try {
            await withTimeout(vkBridge.send('VKWebAppInit'), 1000);
        } catch (_) {
            // VK moderation can open the app with launch params before bridge init is ready.
        }
        try {
            const launch = await withTimeout(vkBridge.send('VKWebAppGetLaunchParams'), 1000);
            if (launch && typeof launch === 'object') {
                Object.entries(launch).forEach(([key, value]) => {
                    if (value !== null && value !== undefined && value !== '') {
                        state.bridgeLaunchParams.set(key, String(value));
                    }
                });
                applyInitialRoute();
            }
        } catch (_) {
            // URL search/hash params are enough when launch params are not available.
        }
        try {
            const user = await withTimeout(vkBridge.send('VKWebAppGetUserInfo'), 1500);
            if (user && user.id) {
                return user;
            }
        } catch (_) {
            // VK moderation can open the app with launch params before bridge user info is available.
        }
    }

    const params = vkLaunchParams();
    const fallbackId = params.get('vk_user_id') || params.get('vk_ok_user_id') || params.get('logged_user_id');
    return fallbackId ? {
        id: fallbackId,
        first_name: '',
        last_name: '',
        domain: '',
    } : null;
}

async function initWebUser(referralCodeOverride = null) {
    const storedReferralCode = localStorage.getItem('swpro_pending_referral_code') || '';
    const referralCode = normalizeReferralCodeInput(referralCodeOverride || getReferralCode() || storedReferralCode);
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
            first_name: '',
            last_name: '',
            referral_code: referralCode,
            link_token: linkToken,
        }),
    });

    state.platform = 'web';
    state.platformUserId = webUserId;
    state.auth = {platform: 'web', platform_user_id: webUserId};
    state.user = result.user;
    rememberCurrentReferralCode(referralCode);
    return result.user;
}

async function consumeTelegramOidc() {
    if (isVkOkContext()) {
        return null;
    }
    const search = new URLSearchParams(window.location.search);
    if (search.get('oidc') !== '1') {
        return null;
    }
    const result = await api('telegram_oidc_session.php');
    state.auth = result.auth;
    state.platform = result.auth.platform;
    state.platformUserId = result.auth.platform_user_id;
    state.user = result.user;
    search.delete('oidc');
    const nextUrl = `${window.location.pathname}${search.toString() ? `?${search}` : ''}${window.location.hash}`;
    window.history.replaceState({}, '', nextUrl);
    rememberCurrentReferralCode(getReferralCode());
    return result.user;
}

function telegramOidcStartUrl() {
    const params = new URLSearchParams();
    const referralCode = getReferralCode();
    const linkToken = getLinkToken();
    if (referralCode) params.set('ref', referralCode);
    if (linkToken) params.set('link_token', linkToken);
    if (state.page && state.page !== 'home') params.set('return_page', state.page);
    if (state.initialTestId) params.set('test_id', String(state.initialTestId));
    if (state.initialMaterialId) params.set('material_id', String(state.initialMaterialId));
    return `${API_BASE}/telegram_oidc_start.php${params.toString() ? `?${params}` : ''}`;
}

function buildVkOkIdentity(vkUser) {
    const params = vkLaunchParams();
    const vkClient = String(params.get('vk_client') || '').toLowerCase();
    const vkPlatform = String(params.get('vk_platform') || '').toLowerCase();
    const okUserId = params.get('vk_ok_user_id') || params.get('logged_user_id') || '';
    const isOk = vkClient === 'ok' || vkPlatform.includes('ok') || okUserId !== '';
    const platform = isOk ? 'OK' : 'VK';
    const platformUserId = isOk && okUserId !== '' ? okUserId : String(vkUser.id);

    return {platform, platformUserId};
}

async function authorize() {
    state.vkUser = await initVk();
    if (state.vkUser) {
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
        rememberCurrentReferralCode(getReferralCode());
        return state.user;
    }

    if (await initTelegram()) {
        return state.user;
    }

    if (await consumeTelegramOidc()) {
        return state.user;
    }

    return initWebUser();
}

async function authorizeWithReferral(referralCode) {
    referralCode = normalizeReferralCodeInput(referralCode);
    if (!referralCode) {
        throw new Error(ui('referral.code_required'));
    }

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
        return result.user;
    }

    if (state.auth && state.platform && state.platformUserId) {
        const result = await api('auth.php', {
            method: 'POST',
            body: JSON.stringify({
                platform: state.platform,
                platform_user_id: state.platformUserId,
                first_name: state.vkUser?.first_name || state.user?.first_name || '',
                last_name: state.vkUser?.last_name || state.user?.last_name || '',
                username: state.vkUser?.domain || state.user?.username || '',
                referral_code: referralCode,
                link_token: getLinkToken(),
                platform_meta: Object.fromEntries(vkLaunchParams().entries()),
            }),
        });
        state.user = result.user;
        return result.user;
    }

    return initWebUser(referralCode);
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

function isStaffPreview() {
    return Boolean(state.user?.staff_preview);
}

function updateStaffPreviewBanner() {
    if (!staffPreviewBanner) return;
    staffPreviewBanner.hidden = !isStaffPreview();
    document.body.classList.toggle('staff-preview', isStaffPreview());
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
    if (state.platform === 'VK') {
        return profile.vk_url || profile.ok_url || profile.whatsapp_url || '';
    }
    if (state.platform === 'OK') {
        return profile.ok_url || profile.vk_url || profile.whatsapp_url || '';
    }
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

function isTechnicalName(firstName, lastName) {
    return ['Web', 'VK'].includes(String(firstName || '').trim())
        && String(lastName || '').trim() === 'User';
}

function isTechnicalDisplayName(name) {
    return ['Web User', 'VK User'].includes(String(name || '').trim());
}

function userDisplayName(user) {
    if (!user || isTechnicalName(user.first_name, user.last_name)) {
        return user?.username || ui('profile.client');
    }
    const fullName = [user.first_name, user.last_name].filter(Boolean).join(' ');
    return fullName || user.username || ui('profile.client');
}

function platformAccountDisplayName(account) {
    if (isTechnicalName(account?.first_name, account?.last_name) || isTechnicalDisplayName(account?.display_name)) {
        return account?.username || ui('profile.platform_account');
    }
    const profileName = account?.display_name || [account?.first_name, account?.last_name].filter(Boolean).join(' ');
    return profileName || account?.username || ui('profile.platform_account');
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
    return ['home', 'cashback', 'contact', 'cooperation'].includes(pageName);
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

    if (String(url).split('?', 1)[0].toLowerCase().endsWith('.mp4')) {
        return `
            <div class="detail-video">
                <video controls preload="none" src="${escapeHtml(url)}" aria-label="${escapeHtml(title)}"></video>
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
    const tg = shouldUseTelegramRuntime() ? getTelegramApp() : null;
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

async function loadOnboarding() {
    if (isStaffPreview()) {
        state.onboarding = {
            complete: true,
            profile_complete: true,
            missing_consents: [],
            marketing_consent: false,
            web_merge_required: false,
        };
        return state.onboarding;
    }
    const result = await api(`onboarding.php?${userQuery()}`);
    state.user = result.user;
    state.onboarding = result.onboarding;
    return result.onboarding;
}

async function loadWebMergeLink() {
    if (!state.onboarding?.web_merge_required || isStaffPreview()) {
        state.webMergeLink = null;
        return null;
    }
    const result = await api('account_link.php', {
        method: 'POST',
        body: JSON.stringify(userPayload()),
    });
    state.webMergeLink = result;
    return result;
}

function renderWebMergeGate() {
    document.body.classList.add('auth-required');
    tabs.forEach((tab) => {
        tab.disabled = true;
        tab.classList.remove('active');
    });
    const links = state.webMergeLink?.links || {};
    const deadline = state.onboarding?.web_cleanup_deadline_at
        ? new Date(String(state.onboarding.web_cleanup_deadline_at).replace(' ', 'T')).toLocaleString('ru-RU')
        : 'через 5 дней';
    page.innerHTML = `
        <section class="panel web-merge-panel">
            <span class="eyebrow">Обязательное подтверждение</span>
            <h2>Объедините Web-профиль с личным аккаунтом</h2>
            <p>Анкета сохранена. Теперь подтвердите себя через VK или Telegram, чтобы профиль не потерялся при смене браузера или устройства.</p>
            <div class="web-merge-warning">
                Если аккаунт не будет подключён до <strong>${escapeHtml(deadline)}</strong>, временный Web-профиль и его данные будут удалены.
            </div>
            <div class="detail-actions">
                ${links.telegram ? `<a class="primary button-link" href="${escapeHtml(links.telegram)}" target="_blank" rel="noopener">Подключить Telegram</a>` : ''}
                ${links.vk ? `<a class="secondary button-link" href="${escapeHtml(links.vk)}" target="_blank" rel="noopener">Подключить VK</a>` : ''}
                <button class="secondary" data-action="check-web-merge">Я подключил аккаунт — проверить</button>
            </div>
            <p class="muted">Подключайте только свой личный аккаунт. После входа данные автоматически объединятся.</p>
        </section>
    `;
}

async function loadNotifications() {
    if (!state.onboarding?.complete) {
        state.notifications = [];
        return [];
    }
    try {
        const result = await api(`notifications.php?${userQuery()}`);
        state.notifications = result.notifications || [];
    } catch (_) {
        state.notifications = [];
    }
    return state.notifications;
}

async function loadToday() {
    if (!state.onboarding?.complete || isStaffPreview()) {
        state.today = null;
        return null;
    }
    try {
        state.today = await api(`today.php?${userQuery()}`);
    } catch (_) {
        state.today = null;
    }
    return state.today;
}

function renderTodayForClient() {
    const today = state.today;
    if (!today) return '';
    const plan = today.plan;
    const items = Array.isArray(today.plan_items) ? today.plan_items : [];
    const currentDay = Number(plan?.current_day || 1);
    const currentItems = plan ? items
        .filter((item) => Number(item.day_number) === currentDay || (Number(item.day_number) < currentDay && Number(item.is_completed) !== 1))
        .sort((left, right) => Number(right.day_number) - Number(left.day_number))
        .slice(0, 6) : [];
    const comparison = today.comparison;
    return `
        <section class="client-today-section">
            <div class="client-today-head"><div><span class="eyebrow">Сегодня для вас</span><h2>${escapeHtml(plan?.title || 'Ваш следующий полезный шаг')}</h2></div>${plan ? `<strong>${escapeHtml(plan.progress_percent)}%</strong>` : ''}</div>
            ${plan ? `<div class="client-plan-progress"><span style="width:${Math.max(0, Math.min(100, Number(plan.progress_percent || 0)))}%"></span></div><p class="muted">День ${escapeHtml(plan.current_day)} из ${escapeHtml(plan.duration_days)} · выполнено ${escapeHtml(plan.completed_items)} из ${escapeHtml(plan.total_items)}</p>` : '<p class="muted">После завершения чек-апа здесь появится персональный информационный план.</p>'}
            ${currentItems.length ? `<div class="client-plan-items">${currentItems.map((item) => `<label class="client-plan-item ${Number(item.is_completed) === 1 ? 'completed' : ''}"><input type="checkbox" data-action="toggle-plan-item" data-item-id="${Number(item.id)}" ${Number(item.is_completed) === 1 ? 'checked' : ''}><span><small>День ${escapeHtml(item.day_number)}</small><strong>${escapeHtml(item.title)}</strong>${item.instruction ? `<small>${escapeHtml(item.instruction)}</small>` : ''}</span></label>`).join('')}</div>` : ''}
            ${today.retest_available ? `<button class="secondary" data-page-target="tests">Пора пройти повторный чек-ап</button>` : today.retest_due_on ? `<p class="muted">Следующее сравнение можно запланировать после ${escapeHtml(new Date(`${today.retest_due_on}T00:00:00`).toLocaleDateString('ru-RU'))}.</p>` : ''}
        </section>
        ${comparison ? `<section class="client-comparison"><div><span class="eyebrow">Было → стало</span><h2>${escapeHtml(comparison.test_title)}</h2><p class="muted">${escapeHtml(comparison.disclaimer)}</p></div><div class="comparison-grid">${comparison.scales.slice(0, 6).map((item) => `<article><strong>${escapeHtml(item.title)}</strong><span>${escapeHtml(item.previous.score)} → ${escapeHtml(item.current.score)}</span><small>Изменение: ${Number(item.delta) > 0 ? '+' : ''}${escapeHtml(item.delta)}</small></article>`).join('')}</div></section>` : ''}
    `;
}

async function loadMessagingConfig() {
    if (!state.onboarding?.complete || !['VK', 'OK'].includes(state.platform)) {
        state.messagingConfig = {integrations: {}};
        return state.messagingConfig;
    }
    if (state.messagingConfig) {
        return state.messagingConfig;
    }
    if (state.messagingConfigPromise) {
        return state.messagingConfigPromise;
    }

    state.messagingConfigPromise = api(`messaging_config.php?${userQuery()}`)
        .then((result) => {
            state.messagingConfig = result;
            return result;
        })
        .catch(() => {
            state.messagingConfig = {integrations: {}};
            return state.messagingConfig;
        })
        .finally(() => {
            state.messagingConfigPromise = null;
        });

    return state.messagingConfigPromise;
}

function currentMessagingIntegration() {
    const integrations = state.messagingConfig?.integrations || {};
    return integrations[state.platform] || null;
}

function messagingPermissionWasRequested() {
    const integration = currentMessagingIntegration();
    if (state.platform === 'VK') {
        return integration?.permission_status === 'allowed';
    }
    if (!integration || !state.platformUserId) {
        return false;
    }
    return localStorage.getItem(`swpro_messages_allowed_${state.platform}_${integration.external_id}_${state.platformUserId}`) === '1';
}

function renderMessagingPermissionCard() {
    const integration = currentMessagingIntegration();
    if (state.onboarding?.marketing_consent_available === false
        || !state.onboarding?.marketing_consent
        || !integration
        || messagingPermissionWasRequested()) {
        return '';
    }

    const title = state.platform === 'VK'
        ? ui('messages.vk_title', 'Сообщения от консультанта VK')
        : ui('messages.ok_title', 'Сообщения от консультанта OK');
    const text = state.platform === 'VK'
        ? ui('messages.vk_hint', 'Разрешите сообщения от сообщества, чтобы получать ответы консультанта прямо во ВКонтакте.')
        : ui('messages.ok_hint', 'Разрешите сообщения от группы, чтобы получать ответы консультанта прямо в Одноклассниках.');
    const button = state.platform === 'VK'
        ? ui('messages.vk_allow', 'Разрешить сообщения VK')
        : ui('messages.ok_allow', 'Разрешить сообщения OK');

    return `
        <section class="message-permission-card">
            <strong>${escapeHtml(title)}</strong>
            <span>${escapeHtml(text)}</span>
            <button class="secondary compact" data-action="allow-social-messages">${escapeHtml(button)}</button>
        </section>
    `;
}

async function prepareVkMessagePermission() {
    return api('vk_message_permission.php', {
        method: 'POST',
        body: JSON.stringify(userPayload()),
    });
}

async function allowSocialMessages() {
    if (state.platform === 'VK' && window.vkBridge) {
        const permission = await prepareVkMessagePermission();
        if (permission.status !== 'allowed') {
            await vkBridge.send('VKWebAppAllowMessagesFromGroup', {
                group_id: Number(permission.group_id),
                key: String(permission.key || ''),
            });
        }
        state.messagingConfig = state.messagingConfig || {integrations: {}};
        state.messagingConfig.integrations = state.messagingConfig.integrations || {};
        state.messagingConfig.integrations.VK = {
            platform: 'VK',
            title: permission.title,
            external_id: permission.group_id,
            permission_status: 'allowed',
        };
        return permission;
    }

    if (state.platform === 'OK' && window.FAPI?.UI?.showPermissions) {
        await new Promise((resolve) => {
            window.FAPI.UI.showPermissions(['BOT_API_INIT'], resolve);
        });
        const integration = currentMessagingIntegration();
        if (integration && state.platformUserId) {
            localStorage.setItem(`swpro_messages_allowed_OK_${integration.external_id}_${state.platformUserId}`, '1');
        }
        await render();
        return;
    }

    page.insertAdjacentHTML(
        'afterbegin',
        `<div class="form-error">${escapeHtml(ui('messages.allow_unavailable', 'Разрешение сообщений доступно только внутри приложения платформы.'))}</div>`
    );
}

function loadVkOpenApi() {
    if (window.VK?.Widgets?.AllowMessagesFromCommunity) {
        return Promise.resolve(window.VK);
    }
    return new Promise((resolve, reject) => {
        const existing = document.querySelector('script[data-vk-open-api]');
        if (existing) {
            existing.addEventListener('load', () => resolve(window.VK), {once: true});
            existing.addEventListener('error', reject, {once: true});
            return;
        }
        const script = document.createElement('script');
        script.src = 'https://vk.com/js/api/openapi.js?169';
        script.async = true;
        script.dataset.vkOpenApi = '1';
        script.onload = () => resolve(window.VK);
        script.onerror = reject;
        document.head.appendChild(script);
    });
}

async function showWebVkPermissionWidget(form) {
    const permission = await prepareVkMessagePermission();
    if (permission.status === 'allowed') {
        setMarketingDeliveryHint(form, 'Готово: сообщения VK уже подключены.', 'success');
        return;
    }

    const hint = form?.querySelector('#marketing-delivery-hint');
    if (!hint) return;
    hint.hidden = false;
    hint.className = 'marketing-delivery-hint';
    hint.innerHTML = `
        <div>Подключите сообщения VK. Если вы ещё не вошли, VK сначала предложит войти. Либо укажите email ниже.</div>
        <div id="vk-web-message-widget" class="vk-web-message-widget"></div>
    `;

    const VK = await loadVkOpenApi();
    if (!VK?.Widgets?.AllowMessagesFromCommunity) {
        throw new Error('VK web widget is unavailable');
    }
    try {
        VK.init({apiId: Number(permission.app_id), onlyWidgets: true});
    } catch (_) {
        // VK Open API may already be initialized on this page.
    }
    VK.Observer.subscribe('widgets.allowMessagesFromCommunity.allowed', () => {
        setMarketingDeliveryHint(form, 'Готово: сообщения VK подключены.', 'success');
    });
    VK.Observer.subscribe('widgets.allowMessagesFromCommunity.denied', () => {
        setMarketingDeliveryHint(form, 'Сообщения VK не подключены. Укажите email, чтобы получать материалы и уведомления.', 'warning');
    });
    VK.Widgets.AllowMessagesFromCommunity(
        'vk-web-message-widget',
        {height: 30, key: String(permission.key || '')},
        Number(permission.group_id)
    );
}

function setMarketingDeliveryHint(form, message, tone = 'info') {
    const hint = form?.querySelector('#marketing-delivery-hint');
    if (!hint) return;
    hint.textContent = message;
    hint.className = `marketing-delivery-hint ${tone}`;
    hint.hidden = message === '';
}

async function handleMarketingConsentChange(checkbox) {
    const form = checkbox.form;
    if (!checkbox.checked) {
        setMarketingDeliveryHint(form, '');
        return;
    }
    if (state.platform === 'web') {
        checkbox.disabled = true;
        state.vkMessagePermissionAttempted = true;
        try {
            await showWebVkPermissionWidget(form);
        } catch (_) {
            setMarketingDeliveryHint(form, 'Не удалось открыть VK. Попробуйте ещё раз или укажите email для получения материалов и уведомлений.', 'warning');
        } finally {
            checkbox.disabled = false;
        }
        return;
    }
    if (state.platform !== 'VK' || !window.vkBridge) {
        setMarketingDeliveryHint(form, 'Для сообщений VK откройте эту страницу в браузере или приложении VK. Также можно указать email.', 'warning');
        return;
    }
    checkbox.disabled = true;
    state.vkMessagePermissionAttempted = true;
    setMarketingDeliveryHint(form, 'Открываем системное окно VK…');
    try {
        await allowSocialMessages();
        setMarketingDeliveryHint(form, 'Готово: сообщения VK разрешены для сообщества вашего консультанта.', 'success');
    } catch (error) {
        setMarketingDeliveryHint(form, 'Сообщения VK не подключены. Укажите email, чтобы мы могли отправлять вам материалы и уведомления.', 'warning');
    } finally {
        checkbox.disabled = false;
    }
}

function accountSuggestionDismissKey() {
    return state.user?.id ? `swpro_account_suggestion_dismissed_${state.user.id}` : '';
}

function accountSuggestionDismissed() {
    const key = accountSuggestionDismissKey();
    return key !== '' && sessionStorage.getItem(key) === '1';
}

async function loadAccountSuggestions() {
    if (!state.onboarding?.complete || accountSuggestionDismissed() || isStaffPreview()) {
        state.accountSuggestions = {suggestions: []};
        return state.accountSuggestions;
    }
    try {
        state.accountSuggestions = await api(`account_suggestions.php?${userQuery()}`);
    } catch (_) {
        state.accountSuggestions = {suggestions: []};
    }
    return state.accountSuggestions;
}

function onboardingDocuments() {
    const documents = state.onboarding?.documents || {};
    return Array.isArray(documents) ? documents : Object.values(documents);
}

function formatRuDateTime(value) {
    if (!value) {
        return '';
    }
    const normalized = String(value).replace(' ', 'T');
    const date = new Date(normalized);
    if (Number.isNaN(date.getTime())) {
        return String(value);
    }
    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

function legalDocumentLink(type, fallback) {
    const document = onboardingDocuments().find((item) => item.type === type);
    return `<a href="${escapeHtml(document?.url || `/legal.php?type=${type}`)}" target="_blank" rel="noopener">${escapeHtml(document?.title || fallback)}</a>`;
}

function updateLegalFooterLinks() {
    const documents = onboardingDocuments();
    document.querySelectorAll('[data-legal-type]').forEach((link) => {
        const type = link.dataset.legalType || '';
        const document = documents.find((item) => item.type === type);
        if (document?.url) {
            link.href = document.url;
            link.hidden = false;
            return;
        }
        const params = new URLSearchParams({type});
        link.href = `/legal.php?${params.toString()}`;
        link.hidden = false;
    });
}

function notificationAction(item) {
    if (!item.action_url) {
        return '';
    }
    try {
        const url = new URL(item.action_url, window.location.href);
        const targetPage = url.searchParams.get('page');
        const actionPath = url.pathname.replace(/index\.html$/, '').replace(/\/+$/, '');
        const currentPath = window.location.pathname.replace(/index\.html$/, '').replace(/\/+$/, '');
        if (
            url.origin === window.location.origin
            && actionPath === currentPath
            && ['home', 'tests', 'cashback', 'contact', 'cooperation'].includes(targetPage || '')
        ) {
            return `<button class="secondary compact" data-page-target="${escapeHtml(targetPage)}">${escapeHtml(item.action_text || 'Открыть')}</button>`;
        }
    } catch (_) {
        // Fall back to an external link.
    }
    return `<a class="soft-link" href="${escapeHtml(item.action_url)}" target="_blank" rel="noopener">${escapeHtml(item.action_text || 'Открыть')}</a>`;
}

function renderOnboardingGate() {
    document.body.classList.add('auth-required');
    tabs.forEach((tab) => {
        tab.disabled = true;
        tab.classList.remove('active');
    });
    const user = state.user || {};
    const profile = state.consultantProfile?.profile || {};
    const missing = new Set(state.onboarding?.missing_consents || []);
    const technicalName = isTechnicalName(user.first_name, user.last_name);
    const firstName = technicalName ? '' : (user.first_name || '');
    const lastName = technicalName ? '' : (user.last_name || '');
    page.innerHTML = `
        <section class="panel onboarding-panel">
            <div class="consultant-strip onboarding-consultant">
                ${profile.photo_path ? `<img class="consultant-photo" src="${escapeHtml(profile.photo_path)}" alt="">` : ''}
                <div class="consultant-meta">
                    <span class="eyebrow">Бот вашего консультанта</span>
                    <strong>${escapeHtml(profile.display_name || 'SWPro')}</strong>
                    <p>${escapeHtml(profile.welcome_text || profile.short_description || '')}</p>
                </div>
            </div>
            ${profile.welcome_video_url ? renderVideoBlock(profile.welcome_video_url, 'Приветствие консультанта') : ''}
            <h2>Расскажите немного о себе</h2>
            <p class="muted">Данные увидит только закреплённый консультант и руководство его команды.</p>
            ${state.platform === 'web' ? `
                <div class="web-merge-warning">
                    Пока соглашение и анкета не подтверждены, профиль является временным и удаляется через 3 дня. Если до завершения анкеты открыть ссылку другого лидера, будет выбран новый лидер.
                </div>
            ` : ''}

            <form id="onboarding-form" class="onboarding-form">
                <div class="legal-consents">
                    <label class="consent-line">
                        <input type="checkbox" name="personal_consent" ${!missing.has('personal_data_consent') && !missing.has('user_agreement') ? 'checked' : ''} required>
                        <span>Принимаю ${legalDocumentLink('personal_data_consent', 'согласие на обработку данных')} и ${legalDocumentLink('user_agreement', 'пользовательское соглашение')}</span>
                    </label>
                    <label class="consent-line">
                        <input type="checkbox" name="health_consent" ${!missing.has('health_data_consent') ? 'checked' : ''} required>
                        <span>Принимаю ${legalDocumentLink('health_data_consent', 'согласие на обработку ответов чек-апа')}</span>
                    </label>
                    ${state.onboarding?.marketing_consent_available !== false ? `
                        <label class="consent-line optional">
                            <input type="checkbox" name="marketing_consent" ${state.onboarding?.marketing_consent ? 'checked' : ''}>
                            <span>Хочу получать полезные материалы, акции и новости. ${legalDocumentLink('marketing_consent', 'Подробнее')}</span>
                        </label>
                        <div id="marketing-delivery-hint" class="marketing-delivery-hint" hidden></div>
                    ` : ''}
                </div>

                <label>
                    <span>Имя *</span>
                    <input name="first_name" required maxlength="190" value="${escapeHtml(firstName)}">
                </label>
                <label>
                    <span>Фамилия *</span>
                    <input name="last_name" required maxlength="190" value="${escapeHtml(lastName)}">
                </label>
                <label>
                    <span>Номер телефона</span>
                    <input type="tel" name="phone" maxlength="50" autocomplete="tel" value="${escapeHtml(user.phone || '')}">
                </label>
                <label>
                    <span>Email</span>
                    <input type="email" name="email" maxlength="190" autocomplete="email" value="${escapeHtml(user.email || '')}">
                </label>
                <label>
                    <span>Пол *</span>
                    <select name="gender" required>
                        <option value="">Выберите</option>
                        <option value="female" ${user.gender === 'female' ? 'selected' : ''}>Женщина</option>
                        <option value="male" ${user.gender === 'male' ? 'selected' : ''}>Мужчина</option>
                        <option value="prefer_not_to_say" ${user.gender === 'prefer_not_to_say' ? 'selected' : ''}>Не хочу указывать</option>
                    </select>
                </label>
                <div class="onboarding-age-grid">
                    <label>
                        <span>Возраст</span>
                        <input type="number" name="age_years" min="14" max="100" value="${escapeHtml(user.age_years || '')}">
                    </label>
                    <label>
                        <span>или дата рождения</span>
                        <input type="date" name="birth_date" value="${escapeHtml(user.birth_date || '')}">
                    </label>
                </div>
                <label>
                    <span>Город *</span>
                    <input name="city" required maxlength="190" value="${escapeHtml(user.city || '')}">
                </label>
                <button class="primary" type="submit">Сохранить и продолжить</button>
                <div class="form-error" id="onboarding-error"></div>
            </form>
            <p class="muted legal-note">Перед отправкой можно прочитать ${legalDocumentLink('privacy_policy', 'политику обработки персональных данных')}.</p>
        </section>
    `;
}

function renderAuthGate() {
    document.body.classList.add('auth-required');
    document.body.classList.remove('referral-required');
    tabs.forEach((tab) => {
        tab.disabled = true;
        tab.classList.remove('active');
    });
    const vkOkContext = isVkOkContext();
    const authText = vkOkContext
        ? 'Приложение открылось без данных платформы. Введите реферальный код консультанта или откройте персональную ссылку еще раз.'
        : ui('auth.required_text');
    const platformBadges = vkOkContext
        ? `${hasOkLaunchParams() ? '<span>OK</span>' : '<span>VK</span>'}`
        : '<span>Telegram</span><span>VK</span><span>OK</span>';
    page.innerHTML = `
        <section class="panel auth-panel">
            <h2>${escapeHtml(ui('auth.required_title'))}</h2>
            <p class="muted">${escapeHtml(authText)}</p>
            <div class="auth-platforms">
                ${platformBadges}
            </div>
            ${vkOkContext ? '' : `<a class="primary button-link" href="${escapeHtml(telegramOidcStartUrl())}">Войти через Telegram</a>`}
            ${vkOkContext ? '' : `<div class="auth-divider">${escapeHtml(ui('referral.or_code'))}</div>`}
            ${referralFormMarkup()}
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
    const linkReferralCode = normalizeReferralCodeInput(getReferralCode());
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
            ${referralFormMarkup(linkReferralCode)}
        </section>
    `;
}

function referralFormMarkup(value = '') {
    return `
        <form class="referral-form" id="referral-form">
            <label>
                <span>${escapeHtml(ui('referral.code_label'))}</span>
                <input
                    name="referral_code"
                    autocomplete="one-time-code"
                    required
                    maxlength="64"
                    value="${escapeHtml(value)}"
                    placeholder="${escapeHtml(ui('referral.code_placeholder'))}"
                >
            </label>
            <button class="primary" type="submit">${escapeHtml(ui('referral.submit'))}</button>
            <div class="form-error" id="referral-error"></div>
        </form>
    `;
}

function renderAccountSuggestionCard() {
    const result = state.accountSuggestions || {};
    const suggestions = Array.isArray(result.suggestions) ? result.suggestions : [];
    if (!suggestions.length || accountSuggestionDismissed()) {
        return '';
    }

    const vkOkContext = isVkOkContext();
    const visibleSuggestions = vkOkContext
        ? suggestions.filter((item) => item.platform !== 'telegram')
        : suggestions;
    if (!visibleSuggestions.length) {
        return '';
    }

    const links = result.linking?.links || {};
    const platforms = visibleSuggestions
        .map((item) => item.platform_label || platformLabel(item.platform))
        .filter(Boolean)
        .join(', ');
    const actions = [];
    if (!vkOkContext && links.telegram && visibleSuggestions.some((item) => item.platform === 'telegram')) {
        actions.push(`<a class="soft-link" href="${escapeHtml(links.telegram)}" target="_blank" rel="noopener">${escapeHtml(ui('account_link.open_telegram'))}</a>`);
    }
    if (links.vk && visibleSuggestions.some((item) => item.platform === 'VK')) {
        actions.push(`<a class="soft-link" href="${escapeHtml(links.vk)}" target="_blank" rel="noopener">${escapeHtml(ui('account_link.open_vk'))}</a>`);
    }
    if (links.mini_app) {
        actions.push(`<a class="soft-link" href="${escapeHtml(links.mini_app)}" target="_blank" rel="noopener">${escapeHtml(ui('account_link.open_mini_app'))}</a>`);
    }

    return `
        <section class="account-suggestion-card">
            <div>
                <span class="eyebrow">${escapeHtml(ui('account_link.suggestion_eyebrow'))}</span>
                <h2>${escapeHtml(ui('account_link.suggestion_title'))}</h2>
                <p class="muted">${escapeHtml(formatUi('account_link.suggestion_text', {platforms}, `Похоже, у вас уже есть профиль: ${platforms}. Объединяйте только свои личные аккаунты.`))}</p>
            </div>
            <div class="detail-actions">
                ${actions.join('')}
                <button class="secondary" data-action="dismiss-account-suggestion">${escapeHtml(ui('account_link.later'))}</button>
            </div>
        </section>
    `;
}

function renderHome() {
    const profile = state.consultantProfile?.profile || {};
    const initials = String(profile.display_name || 'SW').slice(0, 2).toUpperCase();
    const unreadNotifications = (state.notifications || []).filter((item) => Number(item.is_read) === 0);
    const welcomeText = profile.welcome_text || profile.short_description || 'Пройдите бесплатный чек-ап организма и обсудите результат со своим консультантом.';

    page.innerHTML = `
        <section class="home-hero">
            ${profile.banner_path ? `<img class="home-banner" src="${escapeHtml(profile.banner_path)}" alt="" ${lazyImageAttrs()}>` : ''}
            <div class="consultant-strip">
                ${profile.photo_path ? `<img class="consultant-photo" src="${escapeHtml(profile.photo_path)}" alt="" ${lazyImageAttrs()}>` : `<div class="consultant-photo placeholder">${escapeHtml(initials)}</div>`}
                <div class="consultant-meta">
                    <span class="eyebrow">${escapeHtml(profile.title || 'Ваш консультант')}</span>
                    <h2>${escapeHtml(profile.display_name || 'SWPro')}</h2>
                    <p>${escapeHtml(profile.subtitle || '')}</p>
                </div>
            </div>
            <div class="consultant-note">${renderTextBlocks(welcomeText)}</div>
            ${profile.welcome_video_url ? renderVideoBlock(profile.welcome_video_url, 'Приветствие консультанта') : ''}
        </section>

        ${renderTodayForClient()}

        ${unreadNotifications.length ? `
            <section class="notification-list">
                ${unreadNotifications.slice(0, 3).map((item) => `
                    <article class="notification-card">
                        <strong>${escapeHtml(item.title)}</strong>
                        <p>${escapeHtml(item.message_text)}</p>
                        ${item.image_path ? `<img class="notification-media" src="${escapeHtml(item.image_path)}" alt="" ${lazyImageAttrs()}>` : ''}
                        ${item.video_path ? renderVideoBlock(item.video_path, item.title) : ''}
                        ${notificationAction(item)}
                        <button class="text-button" data-action="mark-notification" data-notification-id="${item.id}">Прочитано</button>
                    </article>
                `).join('')}
            </section>
        ` : ''}

        ${renderAccountSuggestionCard()}

        <section class="main-action-grid">
            <button class="action-card" data-page-target="tests">
                <span>🌿</span>
                <strong>Чек-ап организма</strong>
                <small>Пройти или посмотреть результат</small>
            </button>
            <button class="action-card" data-page-target="cashback">
                <span>🎁</span>
                <strong>Кэшбэк и подарки</strong>
                <small>Преимущества карты клиента</small>
            </button>
            <button class="action-card" data-page-target="contact">
                <span>📌</span>
                <strong>Связаться</strong>
                <small>Задать вопрос консультанту</small>
            </button>
            <button class="action-card" data-page-target="cooperation">
                <span>🤝</span>
                <strong>Сотрудничество</strong>
                <small>Узнать о возможностях</small>
            </button>
        </section>

        <div class="privacy-actions">
            <button class="text-button" data-action="disable-marketing">Отключить информационные рассылки</button>
            <button class="text-button danger-text" data-action="revoke-all">Отозвать все согласия</button>
        </div>
    `;
}

function renderLegacyHome() {
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

function renderCashback() {
    const profile = state.consultantProfile?.profile || {};
    const cards = (profile.cashback_cards || []).filter((card) =>
        card.title || card.description || card.image_path || card.card_url
    );
    if (!cards.length) {
        cards.push({
            title: profile.cashback_title || 'Кэшбэк и подарки',
            description: profile.cashback_text || 'Оформите карту клиента, чтобы пользоваться доступными преимуществами. Консультант поможет с регистрацией и ответит на вопросы.',
            image_path: profile.cashback_image_path || '',
            card_url: profile.cashback_url || '',
            button_text: 'Оформить карту клиента',
        });
    }
    page.innerHTML = `
        <div class="cashback-card-stack">
            ${cards.map((card) => `
                <section class="panel feature-page">
                    ${card.image_path ? `<img class="feature-image" src="${escapeHtml(card.image_path)}" alt="" ${lazyImageAttrs()}>` : ''}
                    ${card.title ? `<h2>${escapeHtml(card.title)}</h2>` : ''}
                    ${card.description ? `<div class="rich-text">${renderTextBlocks(card.description)}</div>` : ''}
                    <div class="detail-actions">
                        ${card.card_url ? `<a class="primary button-link" href="${escapeHtml(card.card_url)}" target="_blank" rel="noopener">${escapeHtml(card.button_text || 'Оформить карту клиента')}</a>` : ''}
                        <button class="secondary" data-action="contact-cashback">Задать вопрос консультанту</button>
                    </div>
                </section>
            `).join('')}
        </div>
    `;
}

function renderCooperation() {
    const profile = state.consultantProfile?.profile || {};
    const title = profile.cooperation_title || 'Возможность сотрудничества';
    const text = profile.cooperation_text || 'Узнайте о вариантах сотрудничества и поддержке команды. Подробности можно обсудить с консультантом.';
    page.innerHTML = `
        <section class="panel feature-page">
            ${profile.cooperation_image_path ? `<img class="feature-image" src="${escapeHtml(profile.cooperation_image_path)}" alt="" ${lazyImageAttrs()}>` : ''}
            <span class="eyebrow">Команда</span>
            <h2>${escapeHtml(title)}</h2>
            <div class="rich-text">${renderTextBlocks(text)}</div>
            ${profile.cooperation_video_url ? renderVideoBlock(profile.cooperation_video_url, title) : ''}
            <div class="detail-actions">
                <button class="primary" data-action="contact-cooperation">Обсудить сотрудничество</button>
            </div>
        </section>
    `;
}

async function renderContactPage() {
    await loadMessagingConfig();
    const profile = state.consultantProfile?.profile || {};
    const contacts = [
        ['phone', 'Телефон', profile.phone],
        ['email', 'Email', profile.email ? `mailto:${profile.email}` : ''],
        ['telegram', 'Telegram', profile.telegram_url],
        ['whatsapp', 'WhatsApp', profile.whatsapp_url],
        ['vk', 'VK', profile.vk_url],
        ['ok', 'OK', profile.ok_url],
    ].filter(([key, , value]) => value && !(isVkOkContext() && key === 'telegram'));
    page.innerHTML = `
        <section class="panel feature-page contact-page">
            <span class="eyebrow">Ваш консультант</span>
            <h2>${escapeHtml(profile.display_name || 'Связаться с консультантом')}</h2>
            <p class="muted">Напишите сообщение здесь или выберите удобный способ связи.</p>
            ${contacts.length ? `
                <div class="contact-list">
                    ${contacts.map(([, label, value]) => `
                        <a href="${escapeHtml(value)}" target="_blank" rel="noopener">
                            <span>${escapeHtml(label)}</span>
                            <strong>${escapeHtml(String(value).replace(/^mailto:/, ''))}</strong>
                        </a>
                    `).join('')}
                </div>
            ` : ''}
            <button class="primary" data-action="contact">Написать консультанту</button>
        </section>
        ${renderMessagingPermissionCard()}
        <section class="panel contact-page" id="lead-history">
            <div class="empty">${escapeHtml(ui('common.loading'))}</div>
        </section>
    `;

    try {
        const result = await api(`leads.php?${userQuery()}`);
        const unreadLeadIds = result.leads.filter(leadHasUnreadResponse).map((lead) => lead.id);
        const history = document.querySelector('#lead-history');
        if (history) {
            history.innerHTML = `
                <div class="section-title">
                    <h2>${escapeHtml(ui('leads.history_title', 'Ваши обращения'))}</h2>
                </div>
                ${leadListMarkup(result.leads)}
            `;
        }
        await Promise.allSettled(unreadLeadIds.map(markLeadRead));
    } catch (error) {
        const history = document.querySelector('#lead-history');
        if (history) {
            history.innerHTML = `<div class="empty">${escapeHtml(friendlyError(error))}</div>`;
        }
    }
}

async function renderProfile() {
    if (state.accountSuggestions === null && state.onboarding?.complete) {
        await loadAccountSuggestions();
    }
    await loadMessagingConfig();
    const result = await api(`user.php?${userQuery()}`);
    const user = result.user;
    const accounts = (result.platform_accounts || [])
        .filter((account) => !(isVkOkContext() && account.platform === 'telegram'));
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
        ${renderAccountSuggestionCard()}
        ${renderMessagingPermissionCard()}
        <section class="home-section">
            <div class="section-title">
                <h2>${escapeHtml(ui('profile.accounts'))}</h2>
            </div>
            <p class="muted">${escapeHtml(ui('profile.accounts_hint'))}</p>
            ${accounts.length ? accounts.map((account) => `
                <article class="platform-card">
                    <span class="platform-pill">${escapeHtml(platformLabel(account.platform))}</span>
                    <strong>${escapeHtml(platformAccountDisplayName(account))}</strong>
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
        const telegramLink = isVkOkContext() ? '' : (result.links?.telegram || '');
        const vkLink = result.links?.vk || '';
        const warning = isVkOkContext()
            ? 'Подключайте только свои личные аккаунты. Система не объединяет платформы автоматически.'
            : ui('profile.link_warning');
        panel.innerHTML = `
            <div class="link-card">
                <strong>${escapeHtml(ui('profile.link_title', 'Ссылка для подключения'))}</strong>
                <span class="muted">${escapeHtml(ui('profile.link_hint', 'Откройте ссылку на другой платформе и подтвердите вход.'))}</span>
                <span class="muted">${escapeHtml(warning)}</span>
                ${miniAppLink ? `<a href="${escapeHtml(miniAppLink)}" target="_blank" rel="noopener">${escapeHtml(ui('profile.open_mini_app', 'Открыть Mini App'))}</a>` : ''}
                ${telegramLink ? `<a href="${escapeHtml(telegramLink)}" target="_blank" rel="noopener">${escapeHtml(ui('profile.open_telegram', 'Подключить Telegram'))}</a>` : ''}
                ${vkLink ? `<a href="${escapeHtml(vkLink)}" target="_blank" rel="noopener">${escapeHtml(ui('profile.open_vk', 'Подключить VK'))}</a>` : ''}
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
    return ui(`request_type.${lead.request_type || 'consultation'}`, ui('leads.question'));
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

function leadListMarkup(leads) {
    return leads.length
        ? leads.map((lead) => `
            <article class="lead-chat-card">
                <div class="lead-chat-head">
                    <div>
                        <strong>${escapeHtml(leadTitle(lead))}</strong>
                        <span class="muted">${escapeHtml(platformLabel(lead.source_platform))} · ${escapeHtml(formatRuDateTime(lead.created_at))}</span>
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
                        <small class="muted">${escapeHtml(formatRuDateTime(response.sent_at || response.created_at))}</small>
                    </div>
                `).join('')}
            </article>
        `).join('')
        : `<div class="empty-card">${escapeHtml(ui('leads.empty'))}</div>`;
}

async function renderLeads() {
    const result = await api(`leads.php?${userQuery()}`);
    const unreadLeadIds = result.leads.filter(leadHasUnreadResponse).map((lead) => lead.id);
    page.innerHTML = leadListMarkup(result.leads);
    await Promise.allSettled(unreadLeadIds.map(markLeadRead));
}

function openContactModal(productId = null, presetMessage = '', titleOverride = '', requestType = 'consultation') {
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
                    <input type="hidden" name="request_type" value="${escapeHtml(requestType)}">
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

async function createLeadFromMessage(productId = null, message = '', requestType = 'consultation') {
    const text = String(message || '').trim();
    if (!text) {
        throw new Error(ui('lead.message_required'));
    }

    const result = await api('contact_manager.php', {
        method: 'POST',
        body: JSON.stringify({
            ...userPayload(),
            product_id: productId,
            request_type: requestType,
            message: text,
        }),
    });

    page.insertAdjacentHTML('afterbegin', `
        <div class="panel">
            ${escapeHtml(formatUi('lead.created', {id: result.lead_id}))}
        </div>
    `);
}

async function renderTests() {
    const testsResponse = await api(`tests.php?${userQuery()}`);
    const primaryTest = testsResponse.tests.find((test) => String(test.title || '').toLowerCase().includes('диагност'))
        || testsResponse.tests.find((test) => test.scoring_type === 'multiscale')
        || testsResponse.tests[0];
    const tests = primaryTest ? [primaryTest] : [];
    page.innerHTML = tests.length
        ? tests.map((test) => {
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
    if (state.answerPending) {
        return;
    }
    state.answerPending = true;
    page.querySelectorAll('.answer-button, #test-question-form button').forEach((button) => {
        button.disabled = true;
    });
    const question = state.activeTest.question;
    try {
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
            await loadToday();
            renderTestResult(result);
            return;
        }
        renderTestQuestion(result);
    } finally {
        state.answerPending = false;
    }
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
    const plan = state.today?.plan;
    page.innerHTML = `
        <section class="panel">
            <div class="result-card">
                <strong>${escapeHtml(result.result?.title || ui('result.default_title'))}</strong>
                <span class="result-score">${escapeHtml(ui('result.score'))}: ${escapeHtml(result.total_score)}</span>
                <div class="result-summary">${renderTextBlocks(result.summary)}</div>
            </div>
            ${renderScaleResults(result.scale_results || [])}
            ${plan ? `<div class="result-card"><strong>Ваш план уже готов</strong><p>План на ${escapeHtml(plan.duration_days)} дней появился на странице «Сегодня для вас». Там можно отмечать выполненные шаги и видеть прогресс.</p><button class="secondary" data-page-target="home">Открыть план</button></div>` : ''}
            <div class="result-actions">
                <button class="primary" data-action="contact-result">${escapeHtml(ui('result.contact_manager', 'Разобрать с консультантом'))}</button>
                <button class="secondary" data-page-target="tests">Вернуться к чек-апу</button>
            </div>
        </section>
    `;
}

async function render() {
    updateLegalFooterLinks();
    updateStaffPreviewBanner();
    if (state.authBlocked === 'staff') {
        renderStaffGate();
        return;
    }
    if (!hasTeamAccess()) {
        renderReferralGate();
        return;
    }
    if (!state.onboarding?.complete) {
        renderOnboardingGate();
        return;
    }
    if (state.onboarding?.web_merge_required && !isStaffPreview()) {
        if (!state.webMergeLink) {
            try {
                await loadWebMergeLink();
            } catch (_) {
                state.webMergeLink = {links: {}};
            }
        }
        renderWebMergeGate();
        return;
    }
    if (clientAiChat) clientAiChat.hidden = isStaffPreview();
    document.body.classList.remove('auth-required', 'referral-required');
    tabs.forEach((tab) => {
        tab.disabled = false;
        tab.classList.toggle('active', tab.dataset.page === state.page);
    });
    page.innerHTML = `<div class="empty">${escapeHtml(ui('common.loading'))}</div>`;
    try {
        if (state.initialMaterialId) {
            const materialId = state.initialMaterialId;
            state.initialMaterialId = null;
            await renderMaterialDetail(materialId);
            prefetchConsultantProfile();
            return;
        }
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
        if (['cashback', 'contact', 'cooperation'].includes(state.page)) {
            if (!state.consultantProfile) {
                await loadConsultantProfile();
            }
            if (state.page === 'cashback') renderCashback();
            if (state.page === 'contact') await renderContactPage();
            if (state.page === 'cooperation') renderCooperation();
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
        if (!pageNeedsConsultantProfile()) {
            prefetchConsultantProfile();
        }
    } catch (error) {
        page.innerHTML = `<div class="empty-card">${escapeHtml(friendlyError(error))}</div>`;
    }
}

function setClientAiOpen(open) {
    if (!clientAiPanel || !clientAiToggle) return;
    clientAiPanel.hidden = !open;
    clientAiToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) window.setTimeout(() => clientAiForm?.querySelector('textarea')?.focus(), 0);
}

function addClientAiMessage(text, role, citations = []) {
    if (!clientAiMessages) return null;
    const node = document.createElement('div');
    node.className = `client-ai-message ${role}`;
    const body = document.createElement('div');
    body.textContent = text;
    node.appendChild(body);
    if (citations.length) {
        const sources = document.createElement('small');
        sources.textContent = `Источники: ${citations.map((item) => item.label).join('; ')}`;
        node.appendChild(sources);
    }
    clientAiMessages.appendChild(node);
    clientAiMessages.scrollTop = clientAiMessages.scrollHeight;
    return node;
}

clientAiToggle?.addEventListener('click', () => setClientAiOpen(clientAiPanel?.hidden !== false));
clientAiClose?.addEventListener('click', () => setClientAiOpen(false));
clientAiForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const input = clientAiForm.querySelector('textarea[name="message"]');
    const button = clientAiForm.querySelector('button[type="submit"]');
    const question = String(input?.value || '').trim();
    if (!question || !input || !button) return;
    addClientAiMessage(question, 'user');
    input.value = '';
    input.disabled = true;
    button.disabled = true;
    const pending = addClientAiMessage('Ищу ответ в материалах…', 'assistant pending');
    try {
        const result = await api('ai_chat.php', {method: 'POST', body: JSON.stringify({...userPayload(), message: question})});
        pending?.remove();
        addClientAiMessage(result.answer || 'Ответ не найден.', 'assistant', Array.isArray(result.citations) ? result.citations : []);
    } catch (error) {
        pending?.remove();
        addClientAiMessage(friendlyError(error), 'assistant error');
    } finally {
        input.disabled = false;
        button.disabled = false;
        input.focus();
    }
});

tabs.forEach((tab) => {
    tab.addEventListener('click', () => setPage(tab.dataset.page));
});
homeLink?.addEventListener('click', () => setPage('home'));

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
            ui('result.contact_manager', 'Разобрать с консультантом'),
            'test_result'
        );
    }
    if (target.dataset.action === 'contact-cashback') {
        openContactModal(
            null,
            'Здравствуйте! Хочу узнать подробнее о регистрации, кэшбэке и подарках.',
            'Кэшбэк и регистрация',
            'cashback'
        );
    }
    if (target.dataset.action === 'contact-cooperation') {
        openContactModal(
            null,
            'Здравствуйте! Хочу узнать подробнее о возможности сотрудничества.',
            'Обсудить сотрудничество',
            'cooperation'
        );
    }
    if (target.dataset.action === 'disable-marketing') {
        await api('onboarding.php', {
            method: 'POST',
            body: JSON.stringify({...userPayload(), action: 'revoke_marketing'}),
        });
        state.onboarding.marketing_consent = false;
        target.textContent = 'Рассылки отключены';
        target.setAttribute('disabled', 'disabled');
    }
    if (target.dataset.action === 'revoke-all') {
        if (!window.confirm('Отозвать все согласия и прекратить использование SWPro?')) {
            return;
        }
        await api('onboarding.php', {
            method: 'POST',
            body: JSON.stringify({...userPayload(), action: 'revoke_all'}),
        });
        state.onboarding.complete = false;
        state.onboarding.missing_consents = ['personal_data_consent', 'health_data_consent', 'user_agreement'];
        renderOnboardingGate();
    }
    if (target.dataset.action === 'mark-notification') {
        const notificationId = Number(target.dataset.notificationId || 0);
        await api('notifications.php?action=mark_read', {
            method: 'POST',
            body: JSON.stringify({...userPayload(), id: notificationId}),
        });
        state.notifications = state.notifications.map((item) => (
            Number(item.id) === notificationId ? {...item, is_read: 1} : item
        ));
        renderHome();
    }
    if (target.dataset.action === 'toggle-plan-item') {
        const input = target.matches('input') ? target : target.querySelector('input');
        const itemId = Number(target.dataset.itemId || input?.dataset.itemId || 0);
        if (itemId > 0 && input) {
            input.disabled = true;
            try {
                state.today = await api('today.php', {method: 'POST', body: JSON.stringify({...userPayload(), item_id: itemId, completed: input.checked})});
                renderHome();
            } catch (error) {
                input.checked = !input.checked;
                input.disabled = false;
            }
        }
    }
    if (target.dataset.action === 'dismiss-account-suggestion') {
        const key = accountSuggestionDismissKey();
        if (key) {
            sessionStorage.setItem(key, '1');
        }
        state.accountSuggestions = {suggestions: []};
        await render();
    }
    if (target.dataset.action === 'allow-social-messages') {
        try {
            await allowSocialMessages();
            await render();
        } catch (_) {
            page.insertAdjacentHTML(
                'afterbegin',
                `<div class="form-error">${escapeHtml(ui('messages.allow_failed', 'Не удалось получить разрешение на сообщения. Попробуйте позже или напишите консультанту первым сообщением.'))}</div>`
            );
        }
    }
    if (target.dataset.action === 'start-test') await startTestSession(false);
    if (target.dataset.action === 'resume-test') renderTestQuestion(state.activeTest);
    if (target.dataset.action === 'restart-test') await startTestSession(true);
    if (target.dataset.action === 'show-test-result') await showCompletedTestResult();
    if (target.dataset.action === 'answer-test') {
        try {
            await answerCurrentQuestion(Number(target.dataset.answerId || 0));
        } catch (error) {
            page.insertAdjacentHTML('afterbegin', `<div class="form-error">${escapeHtml(friendlyError(error))}</div>`);
            page.querySelectorAll('.answer-button').forEach((button) => {
                button.disabled = false;
            });
        }
    }
    if (target.dataset.action === 'close-modal') closeModal();
    if (target.dataset.action === 'create-link-token') await renderAccountLinkPanel();
    if (target.dataset.action === 'check-web-merge') {
        target.disabled = true;
        try {
            await loadOnboarding();
            state.webMergeLink = null;
            if (state.onboarding?.web_merge_required) {
                await loadWebMergeLink();
            } else {
                await Promise.all([loadConsultantProfile(), loadNotifications(), loadAccountSuggestions()]);
            }
            await render();
        } finally {
            target.disabled = false;
        }
    }
    if (target.dataset.pageTarget) setPage(target.dataset.pageTarget);
    if (target.dataset.openTestId) await renderTest(Number(target.dataset.openTestId));
    if (target.dataset.openProductId) await renderProductDetail(Number(target.dataset.openProductId));
    if (target.dataset.openMaterialId) await renderMaterialDetail(Number(target.dataset.openMaterialId));
    if (target.dataset.productId) openContactModal(Number(target.dataset.productId), '', '', 'product');
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

page.addEventListener('change', async (event) => {
    const target = event.target;
    if (target instanceof HTMLInputElement && target.name === 'marketing_consent') {
        await handleMarketingConsentChange(target);
    }
});

page.addEventListener('submit', async (event) => {
    const target = event.target;
    if (target instanceof HTMLFormElement && target.id === 'onboarding-form') {
        event.preventDefault();
        const formData = new FormData(target);
        const error = target.querySelector('#onboarding-error');
        const button = target.querySelector('button[type="submit"]');
        const ageYears = Number(formData.get('age_years') || 0);
        const birthDate = String(formData.get('birth_date') || '');
        if (!ageYears && !birthDate) {
            if (error) error.textContent = 'Укажите возраст или дату рождения.';
            return;
        }
        if (button) button.disabled = true;
        if (error) error.textContent = '';
        try {
            const documentTypes = [
                'personal_data_consent',
                'user_agreement',
                'health_data_consent',
            ];
            const marketingConsent = formData.get('marketing_consent') === 'on';
            if (marketingConsent
                && state.platform === 'VK'
                && !messagingPermissionWasRequested()
                && !state.vkMessagePermissionAttempted) {
                try {
                    await allowSocialMessages();
                    setMarketingDeliveryHint(target, 'Готово: сообщения VK разрешены для сообщества вашего консультанта.', 'success');
                } catch (_) {
                    setMarketingDeliveryHint(target, 'Сообщения VK не подключены. Укажите email, чтобы получать материалы и уведомления.', 'warning');
                }
            }
            if (marketingConsent) {
                documentTypes.push('marketing_consent');
            }
            await api('onboarding.php', {
                method: 'POST',
                body: JSON.stringify({
                    ...userPayload(),
                    action: 'consent',
                    document_types: documentTypes,
                }),
            });
            if (!marketingConsent && state.onboarding?.marketing_consent) {
                await api('onboarding.php', {
                    method: 'POST',
                    body: JSON.stringify({...userPayload(), action: 'revoke_marketing'}),
                });
            }
            const result = await api('onboarding.php', {
                method: 'POST',
                body: JSON.stringify({
                    ...userPayload(),
                    action: 'profile',
                    first_name: String(formData.get('first_name') || '').trim(),
                    last_name: String(formData.get('last_name') || '').trim(),
                    phone: String(formData.get('phone') || '').trim(),
                    email: String(formData.get('email') || '').trim(),
                    gender: String(formData.get('gender') || ''),
                    age_years: ageYears || null,
                    birth_date: birthDate || null,
                    city: String(formData.get('city') || '').trim(),
                    timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'Europe/Moscow',
                }),
            });
            state.user = result.user;
            state.onboarding = result.onboarding;
            state.page = 'home';
            if (state.onboarding?.web_merge_required) {
                await loadWebMergeLink();
            } else {
                await Promise.all([loadConsultantProfile(), loadNotifications(), loadAccountSuggestions()]);
            }
            await render();
        } catch (exception) {
            if (error) error.textContent = exception instanceof Error ? exception.message : 'Не удалось сохранить анкету.';
        } finally {
            if (button) button.disabled = false;
        }
        return;
    }
    if (target instanceof HTMLFormElement && target.id === 'referral-form') {
        event.preventDefault();
        const error = target.querySelector('#referral-error');
        const button = target.querySelector('button[type="submit"]');
        const formData = new FormData(target);
        const referralCode = normalizeReferralCodeInput(formData.get('referral_code'));
        if (!referralCode) {
            if (error) error.textContent = ui('referral.code_required');
            return;
        }
        if (error) error.textContent = '';
        if (button) button.disabled = true;
        try {
            await authorizeWithReferral(referralCode);
            if (!hasTeamAccess()) {
                throw new Error(ui('referral.invalid_code'));
            }
            rememberCurrentReferralCode(referralCode);
            await loadOnboarding();
            await loadConsultantProfile();
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
    const requestType = String(formData.get('request_type') || 'consultation');
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
        await createLeadFromMessage(productId, message, requestType);
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
    .then(async () => {
        if (!state.user) {
            renderAuthGate();
            return;
        }
        if (!hasTeamAccess()) {
            await render();
            return;
        }
        await loadOnboarding();
        await render();

        const deferred = [loadNotifications(), loadMessagingConfig(), loadToday()];
        if (hasTeamAccess()) {
            deferred.push(loadConsultantProfile());
        }
        if (state.onboarding?.complete) {
            deferred.push(loadAccountSuggestions());
        }
        Promise.all(deferred)
            .then(() => {
                if (state.page === 'home' || !state.onboarding?.complete) {
                    return render();
                }
                return null;
            })
            .catch(() => {});
    })
    .catch((error) => {
        if (error instanceof AppApiError && error.code === 'staff_client_registration_blocked') {
            state.authBlocked = 'staff';
            renderStaffGate();
            return;
        }
        page.innerHTML = `<div class="empty-card">${escapeHtml(friendlyError(error))}</div>`;
    });
