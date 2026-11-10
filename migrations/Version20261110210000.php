<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Editions as events of their own: teaser, description, cover image, player
 * count and price per edition. Schema API only, so it runs on SQLite and MySQL.
 */
final class Version20261110210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Edition texts and cover';
    }

    public function up(Schema $schema): void
    {
        $edition = $schema->getTable('larp_edition');
        $edition->addColumn('teaser', Types::STRING, ['length' => 500, 'notnull' => false]);
        $edition->addColumn('description', Types::TEXT, ['notnull' => false]);
        $edition->addColumn('cover_image', Types::STRING, ['length' => 255, 'notnull' => false]);
        $edition->addColumn('cover_image_alt', Types::STRING, ['length' => 255, 'notnull' => false]);
        $edition->addColumn('player_count', Types::STRING, ['length' => 120, 'notnull' => false]);
        $edition->addColumn('price', Types::STRING, ['length' => 120, 'notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $edition = $schema->getTable('larp_edition');
        foreach (['teaser', 'description', 'cover_image', 'cover_image_alt', 'player_count', 'price'] as $column) {
            $edition->dropColumn($column);
        }
    }
}
