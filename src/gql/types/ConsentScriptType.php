<?php

namespace guimauve\cookieconsent\gql\types;

use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

/**
 * A provider-agnostic tag/script the frontend loads when its category is granted.
 */
class ConsentScriptType
{
    public static function getName(): string
    {
        return 'ConsentScript';
    }

    public static function getType(): ObjectType
    {
        if ($type = GqlEntityRegistry::getEntity(self::getName())) {
            return $type;
        }

        return GqlEntityRegistry::createEntity(self::getName(), new ObjectType([
            'name' => self::getName(),
            'fields' => [
                'name' => ['type' => Type::string()],
                'category' => ['type' => Type::nonNull(Type::string())],
                'src' => ['type' => Type::string()],
                'code' => ['type' => Type::string()],
            ],
        ]));
    }
}
