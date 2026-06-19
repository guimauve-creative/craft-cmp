<?php

namespace guimauve\cookieconsent\console\controllers;

use craft\console\Controller;
use guimauve\cookieconsent\CookieConsent;
use yii\console\ExitCode;

/**
 * Maintenance commands for consent records.
 *
 *   php craft cookie-consent/records/gc
 */
class RecordsController extends Controller
{
    /**
     * Garbage-collect consent records older than the configured retention period.
     */
    public function actionGc(): int
    {
        $deleted = CookieConsent::$plugin->consents->garbageCollect();

        $this->stdout("Deleted {$deleted} expired consent record(s).\n");

        return ExitCode::OK;
    }
}
