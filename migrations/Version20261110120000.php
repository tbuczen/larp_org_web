<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Photo galleries (per LARP / edition, or standalone): usually a link to an album
 * elsewhere with a cover image, optionally with photos uploaded here. Schema API
 * only, so it runs on SQLite and MySQL.
 */
final class Version20261110120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Photo galleries';
    }

    public function up(Schema $schema): void
    {
        $gallery = $schema->createTable('gallery');
        $gallery->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $gallery->addColumn('larp_id', Types::INTEGER, ['notnull' => false]);
        $gallery->addColumn('edition_id', Types::INTEGER, ['notnull' => false]);
        $gallery->addColumn('title', Types::STRING, ['length' => 160]);
        $gallery->addColumn('slug', Types::STRING, ['length' => 180]);
        $gallery->addColumn('external_url', Types::STRING, ['length' => 500, 'notnull' => false]);
        $gallery->addColumn('cover_image', Types::STRING, ['length' => 255, 'notnull' => false]);
        $gallery->addColumn('photographer', Types::STRING, ['length' => 160, 'notnull' => false]);
        $gallery->addColumn('event_date', Types::DATE_IMMUTABLE, ['notnull' => false]);
        $gallery->addColumn('description', Types::TEXT, ['notnull' => false]);
        $gallery->addColumn('published', Types::BOOLEAN);
        $gallery->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $gallery->addColumn('updated_at', Types::DATETIME_IMMUTABLE);
        $gallery->setPrimaryKey(['id']);
        $gallery->addUniqueIndex(['slug'], 'UNIQ_472B783A989D9B62');
        $gallery->addIndex(['larp_id'], 'IDX_472B783A63FF2A01');
        $gallery->addIndex(['edition_id'], 'IDX_472B783A74281A5E');
        $gallery->addForeignKeyConstraint('larp', ['larp_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_472B783A63FF2A01');
        $gallery->addForeignKeyConstraint('larp_edition', ['edition_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_472B783A74281A5E');

        $photo = $schema->createTable('photo');
        $photo->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $photo->addColumn('gallery_id', Types::INTEGER);
        $photo->addColumn('file', Types::STRING, ['length' => 255]);
        $photo->addColumn('thumbnail', Types::STRING, ['length' => 255, 'notnull' => false]);
        $photo->addColumn('width', Types::INTEGER);
        $photo->addColumn('height', Types::INTEGER);
        $photo->addColumn('caption', Types::STRING, ['length' => 500, 'notnull' => false]);
        $photo->addColumn('photographer', Types::STRING, ['length' => 160, 'notnull' => false]);
        $photo->addColumn('position', Types::INTEGER);
        $photo->setPrimaryKey(['id']);
        $photo->addIndex(['gallery_id'], 'IDX_14B784184E7AF8F');
        $photo->addForeignKeyConstraint('gallery', ['gallery_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_14B784184E7AF8F');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('photo');
        $schema->dropTable('gallery');
    }
}
