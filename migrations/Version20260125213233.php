<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260125213233 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE email ADD owner_id UUID NOT NULL');
        $this->addSql('COMMENT ON COLUMN email.owner_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE email ADD CONSTRAINT FK_E7927C747E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_E7927C747E3C61F9 ON email (owner_id)');
        $this->addSql('CREATE INDEX idx_email_message_id_address ON email (message_id, to_address)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE email DROP CONSTRAINT FK_E7927C747E3C61F9');
        $this->addSql('DROP INDEX IDX_E7927C747E3C61F9');
        $this->addSql('DROP INDEX idx_email_message_id_address');
        $this->addSql('ALTER TABLE email DROP owner_id');
    }
}
