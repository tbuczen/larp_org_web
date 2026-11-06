<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * LARPs, editions, characters and tags. Schema API only, so it runs on SQLite and MySQL.
 */
final class Version20261106100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'LARPs, editions, characters, tags and tag categories';
    }

    public function up(Schema $schema): void
    {
        $larp = $schema->createTable('larp');
        $this->id($larp);
        $larp->addColumn('name', Types::STRING, ['length' => 160]);
        $larp->addColumn('slug', Types::STRING, ['length' => 180]);
        $larp->addColumn('project_type', Types::STRING, ['length' => 20]);
        $larp->addColumn('status', Types::STRING, ['length' => 20]);
        $larp->addColumn('format', Types::STRING, ['length' => 120, 'notnull' => false]);
        $larp->addColumn('setting', Types::STRING, ['length' => 120, 'notnull' => false]);
        $larp->addColumn('teaser', Types::STRING, ['length' => 500, 'notnull' => false]);
        $larp->addColumn('description', Types::TEXT, ['notnull' => false]);
        $larp->addColumn('cover_image', Types::STRING, ['length' => 255, 'notnull' => false]);
        $larp->addColumn('cover_image_alt', Types::STRING, ['length' => 255, 'notnull' => false]);
        $larp->addColumn('player_count', Types::STRING, ['length' => 120, 'notnull' => false]);
        $larp->addColumn('price', Types::STRING, ['length' => 120, 'notnull' => false]);
        $larp->addColumn('featured', Types::BOOLEAN);
        $larp->addColumn('position', Types::INTEGER);
        $this->timestamps($larp);
        $larp->addUniqueIndex(['slug'], 'UNIQ_54AD5CF8989D9B62');

        $edition = $schema->createTable('larp_edition');
        $this->id($edition);
        $edition->addColumn('larp_id', Types::INTEGER);
        $edition->addColumn('name', Types::STRING, ['length' => 120]);
        $edition->addColumn('start_date', Types::DATE_IMMUTABLE, ['notnull' => false]);
        $edition->addColumn('end_date', Types::DATE_IMMUTABLE, ['notnull' => false]);
        $edition->addColumn('location', Types::STRING, ['length' => 160, 'notnull' => false]);
        $edition->addColumn('location_url', Types::STRING, ['length' => 255, 'notnull' => false]);
        $edition->addColumn('recruitment', Types::STRING, ['length' => 20]);
        $edition->addColumn('registration_url', Types::STRING, ['length' => 255, 'notnull' => false]);
        $this->timestamps($edition);
        $edition->addIndex(['larp_id'], 'IDX_7C331E6563FF2A01');
        $edition->addForeignKeyConstraint('larp', ['larp_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_7C331E6563FF2A01');

        $category = $schema->createTable('tag_category');
        $this->id($category);
        $category->addColumn('name', Types::STRING, ['length' => 120]);
        $category->addColumn('type', Types::STRING, ['length' => 20]);
        $category->addColumn('position', Types::INTEGER);

        $tag = $schema->createTable('tag');
        $this->id($tag);
        $tag->addColumn('category_id', Types::INTEGER, ['notnull' => false]);
        $tag->addColumn('name', Types::STRING, ['length' => 120]);
        $tag->addColumn('slug', Types::STRING, ['length' => 140]);
        $tag->addColumn('type', Types::STRING, ['length' => 20]);
        $tag->addColumn('description', Types::TEXT, ['notnull' => false]);
        $tag->addUniqueIndex(['type', 'slug'], 'UNIQ_389B7838CDE5729989D9B62');
        $tag->addIndex(['category_id'], 'IDX_389B78312469DE2');
        $tag->addForeignKeyConstraint('tag_category', ['category_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_389B78312469DE2');

        $character = $schema->createTable('larp_character');
        $this->id($character);
        $character->addColumn('edition_id', Types::INTEGER);
        $character->addColumn('name', Types::STRING, ['length' => 160]);
        $character->addColumn('slug', Types::STRING, ['length' => 180]);
        $character->addColumn('teaser', Types::STRING, ['length' => 500, 'notnull' => false]);
        $character->addColumn('description', Types::TEXT, ['notnull' => false]);
        $character->addColumn('portrait', Types::STRING, ['length' => 255, 'notnull' => false]);
        $character->addColumn('casting', Types::STRING, ['length' => 20]);
        $character->addColumn('availability', Types::STRING, ['length' => 20]);
        $character->addColumn('position', Types::INTEGER);
        $this->timestamps($character);
        $character->addUniqueIndex(['edition_id', 'slug'], 'UNIQ_AFC950DF74281A5E989D9B62');
        $character->addIndex(['edition_id'], 'IDX_AFC950DF74281A5E');
        $character->addForeignKeyConstraint('larp_edition', ['edition_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_AFC950DF74281A5E');

        $characterTag = $schema->createTable('character_tag');
        $this->id($characterTag);
        $characterTag->addColumn('character_id', Types::INTEGER);
        $characterTag->addColumn('tag_id', Types::INTEGER);
        $characterTag->addColumn('percentage', Types::INTEGER);
        $characterTag->addUniqueIndex(['character_id', 'tag_id'], 'UNIQ_48236C1D1136BE75BAD26311');
        $characterTag->addIndex(['character_id'], 'IDX_48236C1D1136BE75');
        $characterTag->addIndex(['tag_id'], 'IDX_48236C1DBAD26311');
        $characterTag->addForeignKeyConstraint('larp_character', ['character_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_48236C1D1136BE75');
        $characterTag->addForeignKeyConstraint('tag', ['tag_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_48236C1DBAD26311');
    }

    public function down(Schema $schema): void
    {
        foreach (['character_tag', 'larp_character', 'tag', 'tag_category', 'larp_edition', 'larp'] as $table) {
            $schema->dropTable($table);
        }
    }

    private function id(Table $table): void
    {
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->setPrimaryKey(['id']);
    }

    private function timestamps(Table $table): void
    {
        $table->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE);
    }
}
