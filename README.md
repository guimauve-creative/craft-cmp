# Craft CMP — Consent Management Platform

A **headless-first** consent management platform (CMP) for Craft CMS 5. It stores
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
- **Framework-agnostic** — a dependency-free vanilla-JS core works with any
  frontend; ready-to-copy Vue and React examples in [`examples/`](examples/).
- **Self-contained & configurable** — categories, copy, versions, cookie
  name/lifetime, retention, CORS and a shared secret, all in plugin settings.

## Requirements

- Craft CMS 5.0.0+
- PHP 8.2+

## Installation

```bash
composer require guimauve/craft-cmp
php craft plugin/install craft-cmp
```

Then open **Craft CMP → Settings** in the Control Panel and define your categories.

## Control Panel setup

1. **Categories** — add a row per category. `Handle` is the stable key your
   frontend uses (e.g. `analytics`). Toggle `Required` for strictly-necessary
   cookies. `Consent Mode signals` is a comma-separated list of the Google
   signals the category maps to (see the table below).
2. **Banner copy** — title, body (HTML allowed) and button labels. These are
   translatable via Craft's static translations / `site` category.
3. **Banner links** — small links shown at the bottom of the banner (e.g.
   Privacy policy). Each link has a label, a URL, and an optional
   open-in-new-tab toggle.
4. **Policy version** — bump this string whenever your cookie policy changes;
   every visitor will be re-prompted automatically.
5. **GA4 measurement ID** — optional. If set, the frontend core can load
   `gtag.js` for you. Supports environment variables (`$GA_MEASUREMENT_ID`).
6. **Headless API security** — add your frontend origin(s) to the CORS
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

`links` are meant to render as small links at the bottom of the banner.

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

**2. Render the banner** anywhere in your layout. It ships with a small bundled
script that writes the cookie, updates `gtag`, and POSTs the audit record to
`/cookie-consent/save`:

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

## Frontend integration (headless)

Craft CMP ships no frontend assets for headless use — a small, dependency-free
core does the work and your UI just renders. Copy-paste starting points live in
[`examples/`](examples/) (these are featured here but **not** part of the
installed Composer package):

- **[`examples/javascript/cookie-consent-core.js`](examples/javascript/cookie-consent-core.js)**
  — the framework-agnostic `CookieConsentManager`: fetches config, persists the
  cookie, drives Google Consent Mode v2, injects per-category provider scripts,
  and POSTs the audit record. **Start here.**
- **[Vue banner](examples/vue/CookieConsentBanner.vue)** and
  **[React hook + banner](examples/react/CookieConsentBanner.jsx)** — the same UI
  on top of the core. Svelte / plain JS work identically.

```js
import { CookieConsentManager } from './cookie-consent-core.js';

const cc = new CookieConsentManager({ apiBase: 'https://cms.example.com' });
await cc.loadConfig();
cc.bootstrapConsentMode();            // default-denied → loads GA + granted scripts
if (cc.needsConsent()) showBanner();  // render your own UI
cc.acceptAll();                       // or rejectAll() / savePreferences({ analytics: true })
```

Because the core has no framework dependencies, every frontend hits the **exact
same** REST/GraphQL contract — no plugin changes to support a new framework.

### SSR (Nuxt / Next)

Wrap the banner in a client-only boundary (consent is browser state), read
config via GraphQL (`cookieConsentConfig`) to avoid CORS, and POST consent
**server-to-server** so Craft logs a trustworthy IP — construct the manager with
`new CookieConsentManager({ config, endpoints: { save: '/api/consent' } })` and
proxy `/api/consent` to `/cookie-consent/save`. See the SSR notes in
[`examples/README.md`](examples/README.md).

---

## What's included

One paid license, every feature unlocked — no tiers:

- Headless REST API (`save` / `status` / `config`)
- GraphQL config + status queries and the `saveCookieConsent` mutation
- Twig integration (`craft.cookieConsent` variable + rendered `banner()`)
- Google Consent Mode v2 signal mapping
- Per-category tags/scripts for any provider (Meta, Matomo, Hotjar, …)
- CP consent records index with CSV / JSON export
- Retention garbage collection
- Per-user permissions

## Retention / garbage collection

Set **Record retention (days)** in settings, then run on a schedule (cron):

```bash
php craft craft-cmp/records/gc
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

## Credits

- Cookie icon by [Gregor Cresnar](https://thenounproject.com/creator/grega.cresnar)
  via the Noun Project (licensed).
