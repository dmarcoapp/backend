<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120125214 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fetch reports from mailboxes over IMAP, take them through the API with keys, and record how each report arrived';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE api_intake_key (id UUID NOT NULL, name VARCHAR(100) NOT NULL, lookup_id VARCHAR(16) NOT NULL, secret_hash VARCHAR(64) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_api_intake_key_lookup ON api_intake_key (lookup_id)');
        $this->addSql('COMMENT ON COLUMN api_intake_key.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN api_intake_key.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN api_intake_key.last_used_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE mailbox (id UUID NOT NULL, name VARCHAR(100) NOT NULL, host VARCHAR(255) NOT NULL, port INT NOT NULL, security VARCHAR(16) NOT NULL, username VARCHAR(255) NOT NULL, auth_method VARCHAR(16) NOT NULL, credentials TEXT NOT NULL, folder VARCHAR(255) NOT NULL, report_address VARCHAR(255) DEFAULT NULL, after_import VARCHAR(16) NOT NULL, move_to_folder VARCHAR(255) DEFAULT NULL, enabled BOOLEAN DEFAULT true NOT NULL, poll_interval_minutes INT DEFAULT 15 NOT NULL, first_import_days INT DEFAULT 30 NOT NULL, uid_validity BIGINT DEFAULT NULL, last_uid BIGINT DEFAULT NULL, last_polled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, last_success_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, last_error TEXT DEFAULT NULL, consecutive_failures INT DEFAULT 0 NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN mailbox.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN mailbox.last_polled_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN mailbox.last_success_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN mailbox.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE mailbox_log_entry (id UUID NOT NULL, mailbox_id UUID NOT NULL, level VARCHAR(16) NOT NULL, message VARCHAR(500) NOT NULL, context JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_7838C5A366EC35CC ON mailbox_log_entry (mailbox_id)');
        $this->addSql('CREATE INDEX idx_mailbox_log_entry_mailbox_created ON mailbox_log_entry (mailbox_id, created_at)');
        $this->addSql('COMMENT ON COLUMN mailbox_log_entry.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN mailbox_log_entry.mailbox_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN mailbox_log_entry.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE mailbox_log_entry ADD CONSTRAINT FK_7838C5A366EC35CC FOREIGN KEY (mailbox_id) REFERENCES mailbox (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE email ADD mailbox_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE email ADD api_key_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE email ADD source VARCHAR(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE email ADD imap_uid BIGINT DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN email.mailbox_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN email.api_key_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE email ADD CONSTRAINT FK_E7927C7466EC35CC FOREIGN KEY (mailbox_id) REFERENCES mailbox (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE email ADD CONSTRAINT FK_E7927C748BE312B3 FOREIGN KEY (api_key_id) REFERENCES api_intake_key (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_E7927C7466EC35CC ON email (mailbox_id)');
        $this->addSql('CREATE INDEX IDX_E7927C748BE312B3 ON email (api_key_id)');
        // Every report so far came through the mail gateway.
        $this->addSql('UPDATE email SET source = \'gateway\'');
        $this->addSql('ALTER TABLE platform_settings ADD api_intake_enabled BOOLEAN DEFAULT NULL');
        $this->addSql('ALTER TABLE platform_settings ADD gateway_secret TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE email DROP CONSTRAINT FK_E7927C748BE312B3');
        $this->addSql('ALTER TABLE email DROP CONSTRAINT FK_E7927C7466EC35CC');
        $this->addSql('ALTER TABLE mailbox_log_entry DROP CONSTRAINT FK_7838C5A366EC35CC');
        $this->addSql('DROP TABLE api_intake_key');
        $this->addSql('DROP TABLE mailbox');
        $this->addSql('DROP TABLE mailbox_log_entry');
        $this->addSql('DROP INDEX IDX_E7927C7466EC35CC');
        $this->addSql('DROP INDEX IDX_E7927C748BE312B3');
        $this->addSql('ALTER TABLE email DROP mailbox_id');
        $this->addSql('ALTER TABLE email DROP api_key_id');
        $this->addSql('ALTER TABLE email DROP source');
        $this->addSql('ALTER TABLE email DROP imap_uid');
        $this->addSql('ALTER TABLE platform_settings DROP api_intake_enabled');
        $this->addSql('ALTER TABLE platform_settings DROP gateway_secret');
    }
}
