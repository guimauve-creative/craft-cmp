<?php

namespace guimauve\cookieconsent\gql\resolvers;

use Craft;
use craft\helpers\Json;
use guimauve\cookieconsent\CookieConsent;
use GraphQL\Type\Definition\ResolveInfo;

/**
 * Resolver for the `saveCookieConsent` mutation.
 */
class SaveConsentResolver
{
    public static function resolve($source, array $arguments, $context, ResolveInfo $resolveInfo): array
    {
        $categories = Json::decodeIfJson($arguments['categories'] ?? '{}');

        if (!is_array($categories)) {
            $categories = [];
        }

        $record = CookieConsent::$plugin->consents->save([
            'visitorId' => (string)$arguments['visitorId'],
            'categories' => $categories,
            'action' => (string)($arguments['action'] ?? 'custom'),
            'locale' => $arguments['locale'] ?? null,
            'userAgent' => Craft::$app->getRequest()->getUserAgent(),
        ]);

        return ConsentResolver::formatRecord($record);
    }
}
