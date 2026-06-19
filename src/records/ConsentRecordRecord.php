<?php

namespace guimauve\cookieconsent\records;

use craft\db\ActiveRecord;
use craft\records\Element;
use guimauve\cookieconsent\migrations\Install;
use yii\db\ActiveQueryInterface;

/**
 * ActiveRecord mapped to the consent records content table.
 *
 * @property int $id
 * @property string $visitorId
 * @property int|null $userId
 * @property int $siteId
 * @property array|null $categories
 * @property string $action
 * @property string $consentVersion
 * @property string $policyVersion
 * @property string|null $locale
 * @property string|null $ip
 * @property string|null $userAgent
 */
class ConsentRecordRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return Install::TABLE;
    }

    public function getElement(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id' => 'id']);
    }
}
