<?php

namespace guimauve\cookieconsent\gql;

use craft\helpers\Gql as GqlHelper;

/**
 * Scope helpers — honour the schema components registered by the plugin.
 */
class Gql
{
    public static function canQueryConfig(): bool
    {
        return GqlHelper::isSchemaAwareOf('cookieConsent.config:read');
    }

    public static function canQueryRecords(): bool
    {
        return GqlHelper::isSchemaAwareOf('cookieConsent.records:read');
    }

    public static function canSaveRecords(): bool
    {
        return GqlHelper::isSchemaAwareOf('cookieConsent.records:save');
    }
}
