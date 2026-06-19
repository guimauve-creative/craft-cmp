<?php

namespace guimauve\cookieconsent\controllers;

use Craft;
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
        ]);
    }
}
