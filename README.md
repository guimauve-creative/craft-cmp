# Cookie Consent for Craft CMS

A **headless-first** cookie consent / CMP for Craft CMS 5. It stores
proof-of-consent records in your database and exposes a clean **REST + GraphQL
API** so any decoupled frontend (Nuxt, React, Astro, plain JS) can read the
banner configuration, save a visitor's decision, and check their stored consent.
It's built for **Google Consent Mode v2**: the plugin supplies the category →
signal mapping, your frontend wires `gtag`.

It works **both ways**: drive your own decoupled UI through the API, or render a
ready-made banner on a traditional Craft site with the bundled `craft.cookieConsent`
Twig integration. Either way, the plugin owns the data, config and consent records.

## Why this plugin

- **Headless API** — REST (`/cookie-consent/save|status|config`) and GraphQL
  (`cookieConsentConfig`, `cookieConsentStatus`, opt-in `saveCookieConsent`).
- **Twig too** — `craft.cookieConsent` variable + an overridable rendered banner
  for traditional (non-headless) Craft sites.
- **Proof of consent** — every decision is an immutable element record with a CP
  index, search, filtering and CSV/JSON export for GDPR / Québec Law 25 audits.
- **Consent Mode v2 + any provider** — map categories to the seven Google consent
  signals, and/or gate per-category scripts (Meta, Matomo, Hotjar, …) that load
  only on consent.
- **Framework-agnostic** — ship the included vanilla-JS core to any frontend; a
  Vue and a React example are below.
- **Self-contained & configurable** — categories, copy, versions, cookie
  name/lifetime, retention, CORS and a shared secret, all in plugin settings.

## Requirements

- Craft CMS 5.0.0+
- PHP 8.2+

## Installation

```bash
composer require guimauve/craft-cmp
php craft plugin/install cookie-consent
```

Then open **Settings → Cookie Consent** and define your categories.

## Control Panel setup

1. **Categories** — add a row per category. `Handle` is the stable key your
   frontend uses (e.g. `analytics`). Toggle `Required` for strictly-necessary
   cookies. `Consent Mode signals` is a comma-separated list of the Google
   signals the category maps to (see the table below).
2. **Banner copy** — title, body (HTML allowed) and button labels. These are
   translatable via Craft's static translations / `site` category.
3. **Banner links** — small links shown at the bottom of the banner (e.g.
   Privacy policy). Each link has a label and points to an **entry** (resolved to
   its URL for the requested locale) or a manual **URL**, with an optional
   open-in-new-tab toggle.
4. **Policy version** — bump this string whenever your cookie policy changes;
   every visitor will be re-prompted automatically.
4. **GA4 measurement ID** — optional. If set, the frontend core can load
   `gtag.js` for you. Supports environment variables (`$GA_MEASUREMENT_ID`).
5. **Headless API security** — add your frontend origin(s) to the CORS
   allow-list, and optionally set a shared secret for write requests.

A sensible starter set of categories:

| Handle | Required | Consent Mode signals |
|---|---|---|
| `necessary` | ✔ | `security_storage` |
| `analytics` | | `analytics_storage` |
| `marketing` | | `ad_storage, ad_user_data, ad_personalization` |
| `preferences` | | `functionality_storage, personalization_storage` |

## REST API

All endpoints are anonymous and live at your site's root.

### `GET /cookie-consent/config`

Returns the banner config and category/signal mapping.

```jsonc
{
  "consentVersion": "1",
  "policyVersion": "1",
  "consentMode": true,
  "gaMeasurementId": "G-XXXXXXX",
  "cookie": { "name": "cc_consent", "lifetimeDays": 180 },
  "banner": { "title": "…", "body": "…", "acceptAllLabel": "Accept all", "rejectAllLabel": "Reject all", "savePrefsLabel": "Save preferences", "managePrefsLabel": "Manage preferences" },
  "categories": [
    { "handle": "necessary", "label": "Strictly necessary", "description": "…", "required": true, "gtagSignals": ["security_storage"] },
    { "handle": "analytics", "label": "Analytics", "description": "…", "required": false, "gtagSignals": ["analytics_storage"] }
  ],
  "links": [
    { "label": "Privacy policy", "url": "https://www.example.com/privacy", "newTab": false }
  ],
  "scripts": [
    { "name": "Meta Pixel", "category": "marketing", "src": "https://connect.facebook.net/en_US/fbevents.js", "code": "fbq('init','123');" }
  ]
}
```

