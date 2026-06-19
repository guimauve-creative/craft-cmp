/**
 * Cookie Consent — Twig banner interaction script.
 *
 * Progressive enhancement for the server-rendered banner. It does NOT set the
 * Consent Mode defaults — that's done synchronously in <head> by
 * craft.cookieConsent.gtagDefaults(). This only handles clicks: write the
 * cookie, push a gtag `update`, and POST the audit record.
 *
 * Markup contract (data attributes):
 *   [data-cc-banner]              the banner wrapper (shown/hidden)
 *   [data-cc-modal]               the preferences modal wrapper
 *   [data-cc-action="accept-all|reject-all|save|manage|close"]
 *   [data-cc-category="<handle>"] a checkbox input per non-required category
 */
(function () {
    'use strict';

    var cfg = window.__ccConfig || {};
    if (!cfg.categories) return;

    function uuid() {
        if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = (Math.random() * 16) | 0, v = c === 'x' ? r : (r & 0x3) | 0x8;
            return v.toString(16);
        });
    }

    function getCookie(name) {
        var m = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
        return m ? decodeURIComponent(m[1]) : null;
    }

    function setCookie(name, value, days) {
        document.cookie = name + '=' + encodeURIComponent(value) +
            '; Max-Age=' + (days * 86400) + '; Path=/; SameSite=Lax';
    }

    function visitorId() {
        var key = cfg.cookieName + '_vid';
        var id = getCookie(key);
        if (!id) { id = uuid(); setCookie(key, id, 395); }
        return id;
    }

    function buildUpdate(map) {
        // Reuse the head helper if present; otherwise build from cfg.
        if (window.__ccBuildUpdate) return window.__ccBuildUpdate(map);
        var u = {
            ad_storage: 'denied', analytics_storage: 'denied', ad_user_data: 'denied',
            ad_personalization: 'denied', functionality_storage: 'denied',
            personalization_storage: 'denied', security_storage: 'granted',
        };
        cfg.categories.forEach(function (cat) {
            (cat.gtagSignals || []).forEach(function (s) {
                if (s === 'security_storage') return;
                u[s] = map[cat.handle] ? 'granted' : 'denied';
            });
        });
        return u;
    }

    var injected = {};

    function applyScripts(map) {
        (cfg.scripts || []).forEach(function (s, i) {
            var key = s.name || (s.category + ':' + i);
            if (injected[key] || !map[s.category]) return;
            if (s.src) {
                var el = document.createElement('script');
                el.async = true; el.src = s.src; el.setAttribute('data-cc-script', key);
                document.head.appendChild(el);
            }
            if (s.code) {
                var el2 = document.createElement('script');
                el2.textContent = s.code; el2.setAttribute('data-cc-script', key);
                document.head.appendChild(el2);
            }
            injected[key] = true;
        });
    }

    function persist(map, action) {
        // Force required on.
        cfg.categories.forEach(function (c) { if (c.required) map[c.handle] = true; });

        var decision = { v: cfg.policyVersion, c: map, a: action, t: Date.now() };
        setCookie(cfg.cookieName, JSON.stringify(decision), cfg.cookieLifetimeDays || 180);

        if (window.gtag) window.gtag('consent', 'update', buildUpdate(map));
        applyScripts(map);

        if (window.fetch) {
            fetch(cfg.saveUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ visitorId: visitorId(), categories: map, action: action }),
            }).catch(function () { /* never block the UI */ });
        }
    }

    function readSelection(root) {
        var map = {};
        cfg.categories.forEach(function (c) { map[c.handle] = !!c.required; });
        (root || document).querySelectorAll('[data-cc-category]').forEach(function (el) {
            map[el.getAttribute('data-cc-category')] = el.checked;
        });
        return map;
    }

    function show(el, on) { if (el) el.hidden = !on; }

    document.addEventListener('DOMContentLoaded', function () {
        var banner = document.querySelector('[data-cc-banner]');
        var modal = document.querySelector('[data-cc-modal]');

        // Returning visitor: inject scripts for already-granted categories.
        var raw = getCookie(cfg.cookieName);
        if (raw) {
            try {
                var stored = JSON.parse(raw);
                if (stored && stored.c) applyScripts(stored.c);
            } catch (e) { /* ignore */ }
        }

        document.addEventListener('click', function (e) {
            var trigger = e.target.closest('[data-cc-action]');
            if (!trigger) return;
            var action = trigger.getAttribute('data-cc-action');

            if (action === 'accept-all') {
                var all = {}; cfg.categories.forEach(function (c) { all[c.handle] = true; });
                persist(all, 'accept_all'); show(banner, false); show(modal, false);
            } else if (action === 'reject-all') {
                var req = {}; cfg.categories.forEach(function (c) { req[c.handle] = !!c.required; });
                persist(req, 'reject_all'); show(banner, false); show(modal, false);
            } else if (action === 'save') {
                persist(readSelection(modal), 'custom'); show(banner, false); show(modal, false);
            } else if (action === 'manage') {
                show(modal, true);
            } else if (action === 'close') {
                show(modal, false);
            }
        });
    });
})();
