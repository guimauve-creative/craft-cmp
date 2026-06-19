<?php

namespace guimauve\cookieconsent\models;

use Craft;
use craft\base\Model;
use craft\behaviors\EnvAttributeParserBehavior;

/**
 * Cookie Consent settings model.
 *
 * All settings are Project-Config backed, so they travel between environments.
 */
class Settings extends Model
{
    /**
     * @var string The display name shown in the CP nav.
     */
    public string $pluginName = 'Craft CMP - Consent Management Platform';

    /**
     * @var array Cookie category definitions. Each row:
     *   ['handle' => 'analytics', 'label' => 'Analytics', 'description' => '...',
     *    'required' => false, 'gtagSignals' => ['analytics_storage']]
     *
     * Pre-filled with the four standard categories mapped to Google Consent
     * Mode v2 signals. These are editable and deletable in the Control Panel —
     * the defaults only apply until the settings are first saved.
     */
    public array $categories = [
        [
            'handle' => 'necessary',
            'label' => 'Strictly necessary',
            'description' => 'Required for the website to function. These cannot be switched off.',
            'required' => true,
            'gtagSignals' => ['security_storage'],
        ],
        [
            'handle' => 'preferences',
            'label' => 'Functional / preferences',
            'description' => 'Remember your choices (language, region, layout) to personalise your experience.',
            'required' => false,
            'gtagSignals' => ['functionality_storage', 'personalization_storage'],
        ],
        [
            'handle' => 'analytics',
            'label' => 'Analytics / performance',
            'description' => 'Help us understand how visitors use the site so we can improve it.',
            'required' => false,
            'gtagSignals' => ['analytics_storage'],
        ],
        [
            'handle' => 'marketing',
            'label' => 'Marketing / advertising',
            'description' => 'Used to deliver relevant ads and measure advertising campaigns.',
            'required' => false,
            'gtagSignals' => ['ad_storage', 'ad_user_data', 'ad_personalization'],
        ],
    ];

    /**
     * @var string Banner heading.
     */
    public string $bannerTitle = 'We value your privacy';

    /**
     * @var string Banner body copy (may contain HTML / a policy link).
     */
    public string $bannerBody = 'We use cookies to enhance your experience, analyse traffic and for marketing. You can accept all, reject non-essential cookies, or manage your preferences.';

    public string $acceptAllLabel = 'Accept all';
    public string $rejectAllLabel = 'Reject all';
    public string $savePrefsLabel = 'Save preferences';
    public string $managePrefsLabel = 'Manage preferences';

    /**
     * @var array Small links shown at the bottom of the banner (privacy policy,
     *   cookie policy, …). Each row:
     *   ['label' => 'Privacy policy', 'url' => 'https://…', 'newTab' => true]
     */
    public array $links = [];

    /**
     * @var string Version of the published cookie/privacy policy. Bump to force re-consent.
     */
    public string $policyVersion = '1';

    /**
     * @var string Version of the banner/category configuration.
     */
    public string $consentVersion = '1';

    /**
     * @var bool Whether the Google Consent Mode v2 integration is enabled.
     *   When off, the per-category `gtagSignals` and `gaMeasurementId` are
     *   ignored and the plugin relies purely on script gating.
     */
    public bool $consentModeEnabled = true;

    /**
     * @var string Optional GA4 measurement id (G-XXXX) the frontend can self-configure with.
     */
    public string $gaMeasurementId = '';

    /**
     * @var array Provider-agnostic tags/scripts loaded only when their category
     *   is granted. Each row:
     *   ['name' => 'Meta Pixel', 'category' => 'marketing', 'src' => '', 'code' => '…']
     *   Either `src` (external script URL) or `code` (inline JS) — or both.
     */
    public array $scripts = [];

    /**
     * @var string Name of the cookie the frontend stores the decision in.
     */
    public string $cookieName = 'cc_consent';

    /**
     * @var int Cookie lifetime in days.
     */
    public int $cookieLifetimeDays = 180;

    /**
     * @var int Delete consent records older than this many days (0 = keep forever).
     */
    public int $consentRetentionDays = 0;

    /**
     * @var bool Whether to hash the stored IP address (recommended for privacy).
     */
    public bool $hashIp = true;

    /**
     * @var array Origins allowed to call the headless API (CORS allowlist). Empty = same-origin only.
     */
    public array $allowedOrigins = [];

    /**
     * @var string Optional shared secret; when set, write requests must send it as the
     *   `X-Consent-Secret` header. Useful when the frontend posts server-to-server.
     */
    public string $sharedSecret = '';

    public function behaviors(): array
    {
        return [
            'parser' => [
                'class' => EnvAttributeParserBehavior::class,
                'attributes' => ['gaMeasurementId', 'sharedSecret'],
            ],
        ];
    }

