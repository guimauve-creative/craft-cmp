<?php

namespace guimauve\cookieconsent\controllers;

use Craft;
use craft\web\Controller;
use guimauve\cookieconsent\elements\ConsentRecord;
use yii\web\Response;

/**
 * Control Panel: consent records index.
 */
class RecordsController extends Controller
{
    public function actionIndex(): Response
    {
        $this->requirePermission('cookieConsent:viewRecords');

        return $this->renderTemplate('craft-cmp/records/index', [
            'title' => Craft::t('craft-cmp', 'Consent Records'),
            'elementType' => ConsentRecord::class,
            'elementDisplayName' => ConsentRecord::displayName(),
            'elementPluralDisplayName' => ConsentRecord::pluralDisplayName(),
            'sources' => Craft::$app->getElementSources()->getSources(ConsentRecord::class, 'index'),
            'context' => 'index',
            'canHaveDrafts' => false,
        ]);
    }
}
