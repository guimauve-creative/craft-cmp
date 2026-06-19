<?php

namespace guimauve\cookieconsent\gql\resolvers;

use guimauve\cookieconsent\CookieConsent;
use guimauve\cookieconsent\elements\ConsentRecord;

/**
 * Resolvers for the consent queries. Shared formatting helpers live here so the
 * query and mutation return an identical shape.
 */
class ConsentResolver
{
    public static function resolveConfig($source, array $arguments): array
    {
        $config = CookieConsent::$plugin->config->getPublicConfig($arguments['locale'] ?? null);

        // Flatten the nested REST shape into the GraphQL field set.
        return [
            'consentVersion' => $config['consentVersion'],
            'policyVersion' => $config['policyVersion'],
            'consentMode' => $config['consentMode'],
            'gaMeasurementId' => $config['gaMeasurementId'],
            'cookieName' => $config['cookie']['name'],
            'cookieLifetimeDays' => $config['cookie']['lifetimeDays'],
            'bannerTitle' => $config['banner']['title'],
            'bannerBody' => $config['banner']['body'],
            'acceptAllLabel' => $config['banner']['acceptAllLabel'],
            'rejectAllLabel' => $config['banner']['rejectAllLabel'],
            'savePrefsLabel' => $config['banner']['savePrefsLabel'],
            'managePrefsLabel' => $config['banner']['managePrefsLabel'],
            'categories' => $config['categories'],
            'links' => $config['links'],
            'scripts' => $config['scripts'],
        ];
    }

    public static function resolveStatus($source, array $arguments): array
    {
        $record = CookieConsent::$plugin->consents->getLatestByVisitor((string)$arguments['visitorId']);

        return self::formatRecord($record);
    }

    /**
     * Turn a ConsentRecord (or null) into the GraphQL field set.
     */
    public static function formatRecord(?ConsentRecord $record): array
    {
        if (!$record) {
            return ['found' => false, 'needsRefresh' => true, 'categories' => []];
        }

        $categories = [];
        foreach ($record->getCategories() as $handle => $granted) {
            $categories[] = ['handle' => $handle, 'granted' => (bool)$granted];
        }

        return [
            'id' => $record->id,
            'found' => true,
            'visitorId' => $record->visitorId,
            'action' => $record->action,
            'consentVersion' => $record->consentVersion,
            'policyVersion' => $record->policyVersion,
            'locale' => $record->locale,
            'dateCreated' => $record->dateCreated?->format('c'),
            'needsRefresh' => CookieConsent::$plugin->consents->needsRefresh($record),
            'categories' => $categories,
        ];
    }
}
