<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260918091027 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop the auth log country column, which only a Cloudflare header ever filled.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE auth_log DROP country_code');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE auth_log ADD country_code VARCHAR(2) DEFAULT NULL');
    }
}
