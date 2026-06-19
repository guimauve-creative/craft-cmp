<?php

namespace guimauve\cookieconsent\gql\types;

use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

/**
 * A category handle plus the granted/denied state stored in a consent record.
 */
class ConsentCategoryStateType
{
    public static function getName(): string
    {
        return 'ConsentCategoryState';
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
                'granted' => ['type' => Type::boolean()],
            ],
        ]));
    }
}
