/**
 * Framework-agnostic Cookie Consent core.
 *
 * No framework imports — works in Nuxt, React, Astro, Svelte or plain JS.
 * It does three things:
 *   1. Talks to the Craft CMP plugin (config / save / status) over REST.
 *   2. Persists the visitor's decision in a first-party cookie.
 *   3. Drives Google Consent Mode v2 (default = denied, then update on choice)
 *      and injects per-category provider scripts on consent.
 *
 * UI layers (a Vue banner, a React banner, …) only render and call these methods.
 * Copy this file into your frontend project and import { CookieConsentManager }.
 */

const DEFAULT_DENIED = {
    ad_storage: 'denied',
    analytics_storage: 'denied',
    ad_user_data: 'denied',
    ad_personalization: 'denied',
    functionality_storage: 'denied',
    personalization_storage: 'denied',
    security_storage: 'granted', // security is always granted
};

function uuid() {
    if (typeof crypto !== 'undefined' && crypto.randomUUID) return crypto.randomUUID();
    // RFC4122-ish fallback
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        const v = c === 'x' ? r : (r & 0x3) | 0x8;
        return v.toString(16);
    });
}

/** Minimal document.cookie adapter; override `storage` for SSR frameworks. */
const browserStorage = {
    get(name) {
        if (typeof document === 'undefined') return null;
        const match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : null;
    },
    set(name, value, maxAgeDays) {
        if (typeof document === 'undefined') return;
        const maxAge = maxAgeDays * 24 * 60 * 60;
        document.cookie = `${name}=${encodeURIComponent(value)}; Max-Age=${maxAge}; Path=/; SameSite=Lax`;
    },
};

export class CookieConsentManager {
    /**
     * @param {object} opts
     * @param {string} [opts.apiBase]    Base URL of the Craft site exposing the plugin endpoints.
     * @param {object} [opts.endpoints]  Override individual endpoint URLs ({config, save, status}).
     * @param {object} [opts.config]     Pre-fetched config (e.g. from GraphQL) to skip the REST call.
     * @param {object} [opts.storage]    Cookie adapter ({get, set}); defaults to document.cookie.
     * @param {function} [opts.fetchImpl] Custom fetch (defaults to global fetch).
     * @param {string} [opts.sharedSecret] Optional X-Consent-Secret header value.
     * @param {boolean} [opts.injectScripts] Set false to let the host app own DOM injection.
     */
    constructor(opts = {}) {
        const base = (opts.apiBase || '').replace(/\/$/, '');
        this.endpoints = {
            config: `${base}/cookie-consent/config`,
            save: `${base}/cookie-consent/save`,
            status: `${base}/cookie-consent/status`,
            ...(opts.endpoints || {}),
        };
        this.config = opts.config || null;
        this.storage = opts.storage || browserStorage;
        this.fetchImpl = opts.fetchImpl || (typeof fetch !== 'undefined' ? fetch.bind(globalThis) : null);
        this.sharedSecret = opts.sharedSecret || null;
        // Set false to skip injecting provider scripts on this frontend (e.g. if
        // the host app prefers to own DOM injection). Consent state still syncs.
        this.injectScripts = opts.injectScripts !== false;
        this._injected = new Set();
        this.decision = this._readCookie();
    }

    // --- Config -------------------------------------------------------------

    async loadConfig() {
        if (this.config) return this.config;
        const res = await this.fetchImpl(this.endpoints.config, { headers: { Accept: 'application/json' } });
        this.config = await res.json();
        return this.config;
    }

    get cookieName() {
        return this.config?.cookie?.name || 'cc_consent';
    }

    get cookieLifetimeDays() {
        return this.config?.cookie?.lifetimeDays || 180;
    }

    get policyVersion() {
        return this.config?.policyVersion || '1';
    }

    // --- Decision state -----------------------------------------------------

    /** Whether the banner should be shown (no decision yet, or policy changed). */
    needsConsent() {
        if (!this.decision) return true;
        return this.decision.v !== this.policyVersion;
    }

    /** Current category → bool map (falls back to required-only). */
    currentCategories() {
        if (this.decision?.c) return this.decision.c;
        return this.defaultCategories();
    }

    /** Required categories granted, everything else denied. */
    defaultCategories() {
        const map = {};
        (this.config?.categories || []).forEach((cat) => {
            map[cat.handle] = !!cat.required;
        });
        return map;
    }

    visitorId() {
        const key = `${this.cookieName}_vid`;
        let id = this.storage.get(key);
        if (!id) {
            id = uuid();
            this.storage.set(key, id, 395);
        }
        return id;
    }

    // --- Google Consent Mode v2 --------------------------------------------

    /** Build a Consent Mode update object from a category map + the config mapping. */
    buildConsentUpdate(categories) {
        const update = { ...DEFAULT_DENIED };
        (this.config?.categories || []).forEach((cat) => {
            const granted = !!categories[cat.handle];
            (cat.gtagSignals || []).forEach((signal) => {
                // security_storage stays granted regardless.
                if (signal === 'security_storage') return;
                update[signal] = granted ? 'granted' : 'denied';
            });
        });
        return update;
    }

