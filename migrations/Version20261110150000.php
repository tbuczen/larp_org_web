<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Per-LARP social links (JSON rows) and a Facebook event per edition. Schema API
 * only, so it runs on SQLite and MySQL.
 */
final class Version20261110150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'LARP social links and edition Facebook events';
    }

    public function up(Schema $schema): void
    {
        $schema->getTable('larp')->addColumn('social_links', Types::JSON, ['notnull' => false]);
        $schema->getTable('larp_edition')->addColumn('facebook_event_url', Types::STRING, ['length' => 255, 'notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('larp')->dropColumn('social_links');
        $schema->getTable('larp_edition')->dropColumn('facebook_event_url');
    }
}
