<?php

namespace guimauve\cookieconsent\services;

use Craft;
use craft\base\Component;
use guimauve\cookieconsent\CookieConsent;

/**
 * Builds the public, frontend-facing consent configuration payload.
 *
 * This is the contract every frontend (Nuxt, React, plain JS) consumes. It
 * deliberately contains no framework assumptions — just data + the category to
 * Consent Mode v2 signal mapping.
 */
class Config extends Component
{
    /**
     * @return array<string,mixed>
     */
    public function getPublicConfig(?string $locale = null): array
    {
        $settings = CookieConsent::$plugin->getSettings();

        $consentMode = $settings->consentModeEnabled;

        return [
            'consentVersion' => $settings->consentVersion,
            'policyVersion' => $settings->policyVersion,
            // Google Consent Mode integration (null/empty when disabled).
            'consentMode' => $consentMode,
            'gaMeasurementId' => $consentMode ? ($settings->gaMeasurementId ?: null) : null,
            'cookie' => [
                'name' => $settings->cookieName,
                'lifetimeDays' => $settings->cookieLifetimeDays,
            ],
            'banner' => [
                'title' => Craft::t('site', $settings->bannerTitle),
                'body' => Craft::t('site', $settings->bannerBody),
                'acceptAllLabel' => Craft::t('site', $settings->acceptAllLabel),
                'rejectAllLabel' => Craft::t('site', $settings->rejectAllLabel),
                'savePrefsLabel' => Craft::t('site', $settings->savePrefsLabel),
                'managePrefsLabel' => Craft::t('site', $settings->managePrefsLabel),
            ],
            'categories' => array_map(static function(array $cat) use ($consentMode) {
                return [
                    'handle' => $cat['handle'],
                    'label' => Craft::t('site', $cat['label']),
                    'description' => Craft::t('site', $cat['description']),
                    'required' => $cat['required'],
                    // Only surface signals when Consent Mode is on.
                    'gtagSignals' => $consentMode ? $cat['gtagSignals'] : [],
                ];
            }, $settings->getCategories()),
            // Provider-agnostic tags, loaded by the frontend when their category is granted.
            'scripts' => array_map(static function(array $script) {
                return [
                    'name' => $script['name'],
                    'category' => $script['category'],
                    'src' => $script['src'],
                    'code' => $script['code'],
                ];
            }, $settings->getScripts()),
        ];
    }
}