`links` are resolved server-side (an entry's URL for the requested locale, or a
manual URL) and are meant to render as small links at the bottom of the banner.

### `POST /cookie-consent/save`

Body:

```json
{ "visitorId": "f47ac10b-58cc-4372-a567-0e02b2c3d479", "categories": { "necessary": true, "analytics": true, "marketing": false }, "action": "custom", "locale": "fr-CA" }
```

`action` is one of `accept_all | reject_all | custom | withdraw`. Returns `201`
with `{ ok, id, consentVersion, policyVersion, dateCreated }`. The plugin stamps
the versions, IP and user-agent **server-side** — the client cannot spoof them.

### `GET /cookie-consent/status?visitorId=…`

Returns the latest stored decision and `needsRefresh` (true when the stored
policy version differs from the current one).

## GraphQL API

Enable the scopes under **Settings → GraphQL → Schemas → (your schema)**:
*Read cookie consent configuration*, *Read cookie consent records*, and — only if
you want the mutation — *Save cookie consent records*.

```graphql
query ($locale: String) {
  cookieConsentConfig(locale: $locale) {
    policyVersion
    consentMode
    gaMeasurementId
    cookieName
    cookieLifetimeDays
    bannerTitle
    bannerBody
    acceptAllLabel
    rejectAllLabel
    savePrefsLabel
    managePrefsLabel
    categories { handle label description required gtagSignals }
    links { label url newTab }
    scripts { name category src code }
  }
}

query ($visitorId: String!) {
  cookieConsentStatus(visitorId: $visitorId) {
    found
    action
    policyVersion
    needsRefresh
    categories { handle granted }
  }
}

# Opt-in mutation (categories is a JSON object string):
mutation ($visitorId: String!, $categories: String!) {
  saveCookieConsent(visitorId: $visitorId, categories: $categories, action: "custom") {
    id
    policyVersion
  }
}
```

> **Reads vs writes.** GraphQL queries are first-class. For writes we recommend
> the REST endpoint: it's anonymous out of the box, whereas a public GraphQL
> mutation requires enabling a write scope on your public schema. The mutation is
> provided for setups that prefer a single GraphQL pipeline.

## CORS, CSRF & security

- **Reads** from the browser are simplest over GraphQL (same-origin via your
  frontend's API proxy) or by adding your origin to the CORS allow-list.
- **Writes**: post **server-to-server** from your frontend's backend (e.g. a
  Nuxt/Next route) to avoid browser CORS/CSRF entirely and capture a trustworthy
  IP. CSRF is disabled on `save`; authenticity is enforced via the Origin
  allow-list and the optional `X-Consent-Secret` shared-secret header.

## Works with any provider — two activation models

Cookie categories are the standard GDPR / Law 25 taxonomy and are **not tied to
Google**. A category grant can drive two independent mechanisms, and the plugin
supports both:

1. **Google Consent Mode v2 (optional).** The Google tag always loads but obeys
   `gtag('consent', …)` signals — pre-consent it runs in cookieless/modeling
   mode. Configure the per-category signals + GA4 ID, or toggle the whole
   integration off under **Settings → Google Consent Mode v2** if you don't use
   Google.

2. **Per-category tags/scripts (any provider).** Under **Settings → Tags &
   scripts**, attach a script to a category by external URL, inline code, or
   both. It loads **only when that category is granted** — the standard way to
   gate Meta Pixel, Matomo, Hotjar, LinkedIn, TikTok, etc. that don't understand
   Consent Mode. These are exposed in `config.scripts` (REST/GraphQL) and the
   frontend core/Twig banner inject them on grant (once each).

> Tags run third-party code on your front-end, so only an admin can add them
> (same trust model as GTM Custom HTML / SEOmatic script fields). Injection
> appends a `<script>` element rather than `eval`, so your CSP needs `script-src`
> for the source but not `unsafe-eval`.

## Google Consent Mode v2

