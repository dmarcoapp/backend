<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251014111200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE report (id UUID NOT NULL, postbox_id UUID NOT NULL, email_number VARCHAR(255) NOT NULL, last_checked TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, last_error VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_C42F7784C5341B4C ON report (postbox_id)');
        $this->addSql('COMMENT ON COLUMN report.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN report.postbox_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE report ADD CONSTRAINT FK_C42F7784C5341B4C FOREIGN KEY (postbox_id) REFERENCES postbox (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE postbox ADD last_error TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE report DROP CONSTRAINT FK_C42F7784C5341B4C');
        $this->addSql('DROP TABLE report');
        $this->addSql('ALTER TABLE postbox DROP last_error');
    }
}
