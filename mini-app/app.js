const DEFAULT_REFERRAL_CODE = 'SWPRO-START';
const TELEGRAM_BOT_USERNAME = 'SWProAssistant_bot';
const VK_APP_ID = '54632319';
const OK_APP_ID = '512004501421';

const refInput = document.querySelector('#ref-code');
const connectForm = document.querySelector('#connect-form');
const knownUserPanel = document.querySelector('#known-user');

function query() {
    return new URLSearchParams(window.location.search);
}

function hashQuery() {
    return new URLSearchParams(window.location.hash.replace(/^#/, ''));
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
    return normalizeReferralCode(refInput.value) || DEFAULT_REFERRAL_CODE;
}

function encodedRef() {
    return encodeURIComponent(currentReferralCode());
}

function targetParams() {
    const params = new URLSearchParams({ref: currentReferralCode()});
    const page = query().get('page');
    const testId = query().get('test_id');
    if (page) {
        params.set('page', page);
    }
    if (testId) {
        params.set('test_id', testId);
    }
    return params;
}

function appUrl() {
    return `../vk-mini-app/?${targetParams().toString()}`;
}

function hasTelegramContext() {
    return Boolean(window.Telegram && window.Telegram.WebApp && window.Telegram.WebApp.initData);
}

function hasVkOkContext() {
    const params = query();
    return params.has('vk_app_id') || params.has('vk_user_id') || params.has('vk_ok_user_id');
}

function hasKnownWebUser() {
    return Boolean(localStorage.getItem('swpro_web_user_id'));
}

function launchHash() {
    return targetParams().toString();
}

function okQuery() {
    const params = new URLSearchParams({ref: currentReferralCode()});
    const page = query().get('page');
    if (page) {
        params.set('page', page);
    }
    return params.toString();
}

function platformLinks() {
    const ref = encodedRef();
    return {
        telegram: `https://t.me/${encodeURIComponent(TELEGRAM_BOT_USERNAME)}?start=${encodeURIComponent(`ref_${currentReferralCode()}`)}`,
        vk: `https://vk.com/app${VK_APP_ID}#${launchHash()}`,
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
    localStorage.setItem('swpro_pending_referral_code', currentReferralCode());
}

function updateLinks() {
    const code = currentReferralCode();
    refInput.value = code;
    const links = platformLinks();

    document.querySelectorAll('[data-link]').forEach((link) => {
        const key = link.dataset.link.replace(/-([a-z])/g, (_, letter) => letter.toUpperCase());
        if (links[key]) {
            link.href = links[key];
        }
    });

    const url = new URL(window.location.href);
    url.searchParams.set('ref', code);
    window.history.replaceState(null, '', url.toString());
}

function showKnownUserShortcut() {
    if (hasKnownWebUser() && knownUserPanel) {
        knownUserPanel.hidden = false;
    }
}

function autoOpenCabinetIfKnown() {
    if (!hasTelegramContext() && !hasVkOkContext() && !hasKnownWebUser()) {
        return false;
    }
    if (hasKnownWebUser()) {
        localStorage.setItem('swpro_pending_referral_code', currentReferralCode());
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
    if (target.dataset.action === 'default-code') {
        refInput.value = DEFAULT_REFERRAL_CODE;
        updateLinks();
    }
    if (target.closest('[data-link="cabinet"], [data-link="known-cabinet"]')) {
        ensureWebIdentity();
    }
});

refInput.value = initialReferralCode() || DEFAULT_REFERRAL_CODE;
updateLinks();
if (!autoOpenCabinetIfKnown()) {
    showKnownUserShortcut();
}
