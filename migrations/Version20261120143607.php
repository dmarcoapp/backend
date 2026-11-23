<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120143607 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Switch the upload endpoint and the webhook on and off on their own, and name the ways in alike everywhere';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE platform_settings ADD upload_endpoint_enabled BOOLEAN DEFAULT NULL');
        $this->addSql('ALTER TABLE platform_settings ADD webhook_enabled BOOLEAN DEFAULT NULL');

        // Until now the API switch covered both, and a missing key or secret
        // was what kept one of them closed.
        $this->addSql('UPDATE platform_settings SET webhook_enabled = TRUE WHERE api_intake_enabled AND gateway_secret IS NOT NULL');
        $this->addSql('UPDATE platform_settings SET upload_endpoint_enabled = TRUE WHERE api_intake_enabled AND EXISTS (SELECT 1 FROM api_intake_key)');

        // The Platform page names the three ways in mailbox, upload endpoint,
        // and webhook, and so does what is stored.
        $this->addSql('ALTER TABLE platform_settings RENAME COLUMN gateway_secret TO webhook_secret');
        $this->addSql("UPDATE email SET source = 'webhook' WHERE source = 'gateway'");
        $this->addSql("UPDATE email SET source = 'upload' WHERE source = 'api'");
        $this->addSql("UPDATE email SET source = 'mailbox' WHERE source = 'imap'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE email SET source = 'gateway' WHERE source = 'webhook'");
        $this->addSql("UPDATE email SET source = 'api' WHERE source = 'upload'");
        $this->addSql("UPDATE email SET source = 'imap' WHERE source = 'mailbox'");
        $this->addSql('ALTER TABLE platform_settings RENAME COLUMN webhook_secret TO gateway_secret');
        $this->addSql('ALTER TABLE platform_settings DROP upload_endpoint_enabled');
        $this->addSql('ALTER TABLE platform_settings DROP webhook_enabled');
    }
}
