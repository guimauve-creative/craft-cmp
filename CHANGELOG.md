# Release Notes for Cookie Consent

## 1.0.0 - 2026-06-19

### Added
- Initial release.
- Consent records stored as a custom element type with a CP index and CSV/JSON export.
- Headless REST API: `POST /cookie-consent/save`, `GET /cookie-consent/status`, `GET /cookie-consent/config`.
- GraphQL queries `cookieConsentConfig` and `cookieConsentStatus`, plus an opt-in `saveCookieConsent` mutation (all schema-scope gated).
- Twig integration: `craft.cookieConsent` variable (`config`, `has`, `currentCategories`, `status`, `needsConsent`, `gtagDefaults`) and a Pro `banner()` renderer with bundled Consent Mode v2 interaction script and an overridable template.
- Four standard categories (necessary, preferences, analytics, marketing) pre-filled with their Google Consent Mode v2 signals; fully editable/deletable.
- Provider-agnostic per-category tags/scripts (external URL and/or inline) that load only when their category is granted — works with any vendor (Meta, Matomo, Hotjar, …). Injected by both the headless JS core and the Twig banner.
- Google Consent Mode integration is now toggleable (`consentModeEnabled`); when off, signals and the GA measurement ID are suppressed.
- CP settings: cookie categories with Google Consent Mode v2 signal mapping, banner copy, policy/consent versions, cookie name/lifetime, record retention, CORS allow-list and optional shared secret.
- `cookie-consent/records/gc` console command for retention-based garbage collection.
- Lite and Pro editions.