    /**
     * Push the default-denied consent state and load gtag.js.
     * MUST run before any GA hits — call it as early as possible.
     */
    bootstrapConsentMode(gtagId = this.config?.gaMeasurementId) {
        if (typeof window === 'undefined') return;

        // Google Consent Mode is optional — skip the gtag wiring when disabled,
        // but still re-apply stored consent (which also injects gated scripts).
        if (this.config?.consentMode !== false) {
            window.dataLayer = window.dataLayer || [];
            if (!window.gtag) {
                window.gtag = function () { window.dataLayer.push(arguments); };
            }

            window.gtag('consent', 'default', { ...DEFAULT_DENIED, wait_for_update: 500 });
            window.gtag('set', 'url_passthrough', true);
            window.gtag('set', 'ads_data_redaction', true);
        }

        // Re-apply a prior decision immediately (no flicker on revisit).
        this.applyStored();

        if (this.config?.consentMode !== false && gtagId) {
            const s = document.createElement('script');
            s.async = true;
            s.src = `https://www.googletagmanager.com/gtag/js?id=${gtagId}`;
            document.head.appendChild(s);
            window.gtag('js', new Date());
            window.gtag('config', gtagId);
        }
    }

    /** Re-apply a stored decision: emit a gtag `update` and inject gated scripts. */
    applyStored() {
        if (!this.decision?.c || typeof window === 'undefined') return;
        if (window.gtag) {
            window.gtag('consent', 'update', this.buildConsentUpdate(this.decision.c));
        }
        this.applyScripts(this.decision.c);
    }

    /**
     * Inject provider scripts (Meta Pixel, Matomo, Hotjar, …) for every granted
     * category. Each script loads at most once. Safe to call repeatedly.
     */
    applyScripts(categories) {
        if (!this.injectScripts || typeof document === 'undefined') return;

        (this.config?.scripts || []).forEach((script, i) => {
            const key = script.name || `${script.category}:${i}`;
            if (this._injected.has(key)) return;
            if (!categories[script.category]) return;

            if (script.src) {
                const el = document.createElement('script');
                el.async = true;
                el.src = script.src;
                el.setAttribute('data-cc-script', key);
                document.head.appendChild(el);
            }
            if (script.code) {
                // Appending a <script> with textContent executes without eval
                // (CSP needs script-src, but not unsafe-eval).
                const el = document.createElement('script');
                el.textContent = script.code;
                el.setAttribute('data-cc-script', key);
                document.head.appendChild(el);
            }
            this._injected.add(key);
        });
    }

    // --- Actions ------------------------------------------------------------

    acceptAll() {
        const map = {};
        (this.config?.categories || []).forEach((cat) => { map[cat.handle] = true; });
        return this.save(map, 'accept_all');
    }

    rejectAll() {
        const map = {};
        (this.config?.categories || []).forEach((cat) => { map[cat.handle] = !!cat.required; });
        return this.save(map, 'reject_all');
    }

    savePreferences(categories) {
        return this.save(categories, 'custom');
    }

    /** Persist locally + update gtag + inject scripts + POST to the plugin. */
    async save(categories, action = 'custom') {
        // Always force required categories on.
        (this.config?.categories || []).forEach((cat) => {
            if (cat.required) categories[cat.handle] = true;
        });

        this.decision = { v: this.policyVersion, c: categories, a: action, t: Date.now() };
        this._writeCookie(this.decision);

        if (typeof window !== 'undefined' && window.gtag) {
            window.gtag('consent', 'update', this.buildConsentUpdate(categories));
        }
        this.applyScripts(categories);

        await this._post(categories, action);
        return this.decision;
    }

    // --- Transport / persistence -------------------------------------------

    async _post(categories, action) {
        if (!this.fetchImpl) return;
        const headers = { 'Content-Type': 'application/json', Accept: 'application/json' };
        if (this.sharedSecret) headers['X-Consent-Secret'] = this.sharedSecret;

        try {
            await this.fetchImpl(this.endpoints.save, {
                method: 'POST',
                headers,
                credentials: 'omit',
                body: JSON.stringify({
                    visitorId: this.visitorId(),
                    categories,
                    action,
                    locale: this.config?.locale || undefined,
                }),
            });
        } catch (e) {
            // Saving the audit record must never block the UI.
            if (typeof console !== 'undefined') console.warn('[cookie-consent] save failed', e);
        }
    }

    _readCookie() {
        const raw = this.storage.get(this.cookieName);
        if (!raw) return null;
        try { return JSON.parse(raw); } catch { return null; }
    }

    _writeCookie(decision) {
        this.storage.set(this.cookieName, JSON.stringify(decision), this.cookieLifetimeDays);
    }
}

export default CookieConsentManager;
