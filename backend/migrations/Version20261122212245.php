<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261122212245 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'P5: contact_messages';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE contact_messages (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(254) NOT NULL,
                message TEXT NOT NULL,
                ip VARBINARY(16) NULL COMMENT 'inet_pton() bytes; REMOTE_ADDR only',
                created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
                PRIMARY KEY (id),
                KEY idx_contact_messages_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE contact_messages');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
