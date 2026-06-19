<?php

namespace guimauve\cookieconsent\gql\types;

use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

/**
 * A cookie category as exposed to the frontend, including its Consent Mode v2 signal mapping.
 */
class ConsentCategoryType
{
    public static function getName(): string
    {
        return 'ConsentCategory';
    }

    public static function getType(): ObjectType
    {
        if ($type = GqlEntityRegistry::getEntity(self::getName())) {
            return $type;
        }

        return GqlEntityRegistry::createEntity(self::getName(), new ObjectType([
            'name' => self::getName(),
            'fields' => [
                'handle' => ['type' => Type::nonNull(Type::string())],
                'label' => ['type' => Type::string()],
                'description' => ['type' => Type::string()],
                'required' => ['type' => Type::boolean()],
                'gtagSignals' => ['type' => Type::listOf(Type::string())],
            ],
        ]));
    }
}
