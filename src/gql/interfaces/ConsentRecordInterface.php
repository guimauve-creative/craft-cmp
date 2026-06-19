<?php

namespace guimauve\cookieconsent\gql\interfaces;

use craft\gql\GqlEntityRegistry;
use guimauve\cookieconsent\gql\types\ConsentCategoryStateType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

/**
 * A stored consent record / decision, as returned by the status query and save mutation.
 */
class ConsentRecordInterface
{
    public static function getName(): string
    {
        return 'ConsentRecord';
    }

    public static function getType(): ObjectType
    {
        if ($type = GqlEntityRegistry::getEntity(self::getName())) {
            return $type;
        }

        return GqlEntityRegistry::createEntity(self::getName(), new ObjectType([
            'name' => self::getName(),
            'description' => 'A stored cookie consent decision.',
            'fields' => [
                'id' => ['type' => Type::id()],
                'found' => ['type' => Type::boolean()],
                'visitorId' => ['type' => Type::string()],
                'action' => ['type' => Type::string()],
                'consentVersion' => ['type' => Type::string()],
                'policyVersion' => ['type' => Type::string()],
                'locale' => ['type' => Type::string()],
                'dateCreated' => ['type' => Type::string()],
                'needsRefresh' => ['type' => Type::boolean()],
                'categories' => ['type' => Type::listOf(ConsentCategoryStateType::getType())],
            ],
        ]));
    }
}
