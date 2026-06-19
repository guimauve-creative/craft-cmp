<?php

namespace guimauve\cookieconsent\controllers;

use Craft;
use craft\elements\Entry;
use craft\web\Controller;
use guimauve\cookieconsent\CookieConsent;
use guimauve\cookieconsent\models\Settings;
use yii\web\Response;

/**
 * Control Panel: plugin settings.
 */
class SettingsController extends Controller
{
    public function actionEdit(): Response
    {
        $this->requireAdmin();

        /** @var CookieConsent $plugin */
        $plugin = CookieConsent::$plugin;

        return $this->renderTemplate('cookie-consent/settings', [
            'plugin' => $plugin,
            'settings' => $plugin->getSettings(),
            'signalOptions' => Settings::consentModeSignals(),
            'entryOptions' => $this->_entryOptions(),
        ]);
    }

    /**
     * Entry options for the links repeater's entry picker. Capped so the
     * dropdown stays usable on large sites — admins use the URL column beyond that.
     *
     * @return array<int, array{label:string,value:string}>
     */
    private function _entryOptions(): array
    {
        $options = [['label' => Craft::t('cookie-consent', '— Select an entry —'), 'value' => '']];

        $entries = Entry::find()
            ->site('*')
            ->unique()
            ->status(null)
            ->orderBy(['title' => SORT_ASC])
            ->limit(500)
            ->all();

        foreach ($entries as $entry) {
            $options[] = [
                'label' => $entry->title,
                'value' => (string)$entry->id,
            ];
        }

        return $options;
    }
}
