<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251013153646 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_RESET_PASSWORD_TOKEN');
        $this->addSql('ALTER TABLE "user" ADD password_reset_token_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" RENAME COLUMN reset_password_token TO password_reset_token');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_RESET_PASSWORD_TOKEN ON "user" (password_reset_token)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX uniq_reset_password_token');
        $this->addSql('ALTER TABLE "user" DROP password_reset_token_expires_at');
        $this->addSql('ALTER TABLE "user" RENAME COLUMN password_reset_token TO reset_password_token');
        $this->addSql('CREATE UNIQUE INDEX uniq_reset_password_token ON "user" (reset_password_token)');
    }
}
