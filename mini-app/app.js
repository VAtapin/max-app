const TELEGRAM_BOT_USERNAME = 'SWProAssistant_bot';
const VK_APP_ID = '54632319';
const OK_APP_ID = '512004501421';

const refInput = document.querySelector('#ref-code');
const connectForm = document.querySelector('#connect-form');
const knownUserPanel = document.querySelector('#known-user');
const codeError = document.querySelector('#code-error');

function query() {
    return new URLSearchParams(window.location.search);
}

function hashQuery() {
    return new URLSearchParams(window.location.hash.replace(/^#/, ''));
}

function routeValue(name) {
    return query().get(name) || hashQuery().get(name) || '';
}

function normalizeReferralCode(value) {
    let code = String(value || '').trim();
    if (code.toLowerCase().startsWith('ref_')) {
        code = code.slice(4);
    }
    return code
        .normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/\s+/g, '-')
        .replace(/[^a-zA-Z0-9_-]/g, '')
        .replace(/[-_]{2,}/g, '-')
        .replace(/^[-_]+|[-_]+$/g, '')
        .toUpperCase();
}

function initialReferralCode() {
    return normalizeReferralCode(
        query().get('ref') ||
        query().get('m') ||
        query().get('startapp') ||
        hashQuery().get('ref') ||
        ''
    );
}

function currentReferralCode() {
    return normalizeReferralCode(refInput.value);
}

function encodedRef() {
    return encodeURIComponent(currentReferralCode());
}

function targetParams() {
    const params = new URLSearchParams();
    const code = currentReferralCode();
    const page = routeValue('page');
    const testId = routeValue('test_id');
    const materialId = routeValue('material_id');
    const linkToken = routeValue('link_token');
    if (code) {
        params.set('ref', code);
    }
    if (page) {
        params.set('page', page);
    }
    if (testId) {
        params.set('test_id', testId);
    }
    if (materialId) {
        params.set('material_id', materialId);
        if (!page) {
            params.set('page', 'home');
        }
    }
    if (linkToken) {
        params.set('link_token', linkToken);
    }
    return params;
}

function appUrl() {
    return `../vk-mini-app/?${targetParams().toString()}`;
}

function hasTelegramContext() {
    const params = query();
    const hash = hashQuery();
    return params.has('tgWebAppData')
        || params.has('tgWebAppVersion')
        || hash.has('tgWebAppData')
        || hash.has('tgWebAppVersion');
}

function hasVkOkContext() {
    const params = query();
    return params.has('vk_app_id') || params.has('vk_user_id') || params.has('vk_ok_user_id');
}

function hasKnownWebUser() {
    return Boolean(localStorage.getItem('swpro_web_user_id'));
}

function hasLinkToken() {
    return routeValue('link_token') !== '';
}

function launchHash() {
    return targetParams().toString();
}

function okQuery() {
    return targetParams().toString();
}

function platformLinks() {
    const ref = encodedRef();
    return {
        telegram: `https://t.me/${encodeURIComponent(TELEGRAM_BOT_USERNAME)}?start=${encodeURIComponent(`ref_${currentReferralCode()}`)}`,
        vk: `https://vk.ru/app${VK_APP_ID}#${launchHash()}`,
        ok: `https://ok.ru/app/${OK_APP_ID}?${okQuery()}`,
        cabinet: appUrl(),
        knownCabinet: appUrl(),
    };
}

function ensureWebIdentity() {
    let webUserId = localStorage.getItem('swpro_web_user_id');
    if (!webUserId) {
        webUserId = `web-${crypto.randomUUID ? crypto.randomUUID() : Date.now()}`;
        localStorage.setItem('swpro_web_user_id', webUserId);
    }
    const code = currentReferralCode();
    if (code) {
        localStorage.setItem('swpro_pending_referral_code', code);
    }
}

function updateLinks() {
    const code = currentReferralCode();
    refInput.value = code;
    if (codeError) {
        codeError.hidden = true;
    }
    const links = platformLinks();

    document.querySelectorAll('[data-link]').forEach((link) => {
        const key = link.dataset.link.replace(/-([a-z])/g, (_, letter) => letter.toUpperCase());
        const requiresReferral = key !== 'knownCabinet' && !(key === 'cabinet' && hasLinkToken());
        const disabled = requiresReferral && !code;
        link.classList.toggle('is-disabled', disabled);
        link.setAttribute('aria-disabled', disabled ? 'true' : 'false');
        if (links[key] && !disabled) {
            link.href = links[key];
        } else if (disabled) {
            link.removeAttribute('href');
        }
    });

    const url = new URL(window.location.href);
    if (code) {
        url.searchParams.set('ref', code);
    } else {
        url.searchParams.delete('ref');
    }
    window.history.replaceState(null, '', url.toString());
}

function showKnownUserShortcut() {
    if (hasKnownWebUser() && knownUserPanel) {
        knownUserPanel.hidden = false;
    }
}

function autoOpenCabinetIfKnown() {
    if (!hasTelegramContext() && !hasVkOkContext() && !hasKnownWebUser() && !hasLinkToken()) {
        return false;
    }
    if (hasKnownWebUser()) {
        const code = currentReferralCode();
        if (code) {
            localStorage.setItem('swpro_pending_referral_code', code);
        }
    }
    window.location.replace(appUrl());
    return true;
}

connectForm.addEventListener('submit', (event) => {
    event.preventDefault();
    updateLinks();
});

document.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) {
        return;
    }
    const platformLink = target.closest('[data-link]');
    const canOpenCabinetByToken = platformLink
        && platformLink.dataset.link === 'cabinet'
        && hasLinkToken();
    if (platformLink && platformLink.dataset.link !== 'known-cabinet' && !canOpenCabinetByToken && !currentReferralCode()) {
        event.preventDefault();
        if (codeError) {
            codeError.hidden = false;
        }
        refInput.focus();
        return;
    }
    if (target.closest('[data-link="cabinet"], [data-link="known-cabinet"]')) {
        ensureWebIdentity();
    }
});

refInput.value = initialReferralCode();
updateLinks();
if (!autoOpenCabinetIfKnown()) {
    showKnownUserShortcut();
}
