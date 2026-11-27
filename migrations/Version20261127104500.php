<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261127104500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remember when the newest report for each domain arrived, and name the channel of the reports stored before channels were recorded';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE domain ADD last_report_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN domain.last_report_at IS \'(DC2Type:datetime_immutable)\'');

        // Reports already stored tell when they arrived through their email.
        $this->addSql('UPDATE domain SET last_report_at = (SELECT MAX(e.created_at) FROM report r INNER JOIN email e ON e.id = r.email_id WHERE r.domain = domain.domain)');

        // Emails stored before the channel was recorded all came through the
        // mail gateway, which posts to the webhook.
        $this->addSql("UPDATE email SET source = 'webhook' WHERE source IS NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE domain DROP last_report_at');
    }
}
