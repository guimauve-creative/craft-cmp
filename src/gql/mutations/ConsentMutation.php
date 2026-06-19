<?php

namespace guimauve\cookieconsent\gql\mutations;

use craft\gql\base\Mutation;
use guimauve\cookieconsent\gql\Gql as GqlHelper;
use guimauve\cookieconsent\gql\interfaces\ConsentRecordInterface;
use guimauve\cookieconsent\gql\resolvers\SaveConsentResolver;
use GraphQL\Type\Definition\Type;

class ConsentMutation extends Mutation
{
    public static function getMutations(bool $checkToken = true): array
    {
        if ($checkToken && !GqlHelper::canSaveRecords()) {
            return [];
        }

        return [
            'saveCookieConsent' => [
                'type' => ConsentRecordInterface::getType(),
                'args' => [
                    'visitorId' => Type::nonNull(Type::string()),
                    'categories' => Type::nonNull(Type::string()),
                    'action' => Type::string(),
                    'locale' => Type::string(),
                ],
                'resolve' => [SaveConsentResolver::class, 'resolve'],
                'description' => 'Stores a cookie consent decision. `categories` is a JSON object string of handle => bool.',
            ],
        ];
    }
}
