<?php

namespace guimauve\cookieconsent\services;

use Craft;
use craft\base\Component;
use guimauve\cookieconsent\CookieConsent;
use guimauve\cookieconsent\elements\ConsentRecord;
use yii\base\InvalidArgumentException;

/**
 * Core consent domain service. Both the REST controller and the GraphQL
 * resolvers funnel through here so there is a single source of truth.
 */
class Consents extends Component
{
    public const ACTIONS = ['accept_all', 'reject_all', 'custom', 'withdraw'];

    /**
     * Persist a consent decision as a new (append-only) record.
     *
     * @param array{
     *   visitorId:string,
     *   categories:array<string,bool>,
     *   action?:string,
     *   locale?:string,
     *   siteId?:int,
     *   userId?:int|null,
     *   ip?:string|null,
     *   userAgent?:string|null
     * } $payload
     */
    public function save(array $payload): ConsentRecord
    {
        $settings = CookieConsent::$plugin->getSettings();

        $visitorId = trim((string)($payload['visitorId'] ?? ''));
        if (!preg_match('/^[0-9a-fA-F-]{16,36}$/', $visitorId)) {
            throw new InvalidArgumentException('A valid visitorId is required.');
        }

        $action = (string)($payload['action'] ?? 'custom');
        if (!in_array($action, self::ACTIONS, true)) {
            $action = 'custom';
        }

        $categories = $this->normalizeCategories($payload['categories'] ?? []);

        $record = new ConsentRecord();
        $record->visitorId = $visitorId;
        $record->userId = $payload['userId'] ?? (Craft::$app->getUser()->getId() ?: null);
        $record->siteId = $payload['siteId'] ?? Craft::$app->getSites()->getCurrentSite()->id;
        $record->setCategories($categories);
        $record->action = $action;
        // Versions are stamped server-side and never trusted from the client.
        $record->consentVersion = $settings->consentVersion;
        $record->policyVersion = $settings->policyVersion;
        $record->locale = $payload['locale'] ?? Craft::$app->language;
        $record->ip = $this->resolveIp($payload['ip'] ?? null, $settings->hashIp);
        $record->userAgent = $payload['userAgent'] ?? null;

        if (!Craft::$app->getElements()->saveElement($record)) {
            throw new \RuntimeException('Could not save consent record: ' . implode(', ', $record->getFirstErrors()));
        }

        return $record;
    }

    /**
     * Get the latest consent record for a visitor (or authenticated user).
     */
    public function getLatestByVisitor(string $visitorId, ?int $siteId = null): ?ConsentRecord
    {
        return ConsentRecord::find()
            ->visitorId($visitorId)
            ->siteId($siteId)
            ->orderBy(['dateCreated' => SORT_DESC])
            ->one();
    }

    /**
     * Whether a stored record predates the current published policy version.
     */
    public function needsRefresh(?ConsentRecord $record): bool
    {
        if (!$record) {
            return true;
        }

        return $record->policyVersion !== CookieConsent::$plugin->getSettings()->policyVersion;
    }

    /**
     * Delete records older than `consentRetentionDays` (0 = keep forever).
     *
     * @return int Number of records removed.
     */
    public function garbageCollect(): int
    {
        $days = CookieConsent::$plugin->getSettings()->consentRetentionDays;

        if ($days <= 0) {
            return 0;
        }

        $cutoff = (new \DateTime('now', new \DateTimeZone('UTC')))->modify("-{$days} days");
        $deleted = 0;

        $records = ConsentRecord::find()
            ->where(['<', 'elements.dateCreated', $cutoff->format('Y-m-d H:i:s')])
            ->all();

        foreach ($records as $record) {
            if (Craft::$app->getElements()->deleteElement($record, true)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * @param array<string,mixed> $categories
     * @return array<string,bool>
     */
    private function normalizeCategories(array $categories): array
    {
        $defined = [];
        foreach (CookieConsent::$plugin->getSettings()->getCategories() as $cat) {
            $defined[$cat['handle']] = $cat['required'];
        }

        $out = [];
        foreach ($defined as $handle => $required) {
            // Required categories are always granted; otherwise honour the payload.
            $out[$handle] = $required ? true : !empty($categories[$handle]);
        }

        return $out;
    }

    private function resolveIp(?string $explicit, bool $hash): ?string
    {
        $ip = $explicit ?: Craft::$app->getRequest()->getUserIP();

        if (!$ip) {
            return null;
        }

        return $hash ? hash('sha256', $ip) : $ip;
    }
}
