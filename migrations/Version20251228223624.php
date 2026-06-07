<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251228223624 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE domain (id UUID NOT NULL, user_id UUID NOT NULL, domain VARCHAR(255) NOT NULL, last_checked TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, dmarc_record TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_A7A91E0BA76ED395 ON domain (user_id)');
        $this->addSql('COMMENT ON COLUMN domain.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN domain.user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN domain.last_checked IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE domain ADD CONSTRAINT FK_A7A91E0BA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE domain DROP CONSTRAINT FK_A7A91E0BA76ED395');
        $this->addSql('DROP TABLE domain');
    }
}
