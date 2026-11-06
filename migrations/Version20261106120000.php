<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Block-based pages and editable menus. Schema API only, so it runs on SQLite and MySQL.
 */
final class Version20261106120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Pages, page blocks, menus and menu items';
    }

    public function up(Schema $schema): void
    {
        $page = $schema->createTable('page');
        $this->id($page);
        $page->addColumn('title', Types::STRING, ['length' => 160]);
        $page->addColumn('slug', Types::STRING, ['length' => 180]);
        $page->addColumn('is_home', Types::BOOLEAN);
        $page->addColumn('published', Types::BOOLEAN);
        $page->addColumn('meta_description', Types::STRING, ['length' => 300, 'notnull' => false]);
        $this->timestamps($page);
        $page->addUniqueIndex(['slug'], 'UNIQ_140AB620989D9B62');

        $block = $schema->createTable('page_block');
        $this->id($block);
        $block->addColumn('page_id', Types::INTEGER);
        $block->addColumn('type', Types::STRING, ['length' => 50]);
        $block->addColumn('admin_label', Types::STRING, ['length' => 120, 'notnull' => false]);
        $block->addColumn('position', Types::INTEGER);
        $block->addColumn('enabled', Types::BOOLEAN);
        $block->addColumn('settings', Types::JSON);
        $this->timestamps($block);
        $block->addIndex(['page_id'], 'IDX_E59A68F4C4663E4');
        $block->addForeignKeyConstraint('page', ['page_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_E59A68F4C4663E4');

        $menu = $schema->createTable('menu');
        $this->id($menu);
        $menu->addColumn('menu_key', Types::STRING, ['length' => 40]);
        $menu->addColumn('name', Types::STRING, ['length' => 120]);
        $menu->addUniqueIndex(['menu_key'], 'UNIQ_7D053A931231659B');

        $item = $schema->createTable('menu_item');
        $this->id($item);
        $item->addColumn('menu_id', Types::INTEGER);
        $item->addColumn('parent_id', Types::INTEGER, ['notnull' => false]);
        $item->addColumn('page_id', Types::INTEGER, ['notnull' => false]);
        $item->addColumn('larp_id', Types::INTEGER, ['notnull' => false]);
        $item->addColumn('label', Types::STRING, ['length' => 80]);
        $item->addColumn('target', Types::STRING, ['length' => 10]);
        $item->addColumn('route', Types::STRING, ['length' => 60, 'notnull' => false]);
        $item->addColumn('url', Types::STRING, ['length' => 255, 'notnull' => false]);
        $item->addColumn('new_tab', Types::BOOLEAN);
        $item->addColumn('enabled', Types::BOOLEAN);
        $item->addColumn('position', Types::INTEGER);
        $item->addIndex(['menu_id'], 'IDX_D754D550CCD7E912');
        $item->addIndex(['parent_id'], 'IDX_D754D550727ACA70');
        $item->addIndex(['page_id'], 'IDX_D754D550C4663E4');
        $item->addIndex(['larp_id'], 'IDX_D754D55063FF2A01');
        $item->addForeignKeyConstraint('menu', ['menu_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_D754D550CCD7E912');
        $item->addForeignKeyConstraint('menu_item', ['parent_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_D754D550727ACA70');
        $item->addForeignKeyConstraint('page', ['page_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_D754D550C4663E4');
        $item->addForeignKeyConstraint('larp', ['larp_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_D754D55063FF2A01');
    }

    public function down(Schema $schema): void
    {
        foreach (['menu_item', 'menu', 'page_block', 'page'] as $table) {
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
