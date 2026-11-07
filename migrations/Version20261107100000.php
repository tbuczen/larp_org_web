<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Tags can belong to one LARP (larp_id) or be shared (NULL). Schema API only, so it
 * runs on SQLite and MySQL.
 */
final class Version20261107100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'LARP-specific tags';
    }

    public function up(Schema $schema): void
    {
        $tag = $schema->getTable('tag');
        $tag->addColumn('larp_id', Types::INTEGER, ['notnull' => false]);
        $tag->dropIndex('UNIQ_389B7838CDE5729989D9B62');
        $tag->addUniqueIndex(['type', 'slug', 'larp_id'], 'UNIQ_389B7838CDE5729989D9B6263FF2A01');
        $tag->addIndex(['larp_id'], 'IDX_389B78363FF2A01');
        $tag->addForeignKeyConstraint('larp', ['larp_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_389B78363FF2A01');
    }

    public function down(Schema $schema): void
    {
        $tag = $schema->getTable('tag');
        $tag->removeForeignKey('FK_389B78363FF2A01');
        $tag->dropIndex('IDX_389B78363FF2A01');
        $tag->dropIndex('UNIQ_389B7838CDE5729989D9B6263FF2A01');
        $tag->dropColumn('larp_id');
        $tag->addUniqueIndex(['type', 'slug'], 'UNIQ_389B7838CDE5729989D9B62');
    }
}
