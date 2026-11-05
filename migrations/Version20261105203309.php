<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Written with the Schema API instead of raw SQL so the same migration runs on
 * SQLite and MySQL/MariaDB. Keep later migrations portable in the same way.
 */
final class Version20261105203309 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Users, site settings and the Messenger queue table';
    }

    public function up(Schema $schema): void
    {
        $user = $schema->createTable('app_user');
        $user->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $user->addColumn('email', Types::STRING, ['length' => 180]);
        $user->addColumn('display_name', Types::STRING, ['length' => 120, 'notnull' => false]);
        $user->addColumn('roles', Types::JSON);
        $user->addColumn('password', Types::STRING, ['length' => 255]);
        $user->addColumn('enabled', Types::BOOLEAN);
        $user->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $user->setPrimaryKey(['id']);
        $user->addUniqueIndex(['email'], 'UNIQ_88BDF3E9E7927C74');

        $settings = $schema->createTable('site_settings');
        $settings->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $settings->addColumn('organization_name', Types::STRING, ['length' => 160]);
        $settings->addColumn('tagline', Types::STRING, ['length' => 255, 'notnull' => false]);
        $settings->addColumn('about', Types::TEXT, ['notnull' => false]);
        $settings->addColumn('contact_email', Types::STRING, ['length' => 180, 'notnull' => false]);
        $settings->addColumn('facebook_url', Types::STRING, ['length' => 255, 'notnull' => false]);
        $settings->addColumn('instagram_url', Types::STRING, ['length' => 255, 'notnull' => false]);
        $settings->addColumn('discord_url', Types::STRING, ['length' => 255, 'notnull' => false]);
        $settings->addColumn('theme', Types::STRING, ['length' => 64]);
        $settings->setPrimaryKey(['id']);

        $messages = $schema->createTable('messenger_messages');
        $messages->addColumn('id', Types::BIGINT, ['autoincrement' => true]);
        $messages->addColumn('body', Types::TEXT);
        $messages->addColumn('headers', Types::TEXT);
        $messages->addColumn('queue_name', Types::STRING, ['length' => 190]);
        $messages->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $messages->addColumn('available_at', Types::DATETIME_IMMUTABLE);
        $messages->addColumn('delivered_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $messages->setPrimaryKey(['id']);
        $messages->addIndex(['queue_name', 'available_at', 'delivered_at', 'id'], 'IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('messenger_messages');
        $schema->dropTable('site_settings');
        $schema->dropTable('app_user');
    }
}
