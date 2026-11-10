<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Per-LARP colour scheme: a preset and own colours. Schema API only, so it runs
 * on SQLite and MySQL.
 */
final class Version20261110170000 extends AbstractMigration
{
    private const COLORS = ['color_background', 'color_text', 'color_primary', 'color_secondary', 'color_accent'];

    public function getDescription(): string
    {
        return 'LARP colour schemes';
    }

    public function up(Schema $schema): void
    {
        $larp = $schema->getTable('larp');
        $larp->addColumn('color_preset', Types::STRING, ['length' => 40, 'notnull' => false]);
        foreach (self::COLORS as $column) {
            $larp->addColumn($column, Types::STRING, ['length' => 7, 'notnull' => false]);
        }
    }

    public function down(Schema $schema): void
    {
        $larp = $schema->getTable('larp');
        $larp->dropColumn('color_preset');
        foreach (self::COLORS as $column) {
            $larp->dropColumn($column);
        }
    }
}