    public function rules(): array
    {
        return [
            [['consentModeEnabled'], 'boolean'],
            [['scripts', 'links'], 'safe'],
            [['pluginName', 'cookieName', 'policyVersion', 'consentVersion'], 'required'],
            [['cookieLifetimeDays'], 'integer', 'min' => 1],
            [['consentRetentionDays'], 'integer', 'min' => 0],
            [['hashIp'], 'boolean'],
            [['categories', 'allowedOrigins'], 'safe'],
        ];
    }

    /**
     * Normalise the editable-table categories into clean rows.
     *
     * @return array<int, array{handle:string,label:string,description:string,required:bool,gtagSignals:string[]}>
     */
    public function getCategories(): array
    {
        $out = [];

        foreach ($this->categories as $row) {
            $handle = trim((string)($row['handle'] ?? ''));

            if ($handle === '') {
                continue;
            }

            $signals = $row['gtagSignals'] ?? [];

            // The editable-table multiselect may hand us an associative map or a CSV string.
            if (is_string($signals)) {
                $signals = array_filter(array_map('trim', explode(',', $signals)));
            } elseif (is_array($signals)) {
                $signals = array_values(array_filter(array_map('strval', $signals)));
            } else {
                $signals = [];
            }

            $out[] = [
                'handle' => $handle,
                'label' => (string)($row['label'] ?? $handle),
                'description' => (string)($row['description'] ?? ''),
                'required' => !empty($row['required']),
                'gtagSignals' => array_values($signals),
            ];
        }

        return $out;
    }

    /**
     * Normalise the per-category scripts into clean rows.
     *
     * @return array<int, array{name:string,category:string,src:string,code:string}>
     */
    public function getScripts(): array
    {
        $out = [];

        foreach ($this->scripts as $row) {
            $category = trim((string)($row['category'] ?? ''));
            $src = trim((string)($row['src'] ?? ''));
            $code = (string)($row['code'] ?? '');

            // A script is only meaningful if it targets a category and has something to run.
            if ($category === '' || ($src === '' && trim($code) === '')) {
                continue;
            }

            $out[] = [
                'name' => (string)($row['name'] ?? $category),
                'category' => $category,
                'src' => $src,
                'code' => $code,
            ];
        }

        return $out;
    }

    /**
     * Scripts shaped for the Control Panel editable table (1:1 today, kept for
     * symmetry with categories and future column transforms).
     *
     * @return array<int, array<string,mixed>>
     */
    public function getScriptsForTable(): array
    {
        return $this->getScripts();
    }

    /**
     * The banner links for output. Links with no URL are skipped.
     *
     * @return array<int, array{label:string,url:string,newTab:bool}>
     */
    public function getLinks(): array
    {
        $out = [];

        foreach ($this->links as $row) {
            $url = trim((string)($row['url'] ?? ''));

            if ($url === '') {
                continue;
            }

            $label = trim((string)($row['label'] ?? ''));

            $out[] = [
                'label' => $label !== '' ? Craft::t('site', $label) : $url,
                'url' => $url,
                'newTab' => !empty($row['newTab']),
            ];
        }

        return $out;
    }

    /**
     * Categories shaped for the Control Panel editable table, where the
     * `gtagSignals` column is a comma-separated string.
     *
     * @return array<int, array<string,mixed>>
     */
    public function getCategoriesForTable(): array
    {
        return array_map(static function(array $cat) {
            return [
                'handle' => $cat['handle'],
                'label' => $cat['label'],
                'description' => $cat['description'],
                'required' => $cat['required'],
                'gtagSignals' => implode(', ', $cat['gtagSignals']),
            ];
        }, $this->getCategories());
    }

    /**
     * Flatten the editable-table origins into a list of strings.
     *
     * @return string[]
     */
    public function getAllowedOrigins(): array
    {
        $out = [];

        foreach ($this->allowedOrigins as $row) {
            $origin = is_array($row) ? trim((string)($row['origin'] ?? '')) : trim((string)$row);
            if ($origin !== '') {
                $out[] = rtrim($origin, '/');
            }
        }

        return $out;
    }

    /**
     * The seven Google Consent Mode v2 signals, for the settings multiselect.
     */
    public static function consentModeSignals(): array
    {
        return [
            'ad_storage' => 'ad_storage',
            'ad_user_data' => 'ad_user_data',
            'ad_personalization' => 'ad_personalization',
            'analytics_storage' => 'analytics_storage',
            'functionality_storage' => 'functionality_storage',
            'personalization_storage' => 'personalization_storage',
            'security_storage' => 'security_storage',
        ];
    }
}
