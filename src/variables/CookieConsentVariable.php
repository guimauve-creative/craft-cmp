<?php

namespace guimauve\cookieconsent\variables;

use Craft;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\web\View;
use guimauve\cookieconsent\CookieConsent;
use guimauve\cookieconsent\web\assets\CookieConsentAsset;
use Twig\Markup;

/**
 * Twig API: `craft.cookieConsent.*`
 *
 * Lets traditional (non-headless) Craft sites render a banner and gate scripts
 * on consent server-side, on top of the same service/records the headless API uses.
 */
class CookieConsentVariable
{
    /**
     * The public consent configuration (categories, copy, signal mapping).
     *
     * @return array<string,mixed>
     */
    public function config(?string $locale = null): array
    {
        return CookieConsent::$plugin->config->getPublicConfig($locale);
    }

    /**
     * Whether a category is granted for the current visitor.
     * Reads the consent cookie first, falling back to the DB by visitorId.
     */
    public function has(string $handle, ?string $visitorId = null): bool
    {
        $categories = $this->currentCategories($visitorId);
        return !empty($categories[$handle]);
    }

    /**
     * The current visitor's category map (cookie-first, DB fallback, then defaults).
     *
     * @return array<string,bool>
     */
    public function currentCategories(?string $visitorId = null): array
    {
        $settings = CookieConsent::$plugin->getSettings();

        // 1) Cookie (set client-side, so read $_COOKIE raw — it isn't Craft-signed).
        $raw = $_COOKIE[$settings->cookieName] ?? null;
        if ($raw) {
            $decoded = Json::decodeIfJson($raw);
            if (is_array($decoded) && isset($decoded['c']) && is_array($decoded['c'])) {
                return $decoded['c'];
            }
        }

        // 2) DB fallback by visitorId.
        $vid = $visitorId ?? ($_COOKIE[$settings->cookieName . '_vid'] ?? null);
        if ($vid) {
            $record = CookieConsent::$plugin->consents->getLatestByVisitor((string)$vid);
            if ($record) {
                return $record->getCategories();
            }
        }

        // 3) Defaults — required categories only.
        $defaults = [];
        foreach ($settings->getCategories() as $cat) {
            $defaults[$cat['handle']] = $cat['required'];
        }
        return $defaults;
    }

    /**
     * The latest stored decision for a visitor (or the current one via cookie).
     *
     * @return array<string,mixed>|null
     */
    public function status(?string $visitorId = null): ?array
    {
        $settings = CookieConsent::$plugin->getSettings();
        $vid = $visitorId ?? ($_COOKIE[$settings->cookieName . '_vid'] ?? null);

        if (!$vid) {
            return null;
        }

        $record = CookieConsent::$plugin->consents->getLatestByVisitor((string)$vid);

        if (!$record) {
            return null;
        }

        return [
            'found' => true,
            'categories' => $record->getCategories(),
            'action' => $record->action,
            'policyVersion' => $record->policyVersion,
            'needsRefresh' => CookieConsent::$plugin->consents->needsRefresh($record),
        ];
    }

    /**
     * Output the Google Consent Mode v2 "default = denied" block for the <head>.
     *
     * MUST be printed before GA loads. It also re-applies any stored decision so
     * GA is granted on revisit without flicker, then (optionally) loads gtag.js.
     *
     * @param bool $loadGtag Whether to also inject gtag.js using the configured GA id.
     */
    public function gtagDefaults(bool $loadGtag = true): Markup
    {
        $settings = CookieConsent::$plugin->getSettings();

        // No-op when the Google Consent Mode integration is turned off.
        if (!$settings->consentModeEnabled) {
            return new Markup('', 'UTF-8');
        }

        $cookieName = $settings->cookieName;
        $gaId = $settings->gaMeasurementId;

        $signals = [];
        foreach ($settings->getCategories() as $cat) {
            $signals[$cat['handle']] = $cat['gtagSignals'];
        }
        $signalsJson = Json::encode($signals);
        $cookieJson = Json::encode($cookieName);

        $js = <<<JS
window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}
var __ccDenied={ad_storage:'denied',analytics_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',functionality_storage:'denied',personalization_storage:'denied',security_storage:'granted'};
gtag('consent','default',Object.assign({},__ccDenied,{wait_for_update:500}));
gtag('set','url_passthrough',true);gtag('set','ads_data_redaction',true);
window.__ccSignals={$signalsJson};
window.__ccBuildUpdate=function(c){var u=Object.assign({},__ccDenied);Object.keys(window.__ccSignals).forEach(function(h){(window.__ccSignals[h]||[]).forEach(function(s){if(s==='security_storage')return;u[s]=c[h]?'granted':'denied';});});return u;};
try{var m=document.cookie.match(new RegExp('(?:^|; )'+{$cookieJson}+'=([^;]*)'));if(m){var d=JSON.parse(decodeURIComponent(m[1]));if(d&&d.c){gtag('consent','update',window.__ccBuildUpdate(d.c));}}}catch(e){}
JS;

        $out = Html::tag('script', $js, ['type' => 'text/javascript']);

        if ($loadGtag && $gaId) {
            $out .= Html::tag('script', '', [
                'async' => true,
                'src' => "https://www.googletagmanager.com/gtag/js?id={$gaId}",
            ]);
            $out .= Html::tag('script', "gtag('js',new Date());gtag('config'," . Json::encode($gaId) . ");");
        }

        // Ensure gated provider scripts run for returning visitors even when the
        // banner isn't shown.
        $this->registerRuntime();

        return new Markup($out, 'UTF-8');
    }

    /**
     * Render the cookie banner. Registers the interaction JS asset and injects
     * the config the script needs.
     *
     * @param string|null $template Optional project template path to render instead of the default.
     */
    public function banner(?string $template = null, array $variables = []): Markup
    {
        $this->registerRuntime($variables['saveUrl'] ?? null);

        $config = $this->config();

        $vars = array_merge([
            'config' => $config,
            'current' => $this->currentCategories(),
            'needsConsent' => $this->needsConsent(),
        ], $variables);

        $mode = View::TEMPLATE_MODE_SITE;
        $template = $template ?: 'craft-cmp/banner';

        $html = Craft::$app->getView()->renderTemplate($template, $vars, $mode);

        return new Markup($html, 'UTF-8');
    }

    /**
     * Register the interaction asset + the JS config it needs (cookie, scripts,
     * categories, save endpoint). Idempotent — safe to call from both
     * gtagDefaults() and banner().
     */
    private function registerRuntime(?string $saveUrl = null): void
    {
        $view = Craft::$app->getView();
        $view->registerAssetBundle(CookieConsentAsset::class);

        $config = $this->config();

        $view->registerJsVar('__ccConfig', [
            'saveUrl' => $saveUrl ?? '/cookie-consent/save',
            'cookieName' => $config['cookie']['name'],
            'cookieLifetimeDays' => $config['cookie']['lifetimeDays'],
            'policyVersion' => $config['policyVersion'],
            'consentMode' => $config['consentMode'],
            'categories' => $config['categories'],
            'scripts' => $config['scripts'],
        ]);
    }

    /**
     * Whether the banner should be shown (no decision, or policy changed).
     */
    public function needsConsent(?string $visitorId = null): bool
    {
        $settings = CookieConsent::$plugin->getSettings();
        $raw = $_COOKIE[$settings->cookieName] ?? null;

        if (!$raw) {
            return true;
        }

        $decoded = Json::decodeIfJson($raw);
        if (!is_array($decoded) || !isset($decoded['v'])) {
            return true;
        }

        return (string)$decoded['v'] !== (string)$settings->policyVersion;
    }
}
