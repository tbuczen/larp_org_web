<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Edition addresses (/larp/{larp}/{edition}): a slug per edition, unique within
 * its LARP, filled in from the names of existing editions. Schema API only, so it
 * runs on SQLite and MySQL.
 */
final class Version20261110190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Edition slugs';
    }

    public function up(Schema $schema): void
    {
        $edition = $schema->getTable('larp_edition');
        $edition->addColumn('slug', Types::STRING, ['length' => 120, 'notnull' => false]);
        $edition->addUniqueIndex(['larp_id', 'slug'], 'UNIQ_7C331E6563FF2A01989D9B62');
    }

    public function postUp(Schema $schema): void
    {
        $slugger = new AsciiSlugger();
        $taken = [];
        $rows = $this->connection->fetchAllAssociative('SELECT id, larp_id, name FROM larp_edition ORDER BY id');
        foreach ($rows as $row) {
            $base = $slugger->slug((string) $row['name'])->lower()->toString() ?: 'edycja';
            $slug = $base;
            for ($n = 2; isset($taken[$row['larp_id']][$slug]); ++$n) {
                $slug = $base.'-'.$n;
            }
            $taken[$row['larp_id']][$slug] = true;
            $this->connection->update('larp_edition', ['slug' => $slug], ['id' => $row['id']]);
        }
    }

    public function down(Schema $schema): void
    {
        $edition = $schema->getTable('larp_edition');
        $edition->dropIndex('UNIQ_7C331E6563FF2A01989D9B62');
        $edition->dropColumn('slug');
    }
}
