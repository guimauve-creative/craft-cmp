<?php

namespace guimauve\cookieconsent\elements\exporters;

use craft\base\ElementInterface;
use craft\base\ElementExporter;
use craft\elements\db\ElementQueryInterface;
use guimauve\cookieconsent\elements\ConsentRecord;

/**
 * Exports consent records as flat rows (CSV / JSON) for GDPR / Law 25 audits.
 */
class ConsentRecordExporter extends ElementExporter
{
    public static function displayName(): string
    {
        return \Craft::t('cookie-consent', 'Consent records');
    }

    public function export(ElementQueryInterface $query): array
    {
        $rows = [];

        /** @var ConsentRecord $record */
        foreach ($query->all() as $record) {
            $rows[] = [
                'id' => $record->id,
                'visitorId' => $record->visitorId,
                'userId' => $record->userId,
                'siteId' => $record->siteId,
                'action' => $record->action,
                'categories' => $record->getCategoriesSummary(),
                'consentVersion' => $record->consentVersion,
                'policyVersion' => $record->policyVersion,
                'locale' => $record->locale,
                'ip' => $record->ip,
                'userAgent' => $record->userAgent,
                'dateCreated' => $record->dateCreated?->format('c'),
            ];
        }

        return $rows;
    }
}