Consent Mode requires you to push a **default = denied** state *before* GA loads,
then **update** it once the visitor chooses. This plugin doesn't render any JS —
it hands your frontend the mapping and your frontend drives `gtag`. The mapping
covers all seven signals:

| Category (example) | Consent Mode v2 signals | Default |
|---|---|---|
| necessary (required) | `security_storage` | granted |
| analytics | `analytics_storage` | denied |
| marketing | `ad_storage`, `ad_user_data`, `ad_personalization` | denied |
| preferences | `functionality_storage`, `personalization_storage` | denied |

---

## Twig usage (traditional, non-headless sites)

If your Craft site renders its own front-end with Twig, you don't need the JS
core — the plugin exposes a `craft.cookieConsent` variable and can render a
ready-made banner.

**1. In your `<head>`, before any analytics**, emit the Consent Mode v2 defaults.
This also re-applies a returning visitor's stored choice (no flicker) and, if a
GA4 measurement ID is set, loads `gtag.js`:

```twig
{{ craft.cookieConsent.gtagDefaults() }}
```

**2. Render the banner** anywhere in your layout (Pro edition). It ships with a
small bundled script that writes the cookie, updates `gtag`, and POSTs the audit
record to `/cookie-consent/save`:

```twig
{{ craft.cookieConsent.banner() }}
```

Override the markup by passing your own template path (it receives `config`,
`current` and `needsConsent`):

```twig
{{ craft.cookieConsent.banner('_partials/cookie-banner') }}
```

**3. Gate scripts server-side** on the visitor's decision (cookie-first, with a
DB fallback by visitor id):

```twig
{% if craft.cookieConsent.has('analytics') %}
  {# only rendered once analytics consent is granted #}
{% endif %}

{% set status = craft.cookieConsent.status() %}      {# latest decision, or null #}
{% set cfg = craft.cookieConsent.config() %}          {# same payload as the REST/GraphQL config #}
```

Available methods: `config(locale)`, `has(handle, visitorId?)`,
`currentCategories(visitorId?)`, `status(visitorId?)`, `needsConsent(visitorId?)`,
`gtagDefaults(loadGtag = true)`, `banner(template?, vars?)`.

> The headless API and the Twig layer share the same service and records, so you
> can even mix them (e.g. render with Twig but read consent over GraphQL).

---

## Frontend: framework-agnostic core

Drop this `CookieConsentManager` into any project. It has **no framework
imports**. It fetches config, persists the decision in a first-party cookie,
drives Consent Mode v2, and POSTs the audit record.

