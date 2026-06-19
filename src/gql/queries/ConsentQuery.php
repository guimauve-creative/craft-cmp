<?php

namespace guimauve\cookieconsent\gql\queries;

use craft\gql\base\Query;
use guimauve\cookieconsent\gql\Gql as GqlHelper;
use guimauve\cookieconsent\gql\interfaces\ConsentConfigInterface;
use guimauve\cookieconsent\gql\interfaces\ConsentRecordInterface;
use guimauve\cookieconsent\gql\resolvers\ConsentResolver;
use GraphQL\Type\Definition\Type;

class ConsentQuery extends Query
{
    public static function getQueries(bool $checkToken = true): array
    {
        $queries = [];

        if (!$checkToken || GqlHelper::canQueryConfig()) {
            $queries['cookieConsentConfig'] = [
                'type' => ConsentConfigInterface::getType(),
                'args' => [
                    'locale' => Type::string(),
                ],
                'resolve' => ConsentResolver::class . '::resolveConfig',
                'description' => 'Returns the public cookie consent configuration.',
            ];
        }

        if (!$checkToken || GqlHelper::canQueryRecords()) {
            $queries['cookieConsentStatus'] = [
                'type' => ConsentRecordInterface::getType(),
                'args' => [
                    'visitorId' => Type::nonNull(Type::string()),
                ],
                'resolve' => ConsentResolver::class . '::resolveStatus',
                'description' => 'Returns the latest stored consent decision for a visitor.',
            ];
        }

        return $queries;
    }
}
