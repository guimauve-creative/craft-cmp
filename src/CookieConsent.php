<?php

namespace guimauve\cookieconsent;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterGqlMutationsEvent;
use craft\events\RegisterGqlQueriesEvent;
use craft\events\RegisterGqlSchemaComponentsEvent;
use craft\events\RegisterGqlTypesEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\helpers\UrlHelper;
use craft\services\Elements;
use craft\services\Gql;
use craft\services\UserPermissions;
use craft\web\UrlManager;
use craft\web\View;
use craft\web\twig\variables\CraftVariable;

use guimauve\cookieconsent\elements\ConsentRecord;
use guimauve\cookieconsent\gql\interfaces\ConsentConfigInterface;
use guimauve\cookieconsent\gql\interfaces\ConsentRecordInterface;
use guimauve\cookieconsent\gql\mutations\ConsentMutation;
use guimauve\cookieconsent\gql\queries\ConsentQuery;
use guimauve\cookieconsent\models\Settings;
use guimauve\cookieconsent\services\Config as ConfigService;
use guimauve\cookieconsent\services\Consents;
use guimauve\cookieconsent\variables\CookieConsentVariable;

use yii\base\Event;

/**
 * Cookie Consent plugin.
 *
 * @property-read Consents $consents
 * @property-read ConfigService $config
 * @property-read Settings $settings
 * @method Settings getSettings()
 */
class CookieConsent extends Plugin
{
    // Static Properties
    // =========================================================================

    public static CookieConsent $plugin;

    // Editions
    // =========================================================================

    public const EDITION_LITE = 'lite';
    public const EDITION_PRO = 'pro';

    public static function editions(): array
    {
        return [
            self::EDITION_LITE,
            self::EDITION_PRO,
        ];
    }

    // Properties
    // =========================================================================

    public bool $hasCpSection = true;
    public bool $hasCpSettings = true;
    public string $schemaVersion = '1.0.0';

    // Public Methods
    // =========================================================================

    public function init(): void
    {
        parent::init();

        self::$plugin = $this;

        Craft::setAlias('@guimauve/cookieconsent', $this->getBasePath());

        $this->setComponents([
            'consents' => Consents::class,
            'config' => ConfigService::class,
        ]);

        $this->_registerElementTypes();
        $this->_registerSiteRoutes();
        $this->_registerGraphQl();
        $this->_registerVariable();
        $this->_registerTemplateRoots();

        if (Craft::$app->getRequest()->getIsCpRequest()) {
            $this->_registerCpRoutes();
        }

        if (Craft::$app->getEdition() !== Craft::Solo) {
            $this->_registerPermissions();
        }
    }

    public function getPluginName(): string
    {
        return Craft::t('cookie-consent', $this->getSettings()->pluginName ?: 'Cookie Consent');
    }

    public function getSettingsResponse(): mixed
    {
        return Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('cookie-consent/settings'));
    }

    public function getCpNavItem(): ?array
    {
        $nav = parent::getCpNavItem();
        $nav['label'] = $this->getPluginName();

        $nav['subnav']['records'] = [
            'label' => Craft::t('cookie-consent', 'Records'),
            'url' => 'cookie-consent/records',
        ];

        if (Craft::$app->getUser()->getIsAdmin() && Craft::$app->getConfig()->getGeneral()->allowAdminChanges) {
            $nav['subnav']['settings'] = [
                'label' => Craft::t('cookie-consent', 'Settings'),
                'url' => 'cookie-consent/settings',
            ];
        }

        return $nav;
    }

    // Protected Methods
    // =========================================================================

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    // Private Methods
    // =========================================================================

    private function _registerElementTypes(): void
    {
        Event::on(Elements::class, Elements::EVENT_REGISTER_ELEMENT_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = ConsentRecord::class;
        });
    }

    private function _registerVariable(): void
    {
        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, function(Event $event) {
            $event->sender->set('cookieConsent', CookieConsentVariable::class);
        });
    }

    private function _registerTemplateRoots(): void
    {
        // Front-end template root so craft.cookieConsent.banner() can render
        // `cookie-consent/banner` (and buyers can supply their own path).
        Event::on(View::class, View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS, function(RegisterTemplateRootsEvent $event) {
            $event->roots['cookie-consent'] = $this->getBasePath() . DIRECTORY_SEPARATOR . 'templates';
        });
    }

    private function _registerSiteRoutes(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_SITE_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules['POST cookie-consent/save'] = 'cookie-consent/consent/save';
            $event->rules['OPTIONS cookie-consent/save'] = 'cookie-consent/consent/save';
            $event->rules['GET cookie-consent/status'] = 'cookie-consent/consent/status';
            $event->rules['GET cookie-consent/config'] = 'cookie-consent/consent/config';
        });
    }

    private function _registerCpRoutes(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules['cookie-consent'] = 'cookie-consent/records/index';
            $event->rules['cookie-consent/records'] = 'cookie-consent/records/index';
            $event->rules['cookie-consent/settings'] = 'cookie-consent/settings/edit';
        });
    }

    private function _registerPermissions(): void
    {
        Event::on(UserPermissions::class, UserPermissions::EVENT_REGISTER_PERMISSIONS, function(RegisterUserPermissionsEvent $event) {
            $event->permissions[] = [
                'heading' => Craft::t('cookie-consent', 'Cookie Consent'),
                'permissions' => [
                    'cookieConsent:viewRecords' => [
                        'label' => Craft::t('cookie-consent', 'View consent records'),
                    ],
                    'cookieConsent:exportRecords' => [
                        'label' => Craft::t('cookie-consent', 'Export consent records'),
                    ],
                ],
            ];
        });
    }

    private function _registerGraphQl(): void
    {
        Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_TYPES, function(RegisterGqlTypesEvent $event) {
            $event->types[] = ConsentConfigInterface::class;
            $event->types[] = ConsentRecordInterface::class;
        });

        Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_QUERIES, function(RegisterGqlQueriesEvent $event) {
            foreach (ConsentQuery::getQueries() as $key => $value) {
                $event->queries[$key] = $value;
            }
        });

        Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_MUTATIONS, function(RegisterGqlMutationsEvent $event) {
            foreach (ConsentMutation::getMutations() as $key => $value) {
                $event->mutations[$key] = $value;
            }
        });

        Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_SCHEMA_COMPONENTS, function(RegisterGqlSchemaComponentsEvent $event) {
            $label = Craft::t('cookie-consent', 'Cookie Consent');
            $event->queries[$label]['cookieConsent.config:read'] = [
                'label' => Craft::t('cookie-consent', 'Read cookie consent configuration'),
            ];
            $event->queries[$label]['cookieConsent.records:read'] = [
                'label' => Craft::t('cookie-consent', 'Read cookie consent records'),
            ];
            $event->mutations[$label]['cookieConsent.records:save'] = [
                'label' => Craft::t('cookie-consent', 'Save cookie consent records'),
            ];
        });
    }
}