```js
// cookie-consent-core.js
const DEFAULT_DENIED = {
  ad_storage: 'denied', analytics_storage: 'denied', ad_user_data: 'denied',
  ad_personalization: 'denied', functionality_storage: 'denied',
  personalization_storage: 'denied', security_storage: 'granted',
};

function uuid() {
  if (typeof crypto !== 'undefined' && crypto.randomUUID) return crypto.randomUUID();
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0; const v = c === 'x' ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}

const browserStorage = {
  get(name) {
    if (typeof document === 'undefined') return null;
    const m = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
    return m ? decodeURIComponent(m[1]) : null;
  },
  set(name, value, maxAgeDays) {
    if (typeof document === 'undefined') return;
    document.cookie = `${name}=${encodeURIComponent(value)}; Max-Age=${maxAgeDays * 86400}; Path=/; SameSite=Lax`;
  },
};

export class CookieConsentManager {
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
    this.decision = this._readCookie();
  }

  async loadConfig() {
    if (this.config) return this.config;
    const res = await this.fetchImpl(this.endpoints.config, { headers: { Accept: 'application/json' } });
    this.config = await res.json();
    return this.config;
  }

  get cookieName() { return this.config?.cookie?.name || 'cc_consent'; }
  get cookieLifetimeDays() { return this.config?.cookie?.lifetimeDays || 180; }
  get policyVersion() { return this.config?.policyVersion || '1'; }

  needsConsent() { return !this.decision || this.decision.v !== this.policyVersion; }

  currentCategories() { return this.decision?.c || this.defaultCategories(); }
  defaultCategories() {
    const map = {};
    (this.config?.categories || []).forEach((c) => { map[c.handle] = !!c.required; });
    return map;
  }

  visitorId() {
    const key = `${this.cookieName}_vid`;
    let id = this.storage.get(key);
    if (!id) { id = uuid(); this.storage.set(key, id, 395); }
    return id;
  }

  buildConsentUpdate(categories) {
    const update = { ...DEFAULT_DENIED };
    (this.config?.categories || []).forEach((cat) => {
      const granted = !!categories[cat.handle];
      (cat.gtagSignals || []).forEach((sig) => {
        if (sig === 'security_storage') return;
        update[sig] = granted ? 'granted' : 'denied';
      });
    });
    return update;
  }

  bootstrapConsentMode(gtagId = this.config?.gaMeasurementId) {
    if (typeof window === 'undefined') return;
    window.dataLayer = window.dataLayer || [];
    if (!window.gtag) window.gtag = function () { window.dataLayer.push(arguments); };
    window.gtag('consent', 'default', { ...DEFAULT_DENIED, wait_for_update: 500 });
    window.gtag('set', 'url_passthrough', true);
    window.gtag('set', 'ads_data_redaction', true);
    this.applyStored();
    if (gtagId) {
      const s = document.createElement('script');
      s.async = true; s.src = `https://www.googletagmanager.com/gtag/js?id=${gtagId}`;
      document.head.appendChild(s);
      window.gtag('js', new Date());
      window.gtag('config', gtagId);
    }
  }

  applyStored() {
    if (!this.decision?.c || typeof window === 'undefined' || !window.gtag) return;
    window.gtag('consent', 'update', this.buildConsentUpdate(this.decision.c));
  }

  acceptAll() {
    const map = {}; (this.config?.categories || []).forEach((c) => { map[c.handle] = true; });
    return this.save(map, 'accept_all');
  }
  rejectAll() {
    const map = {}; (this.config?.categories || []).forEach((c) => { map[c.handle] = !!c.required; });
    return this.save(map, 'reject_all');
  }
  savePreferences(categories) { return this.save(categories, 'custom'); }

  async save(categories, action = 'custom') {
    (this.config?.categories || []).forEach((c) => { if (c.required) categories[c.handle] = true; });
    this.decision = { v: this.policyVersion, c: categories, a: action, t: Date.now() };
    this._writeCookie(this.decision);
    if (typeof window !== 'undefined' && window.gtag) {
      window.gtag('consent', 'update', this.buildConsentUpdate(categories));
    }
    await this._post(categories, action);
    return this.decision;
  }

  async _post(categories, action) {
    if (!this.fetchImpl) return;
    const headers = { 'Content-Type': 'application/json', Accept: 'application/json' };
    if (this.sharedSecret) headers['X-Consent-Secret'] = this.sharedSecret;
    try {
      await this.fetchImpl(this.endpoints.save, {
        method: 'POST', headers, credentials: 'omit',
        body: JSON.stringify({ visitorId: this.visitorId(), categories, action }),
      });
    } catch (e) { /* never block the UI on the audit write */ }
  }

  _readCookie() {
    const raw = this.storage.get(this.cookieName);
    if (!raw) return null;
    try { return JSON.parse(raw); } catch { return null; }
  }
  _writeCookie(d) { this.storage.set(this.cookieName, JSON.stringify(d), this.cookieLifetimeDays); }
}
```

> The snippet above is abridged for readability. The canonical core ships in the
> repo and additionally **injects per-category provider scripts** (`config.scripts`)
> via an `applyScripts()` method called from `bootstrapConsentMode`, `applyStored`
> and `save`, and **skips the gtag wiring when `config.consentMode === false`**.
> Pass `{ injectScripts: false }` to the constructor if your app prefers to own
> DOM injection of those scripts. Use that file as-is.

### How you use it (any framework)

```js
const cc = new CookieConsentManager({ apiBase: 'https://cms.example.com' });
await cc.loadConfig();
cc.bootstrapConsentMode();          // default-denied → load GA + inject granted scripts
if (cc.needsConsent()) showBanner(); // render your own UI
// on click:
cc.acceptAll();                      // or cc.rejectAll() / cc.savePreferences({analytics:true})
```

---

## Demo: Vue component

A self-contained banner + preferences modal. The component only renders and
calls the core — swap the framework, keep the logic.

```vue
<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { CookieConsentManager } from './cookie-consent-core.js';

