<?php

namespace guimauve\cookieconsent\elements;

use Craft;
use craft\base\Element;
use craft\elements\actions\Delete;
use craft\elements\actions\Restore;
use craft\elements\conditions\ElementConditionInterface;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Db;
use craft\helpers\Html;
use craft\helpers\Json;
use guimauve\cookieconsent\elements\conditions\ConsentRecordCondition;
use guimauve\cookieconsent\elements\db\ConsentRecordQuery;
use guimauve\cookieconsent\elements\exporters\ConsentRecordExporter;
use guimauve\cookieconsent\migrations\Install;

/**
 * Consent record element — one immutable proof-of-consent row.
 */
class ConsentRecord extends Element
{
    public ?string $visitorId = null;
    public ?int $userId = null;
    public string $action = 'custom';
    public string $consentVersion = '1';
    public string $policyVersion = '1';
    public ?string $locale = null;
    public ?string $ip = null;
    public ?string $userAgent = null;

    /**
     * @var array<string,bool> Category handle => granted map.
     */
    private array $_categories = [];

    // Static
    // =========================================================================

    public static function displayName(): string
    {
        return Craft::t('craft-cmp', 'Consent Record');
    }

    public static function pluralDisplayName(): string
    {
        return Craft::t('craft-cmp', 'Consent Records');
    }

    public static function refHandle(): ?string
    {
        return 'consent';
    }

    public static function hasTitles(): bool
    {
        return false;
    }

    public static function hasUris(): bool
    {
        return false;
    }

    public static function isLocalized(): bool
    {
        return false;
    }

    public static function hasStatuses(): bool
    {
        return false;
    }

    public static function find(): ConsentRecordQuery
    {
        return new ConsentRecordQuery(static::class);
    }

    public static function createCondition(): ElementConditionInterface
    {
        return Craft::createObject(ConsentRecordCondition::class, [static::class]);
    }

    public static function gqlTypeNameByContext(mixed $context): string
    {
        return 'ConsentRecord';
    }

    protected static function defineSources(string $context = null): array
    {
        $sources = [
            [
                'key' => '*',
                'label' => Craft::t('craft-cmp', 'All records'),
                'criteria' => [],
                'defaultSort' => ['dateCreated', 'desc'],
            ],
        ];

        foreach (['accept_all', 'reject_all', 'custom', 'withdraw'] as $action) {
            $sources[] = [
                'key' => "action:$action",
                'label' => Craft::t('craft-cmp', ucfirst(str_replace('_', ' ', $action))),
                'criteria' => ['action' => $action],
            ];
        }

        return $sources;
    }

    protected static function defineActions(string $source = null): array
    {
        return [
            Craft::$app->getElements()->createAction([
                'type' => Delete::class,
                'confirmationMessage' => Craft::t('craft-cmp', 'Are you sure you want to delete the selected consent records?'),
                'successMessage' => Craft::t('craft-cmp', 'Consent records deleted.'),
            ]),
            Restore::class,
        ];
    }

    protected static function defineExporters(string $source): array
    {
        return [
            ConsentRecordExporter::class,
        ];
    }

    protected static function defineTableAttributes(): array
    {
        return [
            'visitorId' => ['label' => Craft::t('craft-cmp', 'Visitor')],
            'action' => ['label' => Craft::t('craft-cmp', 'Action')],
            'categoriesSummary' => ['label' => Craft::t('craft-cmp', 'Categories')],
            'policyVersion' => ['label' => Craft::t('craft-cmp', 'Policy version')],
            'consentVersion' => ['label' => Craft::t('craft-cmp', 'Consent version')],
            'locale' => ['label' => Craft::t('craft-cmp', 'Locale')],
            'userId' => ['label' => Craft::t('craft-cmp', 'User')],
            'ip' => ['label' => Craft::t('craft-cmp', 'IP')],
            'dateCreated' => ['label' => Craft::t('craft-cmp', 'Date')],
        ];
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        return ['visitorId', 'action', 'categoriesSummary', 'policyVersion', 'locale', 'dateCreated'];
    }

    protected static function defineSortOptions(): array
    {
        return [
            'dateCreated' => Craft::t('craft-cmp', 'Date'),
            'action' => Craft::t('craft-cmp', 'Action'),
            'policyVersion' => Craft::t('craft-cmp', 'Policy version'),
        ];
    }

    // Instance
    // =========================================================================

    public function __toString(): string
    {
        return 'Consent #' . ($this->id ?? '?');
    }

    public function getUiLabel(): string
    {
        return (string)$this;
    }

    public function canView(\craft\elements\User $user): bool
    {
        return $user->can('cookieConsent:viewRecords');
    }

    public function canDelete(\craft\elements\User $user): bool
    {
        return $user->can('cookieConsent:viewRecords');
    }

    public function canSave(\craft\elements\User $user): bool
    {
        return false;
    }

    /**
     * Accepts an array, or the raw JSON string from the DB (during populate
     * Craft sets native attributes via __set, which routes here).
     *
     * @param array<string,bool>|string|null $categories
     */
    public function setCategories(array|string|null $categories): void
    {
        if (is_string($categories)) {
            $categories = Json::decodeIfJson($categories);
        }

        $this->_categories = is_array($categories)
            ? array_map(static fn($v) => (bool)$v, $categories)
            : [];
    }

    /**
     * @return array<string,bool>
     */
    public function getCategories(): array
    {
        return $this->_categories;
    }

    public function getCategoriesSummary(): string
    {
        $granted = array_keys(array_filter($this->_categories));
        return $granted ? implode(', ', $granted) : Craft::t('craft-cmp', 'none');
    }

    protected function attributeHtml(string $attribute): string
    {
        if ($attribute === 'categoriesSummary') {
            return Html::encode($this->getCategoriesSummary());
        }

        if ($attribute === 'userId') {
            return $this->userId ? Html::encode((string)$this->userId) : '–';
        }

        return parent::attributeHtml($attribute);
    }

    public function afterSave(bool $isNew): void
    {
        if (!$this->propagating) {
            $data = [
                'visitorId' => $this->visitorId,
                'userId' => $this->userId,
                'siteId' => $this->siteId,
                'categories' => Json::encode($this->_categories),
                'action' => $this->action,
                'consentVersion' => $this->consentVersion,
                'policyVersion' => $this->policyVersion,
                'locale' => $this->locale,
                'ip' => $this->ip,
                'userAgent' => $this->userAgent,
            ];

            if ($isNew) {
                Db::insert(Install::TABLE, array_merge(['id' => $this->id], $data));
            } else {
                Db::update(Install::TABLE, $data, ['id' => $this->id]);
            }
        }

        parent::afterSave($isNew);
    }
}
