<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260125210406 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE report DROP CONSTRAINT fk_c42f7784c5341b4c');
        $this->addSql('CREATE TABLE email (id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, from_address VARCHAR(255) NOT NULL, to_address VARCHAR(255) NOT NULL, message_id VARCHAR(255) NOT NULL, attachment_bucket VARCHAR(255) NOT NULL, attachment_key VARCHAR(255) NOT NULL, attachment_filename VARCHAR(255) NOT NULL, attachment_content_type VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN email.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN email.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE postbox_logs DROP CONSTRAINT fk_4f4bdc1bc5341b4c');
        $this->addSql('DROP TABLE postbox');
        $this->addSql('DROP TABLE postbox_logs');
        $this->addSql('DROP INDEX email_lookup_idx');
        $this->addSql('DROP INDEX idx_c42f7784c5341b4c');
        $this->addSql('ALTER TABLE report ADD email_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE report DROP postbox_id');
        $this->addSql('ALTER TABLE report DROP last_checked');
        $this->addSql('ALTER TABLE report DROP last_error');
        $this->addSql('COMMENT ON COLUMN report.email_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE report ADD CONSTRAINT FK_C42F7784A832C1C9 FOREIGN KEY (email_id) REFERENCES email (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C42F7784A832C1C9 ON report (email_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE report DROP CONSTRAINT FK_C42F7784A832C1C9');
        $this->addSql('CREATE TABLE postbox (id UUID NOT NULL, imap_address VARCHAR(255) NOT NULL, imap_port SMALLINT DEFAULT NULL, imap_encryption VARCHAR(255) NOT NULL, username VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, processing_strategy VARCHAR(255) DEFAULT \'soft_delete\' NOT NULL, trash_folder VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN postbox.id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE postbox_logs (id UUID NOT NULL, postbox_id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, level VARCHAR(255) NOT NULL, message VARCHAR(255) NOT NULL, context JSON DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_4f4bdc1bc5341b4c ON postbox_logs (postbox_id)');
        $this->addSql('COMMENT ON COLUMN postbox_logs.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN postbox_logs.postbox_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN postbox_logs.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE postbox_logs ADD CONSTRAINT fk_4f4bdc1bc5341b4c FOREIGN KEY (postbox_id) REFERENCES postbox (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('DROP TABLE email');
        $this->addSql('DROP INDEX UNIQ_C42F7784A832C1C9');
        $this->addSql('ALTER TABLE report ADD postbox_id UUID NOT NULL');
        $this->addSql('ALTER TABLE report ADD last_checked TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE report ADD last_error VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE report DROP email_id');
        $this->addSql('COMMENT ON COLUMN report.postbox_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE report ADD CONSTRAINT fk_c42f7784c5341b4c FOREIGN KEY (postbox_id) REFERENCES postbox (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX email_lookup_idx ON report (postbox_id, email_number) WHERE ((postbox_id IS NOT NULL) AND (email_number IS NOT NULL))');
        $this->addSql('CREATE INDEX idx_c42f7784c5341b4c ON report (postbox_id)');
    }
}
