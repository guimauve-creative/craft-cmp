<?php

namespace guimauve\cookieconsent\gql\interfaces;

use craft\gql\GqlEntityRegistry;
use guimauve\cookieconsent\gql\types\ConsentCategoryType;
use guimauve\cookieconsent\gql\types\ConsentScriptType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

/**
 * The public consent configuration type (banner copy, categories, signal mapping).
 */
class ConsentConfigInterface
{
    public static function getName(): string
    {
        return 'ConsentConfig';
    }

    public static function getType(): ObjectType
    {
        if ($type = GqlEntityRegistry::getEntity(self::getName())) {
            return $type;
        }

        return GqlEntityRegistry::createEntity(self::getName(), new ObjectType([
            'name' => self::getName(),
            'description' => 'Public cookie consent configuration for frontends.',
            'fields' => [
                'consentVersion' => ['type' => Type::string()],
                'policyVersion' => ['type' => Type::string()],
                'consentMode' => ['type' => Type::boolean()],
                'gaMeasurementId' => ['type' => Type::string()],
                'cookieName' => ['type' => Type::string()],
                'cookieLifetimeDays' => ['type' => Type::int()],
                'bannerTitle' => ['type' => Type::string()],
                'bannerBody' => ['type' => Type::string()],
                'acceptAllLabel' => ['type' => Type::string()],
                'rejectAllLabel' => ['type' => Type::string()],
                'savePrefsLabel' => ['type' => Type::string()],
                'managePrefsLabel' => ['type' => Type::string()],
                'categories' => ['type' => Type::listOf(ConsentCategoryType::getType())],
                'scripts' => ['type' => Type::listOf(ConsentScriptType::getType())],
            ],
        ]));
    }
}