const props = defineProps({ apiBase: { type: String, default: '' } });

const cc = new CookieConsentManager({ apiBase: props.apiBase });
const config = ref(null);
const open = ref(false);
const showPrefs = ref(false);
const selection = reactive({});

const categories = computed(() => config.value?.categories ?? []);

onMounted(async () => {
  config.value = await cc.loadConfig();
  cc.bootstrapConsentMode();                 // Consent Mode v2: default-denied → GA
  Object.assign(selection, cc.currentCategories());
  open.value = cc.needsConsent();
});

function acceptAll() { cc.acceptAll(); open.value = showPrefs.value = false; }
function rejectAll() { cc.rejectAll(); open.value = showPrefs.value = false; }
function savePrefs() { cc.savePreferences({ ...selection }); open.value = showPrefs.value = false; }
function manage() { Object.assign(selection, cc.currentCategories()); showPrefs.value = true; }

// expose manage() so a footer link can re-open preferences:
defineExpose({ manage });
</script>

<template>
  <section v-if="open && config" class="cc-banner" role="dialog" :aria-label="config.banner.title">
    <div>
      <h2>{{ config.banner.title }}</h2>
      <div v-html="config.banner.body" />
    </div>
    <div class="cc-actions">
      <button @click="manage">{{ config.banner.managePrefsLabel }}</button>
      <button @click="rejectAll">{{ config.banner.rejectAllLabel }}</button>
      <button @click="acceptAll">{{ config.banner.acceptAllLabel }}</button>
    </div>
    <ul v-if="config.links?.length" class="cc-links">
      <li v-for="(link, i) in config.links" :key="i">
        <a :href="link.url" :target="link.newTab ? '_blank' : null" :rel="link.newTab ? 'noopener noreferrer' : null">{{ link.label }}</a>
      </li>
    </ul>
  </section>

  <div v-if="showPrefs" class="cc-modal" role="dialog" aria-modal="true">
    <div class="cc-modal__box">
      <h2>{{ config.banner.managePrefsLabel }}</h2>
      <label v-for="cat in categories" :key="cat.handle" class="cc-row">
        <span>
          <strong>{{ cat.label }}</strong>
          <small>{{ cat.description }}</small>
        </span>
        <input type="checkbox" :disabled="cat.required"
               :checked="cat.required ? true : !!selection[cat.handle]"
               @change="selection[cat.handle] = $event.target.checked">
      </label>
      <div class="cc-actions">
        <button @click="showPrefs = false">Cancel</button>
        <button @click="savePrefs">{{ config.banner.savePrefsLabel }}</button>
      </div>
    </div>
  </div>
</template>
```

### Nuxt 3 (SSR) notes

For Nuxt, wrap the banner in `<ClientOnly>` (consent is browser state), read the
config through GraphQL (`cookieConsentConfig`) to avoid CORS, and POST consent
**server-to-server** through a Nitro route so Craft sees the real client IP:

```js
// server/api/consent.post.js
export default defineEventHandler(async (event) => {
  const body = await readBody(event);
  return await $fetch(`${process.env.CMS_INTERNAL_URL}/cookie-consent/save`, {
    method: 'POST',
    headers: {
      'X-Forwarded-For': getRequestHeader(event, 'x-forwarded-for') || getRequestIP(event) || '',
      ...(process.env.CONSENT_SHARED_SECRET ? { 'X-Consent-Secret': process.env.CONSENT_SHARED_SECRET } : {}),
    },
    body,
  });
});
```

Then construct the manager with `new CookieConsentManager({ config, endpoints: { save: '/api/consent' } })`.

---

## Adapting to React

Same core, a thin hook + component. Nothing about the core changes.

```jsx
// useCookieConsent.js
import { useEffect, useRef, useState, useCallback } from 'react';
import { CookieConsentManager } from './cookie-consent-core.js';

