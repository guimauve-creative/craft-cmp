# Frontend examples

Copy-paste starting points for consuming Craft CMP from any frontend. These
files are **not** shipped in the distributed Composer package (they're
`export-ignore`d) — they live here on GitHub as reference.

Everything is built on one small, dependency-free core:

| File | What it is |
|---|---|
| [`javascript/cookie-consent-core.js`](javascript/cookie-consent-core.js) | The framework-agnostic `CookieConsentManager`. Fetches config, persists the cookie, drives Google Consent Mode v2, injects per-category provider scripts, and POSTs the audit record. Start here. |
| [`vue/CookieConsentBanner.vue`](vue/CookieConsentBanner.vue) | Self-contained Vue 3 banner + preferences modal + bottom links. |
| [`react/useCookieConsent.js`](react/useCookieConsent.js) · [`react/CookieConsentBanner.jsx`](react/CookieConsentBanner.jsx) | The same UI as a React hook + component. |

## Usage (any framework)

```js
import { CookieConsentManager } from './cookie-consent-core.js';

const cc = new CookieConsentManager({ apiBase: 'https://cms.example.com' });
await cc.loadConfig();
cc.bootstrapConsentMode();            // default-denied → loads GA + granted scripts
if (cc.needsConsent()) showBanner();  // render your own UI
// on click:
cc.acceptAll();                       // or cc.rejectAll() / cc.savePreferences({ analytics: true })
```

Because the core has no framework dependencies, the Vue, React, Svelte and plain
JS integrations all hit the **exact same** REST/GraphQL contract — no plugin
changes required to support a new frontend.

## Notes

- **SSR (Nuxt/Next):** wrap the banner in a client-only boundary (consent is
  browser state), read config via GraphQL (`cookieConsentConfig`) to avoid CORS,
  and POST consent server-to-server so Craft sees a trustworthy IP. Construct the
  manager with `new CookieConsentManager({ config, endpoints: { save: '/api/consent' } })`.
- **Twig sites** don't need any of this — use `craft.cookieConsent.gtagDefaults()`
  + `craft.cookieConsent.banner()` (see the main README).
- **Roadmap:** ready-made Twig/Vue/React/Svelte (and Blade for Craft 6) templates
  plus an optional npm package — see "Future / next on the plate" in the main README.
