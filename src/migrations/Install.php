<?php

namespace guimauve\cookieconsent\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Table;

/**
 * Install migration.
 *
 * Creates the {{%cookieconsent_records}} table. Consent records are Craft
 * elements, so their identity row lives in the native `elements` table and the
 * native attributes live here, joined on `id`.
 */
class Install extends Migration
{
    public const TABLE = '{{%cookieconsent_records}}';

    public function safeUp(): bool
    {
        if ($this->createTables()) {
            $this->createIndexes();
            $this->addForeignKeys();
            Craft::$app->db->schema->refresh();
        }

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists(self::TABLE);

        return true;
    }

    protected function createTables(): bool
    {
        if ($this->db->tableExists(self::TABLE)) {
            return false;
        }

        $this->createTable(self::TABLE, [
            'id' => $this->integer()->notNull(),
            'visitorId' => $this->char(36)->notNull(),
            'userId' => $this->integer()->null(),
            'siteId' => $this->integer()->notNull(),
            'categories' => $this->text(),
            'action' => $this->string(20)->notNull()->defaultValue('custom'),
            'consentVersion' => $this->string(20)->notNull()->defaultValue('1'),
            'policyVersion' => $this->string(20)->notNull()->defaultValue('1'),
            'locale' => $this->string(12)->null(),
            'ip' => $this->string(128)->null(),
            'userAgent' => $this->text()->null(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
            'PRIMARY KEY([[id]])',
        ]);

        return true;
    }

    protected function createIndexes(): void
    {
        $this->createIndex(null, self::TABLE, ['visitorId']);
        $this->createIndex(null, self::TABLE, ['userId']);
        $this->createIndex(null, self::TABLE, ['siteId']);
        $this->createIndex(null, self::TABLE, ['dateCreated']);
    }

    protected function addForeignKeys(): void
    {
        $this->addForeignKey(null, self::TABLE, ['id'], Table::ELEMENTS, ['id'], 'CASCADE', null);
        $this->addForeignKey(null, self::TABLE, ['siteId'], Table::SITES, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, self::TABLE, ['userId'], Table::USERS, ['id'], 'SET NULL', null);
    }
}
