<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251013185450 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE postbox (id UUID NOT NULL, owner_id UUID NOT NULL, imap_address VARCHAR(255) NOT NULL, imap_port SMALLINT DEFAULT NULL, imap_encryption VARCHAR(255) NOT NULL, username VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, last_checked TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_25618E267E3C61F9 ON postbox (owner_id)');
        $this->addSql('COMMENT ON COLUMN postbox.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN postbox.owner_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE postbox ADD CONSTRAINT FK_25618E267E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE postbox DROP CONSTRAINT FK_25618E267E3C61F9');
        $this->addSql('DROP TABLE postbox');
    }
}
