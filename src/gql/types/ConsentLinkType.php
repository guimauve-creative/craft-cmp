<?php

namespace guimauve\cookieconsent\gql\types;

use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

/**
 * A small link shown at the bottom of the banner (resolved to a final URL).
 */
class ConsentLinkType
{
    public static function getName(): string
    {
        return 'ConsentLink';
    }

    public static function getType(): ObjectType
    {
        if ($type = GqlEntityRegistry::getEntity(self::getName())) {
            return $type;
        }

        return GqlEntityRegistry::createEntity(self::getName(), new ObjectType([
            'name' => self::getName(),
            'fields' => [
                'label' => ['type' => Type::string()],
                'url' => ['type' => Type::nonNull(Type::string())],
                'newTab' => ['type' => Type::boolean()],
            ],
        ]));
    }
}
