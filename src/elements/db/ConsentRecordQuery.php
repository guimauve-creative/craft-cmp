<?php

namespace guimauve\cookieconsent\elements\db;

use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use guimauve\cookieconsent\migrations\Install;

/**
 * @method \guimauve\cookieconsent\elements\ConsentRecord[] all($db = null)
 * @method \guimauve\cookieconsent\elements\ConsentRecord|null one($db = null)
 * @method \guimauve\cookieconsent\elements\ConsentRecord|null nth(int $n, $db = null)
 */
class ConsentRecordQuery extends ElementQuery
{
    public mixed $visitorId = null;
    public mixed $userId = null;
    public mixed $action = null;
    public mixed $policyVersion = null;
    public mixed $consentVersion = null;
    public mixed $locale = null;

    public function visitorId(mixed $value): static
    {
        $this->visitorId = $value;
        return $this;
    }

    public function userId(mixed $value): static
    {
        $this->userId = $value;
        return $this;
    }

    public function action(mixed $value): static
    {
        $this->action = $value;
        return $this;
    }

    public function policyVersion(mixed $value): static
    {
        $this->policyVersion = $value;
        return $this;
    }

    public function consentVersion(mixed $value): static
    {
        $this->consentVersion = $value;
        return $this;
    }

    public function locale(mixed $value): static
    {
        $this->locale = $value;
        return $this;
    }

    protected function beforePrepare(): bool
    {
        $table = 'cookieconsent_records';
        $this->joinElementTable($table);

        $this->query->select([
            "$table.visitorId",
            "$table.userId",
            "$table.categories",
            "$table.action",
            "$table.consentVersion",
            "$table.policyVersion",
            "$table.locale",
            "$table.ip",
            "$table.userAgent",
        ]);

        if ($this->visitorId !== null) {
            $this->subQuery->andWhere(Db::parseParam("$table.visitorId", $this->visitorId));
        }

        if ($this->userId !== null) {
            $this->subQuery->andWhere(Db::parseParam("$table.userId", $this->userId));
        }

        if ($this->action !== null) {
            $this->subQuery->andWhere(Db::parseParam("$table.action", $this->action));
        }

        if ($this->policyVersion !== null) {
            $this->subQuery->andWhere(Db::parseParam("$table.policyVersion", $this->policyVersion));
        }

        if ($this->consentVersion !== null) {
            $this->subQuery->andWhere(Db::parseParam("$table.consentVersion", $this->consentVersion));
        }

        if ($this->locale !== null) {
            $this->subQuery->andWhere(Db::parseParam("$table.locale", $this->locale));
        }

        return parent::beforePrepare();
    }
}
