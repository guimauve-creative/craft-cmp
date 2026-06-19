<?php

namespace guimauve\cookieconsent\controllers;

use Craft;
use craft\web\Controller;
use guimauve\cookieconsent\CookieConsent;
use yii\base\InvalidArgumentException;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Headless REST API for cookie consent.
 *
 *   POST cookie-consent/save     — store a consent decision
 *   GET  cookie-consent/status   — latest decision for a visitor
 *   GET  cookie-consent/config   — banner config + category/signal mapping
 */
class ConsentController extends Controller
{
    protected array|bool|int $allowAnonymous = [
        'save' => self::ALLOW_ANONYMOUS_LIVE,
        'status' => self::ALLOW_ANONYMOUS_LIVE,
        'config' => self::ALLOW_ANONYMOUS_LIVE,
    ];

    public function beforeAction($action): bool
    {
        // CORS: reflect an allow-listed Origin and answer preflight requests.
        $this->_applyCors();

        if (Craft::$app->getRequest()->getIsOptions()) {
            Craft::$app->getResponse()->setStatusCode(204);
            Craft::$app->end();
        }

        // The write endpoint is called cross-origin / server-to-server, where the
        // CSRF cookie isn't reliably present. We validate authenticity via the
        // Origin allow-list (and optional shared secret) instead.
        if ($action->id === 'save') {
            $this->enableCsrfValidation = false;
        }

        return parent::beforeAction($action);
    }

    public function actionConfig(): Response
    {
        $request = Craft::$app->getRequest();
        $locale = $request->getQueryParam('locale');

        return $this->asJson(CookieConsent::$plugin->config->getPublicConfig($locale));
    }

    public function actionStatus(): Response
    {
        $visitorId = (string)Craft::$app->getRequest()->getQueryParam('visitorId', '');

        if ($visitorId === '') {
            throw new BadRequestHttpException('visitorId is required.');
        }

        $record = CookieConsent::$plugin->consents->getLatestByVisitor($visitorId);

        if (!$record) {
            return $this->asJson(['found' => false, 'needsRefresh' => true]);
        }

        return $this->asJson([
            'found' => true,
            'categories' => $record->getCategories(),
            'action' => $record->action,
            'consentVersion' => $record->consentVersion,
            'policyVersion' => $record->policyVersion,
            'dateCreated' => $record->dateCreated?->format('c'),
            'needsRefresh' => CookieConsent::$plugin->consents->needsRefresh($record),
        ]);
    }

    public function actionSave(): Response
    {
        $this->requirePostRequest();
        $this->_requireSharedSecret();

        $request = Craft::$app->getRequest();

        try {
            $record = CookieConsent::$plugin->consents->save([
                'visitorId' => (string)$request->getBodyParam('visitorId', ''),
                'categories' => (array)$request->getBodyParam('categories', []),
                'action' => (string)$request->getBodyParam('action', 'custom'),
                'locale' => $request->getBodyParam('locale'),
                'userAgent' => $request->getUserAgent(),
            ]);
        } catch (InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        Craft::$app->getResponse()->setStatusCode(201);

        return $this->asJson([
            'ok' => true,
            'id' => $record->id,
            'consentVersion' => $record->consentVersion,
            'policyVersion' => $record->policyVersion,
            'dateCreated' => $record->dateCreated?->format('c'),
        ]);
    }

    // Private Methods
    // =========================================================================

    private function _applyCors(): void
    {
        $allowed = CookieConsent::$plugin->getSettings()->getAllowedOrigins();
        $origin = rtrim((string)Craft::$app->getRequest()->getHeaders()->get('Origin'), '/');

        if ($origin && in_array($origin, $allowed, true)) {
            $headers = Craft::$app->getResponse()->getHeaders();
            $headers->set('Access-Control-Allow-Origin', $origin);
            $headers->set('Vary', 'Origin');
            $headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
            $headers->set('Access-Control-Allow-Headers', 'Content-Type, X-Consent-Secret, X-CSRF-Token, X-Requested-With');
            $headers->set('Access-Control-Allow-Credentials', 'true');
            $headers->set('Access-Control-Max-Age', '86400');
        }
    }

    private function _requireSharedSecret(): void
    {
        $secret = CookieConsent::$plugin->getSettings()->sharedSecret;

        if ($secret === '') {
            return;
        }

        $provided = Craft::$app->getRequest()->getHeaders()->get('X-Consent-Secret');

        if (!is_string($provided) || !hash_equals($secret, $provided)) {
            throw new ForbiddenHttpException('Invalid consent secret.');
        }
    }
}