export function useCookieConsent(apiBase = '') {
  const cc = useRef(null);
  const [config, setConfig] = useState(null);
  const [open, setOpen] = useState(false);
  const [selection, setSelection] = useState({});

  useEffect(() => {
    cc.current = new CookieConsentManager({ apiBase });
    (async () => {
      const cfg = await cc.current.loadConfig();
      cc.current.bootstrapConsentMode();              // default-denied → GA
      setConfig(cfg);
      setSelection(cc.current.currentCategories());
      setOpen(cc.current.needsConsent());
    })();
  }, [apiBase]);

  const acceptAll = useCallback(() => { cc.current.acceptAll(); setOpen(false); }, []);
  const rejectAll = useCallback(() => { cc.current.rejectAll(); setOpen(false); }, []);
  const savePrefs = useCallback((sel) => { cc.current.savePreferences(sel); setOpen(false); }, []);
  const manage = useCallback(() => { setSelection(cc.current.currentCategories()); setOpen(true); }, []);

  return { config, open, selection, setSelection, acceptAll, rejectAll, savePrefs, manage };
}
```

```jsx
// CookieConsentBanner.jsx
import { useCookieConsent } from './useCookieConsent.js';

export function CookieConsentBanner({ apiBase }) {
  const { config, open, selection, setSelection, acceptAll, rejectAll, savePrefs } = useCookieConsent(apiBase);
  if (!open || !config) return null;

  return (
    <section className="cc-banner" role="dialog" aria-label={config.banner.title}>
      <div>
        <h2>{config.banner.title}</h2>
        <div dangerouslySetInnerHTML={{ __html: config.banner.body }} />
      </div>
      <div className="cc-actions">
        <button onClick={rejectAll}>{config.banner.rejectAllLabel}</button>
        <button onClick={acceptAll}>{config.banner.acceptAllLabel}</button>
        {config.categories.filter((c) => !c.required).map((c) => (
          <label key={c.handle}>
            <input type="checkbox" checked={!!selection[c.handle]}
              onChange={(e) => setSelection({ ...selection, [c.handle]: e.target.checked })} />
            {c.label}
          </label>
        ))}
        <button onClick={() => savePrefs(selection)}>{config.banner.savePrefsLabel}</button>
      </div>
      {config.links?.length > 0 && (
        <ul className="cc-links">
          {config.links.map((link, i) => (
            <li key={i}>
              <a href={link.url} target={link.newTab ? '_blank' : undefined} rel={link.newTab ? 'noopener noreferrer' : undefined}>{link.label}</a>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}
```

Because the core has no framework dependencies, the React, Vue, Svelte and plain
JS integrations all hit the **exact same** REST/GraphQL contract — no plugin
changes required to support a new frontend.

---

## Editions

| Feature | Lite | Pro |
|---|---|---|
| Banner config + REST `save`/`config` | ✔ | ✔ |
| GraphQL config + status queries | ✔ | ✔ |
| Twig variable (`config`, `has`, `status`, `gtagDefaults`) | ✔ | ✔ |
| Google Consent Mode v2 signals | ✔ | ✔ |
| Per-category tags/scripts (any provider) | ✔ | ✔ |
| Rendered Twig banner (`craft.cookieConsent.banner()`) | | ✔ |
| CP consent records index | | ✔ |
| CSV / JSON export | | ✔ |
| Retention garbage collection | | ✔ |
| GraphQL `saveCookieConsent` mutation | | ✔ |
| Per-user permissions | | ✔ |

## Retention / garbage collection

Set **Record retention (days)** in settings, then run on a schedule (cron):

```bash
php craft cookie-consent/records/gc
```

## Future / next on the plate

The plugin already ships the framework-agnostic core plus Twig, Vue and React
examples in this README. Planned next:

- **Ready-made front-end templates** — copy-paste banner + preferences components
  for **Twig, Vue, React and Svelte** (and **Blade** once Craft 6 ships), each
  built on the same config/API contract.
- **Optional npm package** — install the components/composable directly (e.g.
  `@guimauve/cookie-consent`) and drop them in, instead of copying the core into
  your project.
- Continued refinement of the Consent Mode v2 mapping and provider script presets.

Have a request? Open an issue on the repository.

## Support

Open an issue at https://github.com/guimauve-creative/craft-cmp/issues or email
info@guimauvecreative.com.
