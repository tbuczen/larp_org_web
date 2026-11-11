<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Characters get an optional in-game name ("Emily Jones / Eddy Jones") next to
 * their vacancy name ("Inżynier", the existing `name`). Only adds a nullable
 * column: existing characters keep working unchanged. Schema API only, so it
 * runs on SQLite and MySQL.
 */
final class Version20261111120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Character in-game name';
    }

    public function up(Schema $schema): void
    {
        $schema->getTable('larp_character')->addColumn('character_name', Types::STRING, ['length' => 160, 'notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('larp_character')->dropColumn('character_name');
    }
}
