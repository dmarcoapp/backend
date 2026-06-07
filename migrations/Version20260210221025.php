<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260210221025 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE email_blocklist_entry (id UUID NOT NULL, user_id UUID NOT NULL, pattern VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_blocklist_user ON email_blocklist_entry (user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_blocklist_pattern ON email_blocklist_entry (user_id, pattern)');
        $this->addSql('COMMENT ON COLUMN email_blocklist_entry.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN email_blocklist_entry.user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN email_blocklist_entry.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE email_blocklist_entry ADD CONSTRAINT FK_6DA1AD73A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE email_blocklist_entry DROP CONSTRAINT FK_6DA1AD73A76ED395');
        $this->addSql('DROP TABLE email_blocklist_entry');
    }
}
