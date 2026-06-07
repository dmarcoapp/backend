<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260215145641 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user notification preference flags for login and daily report limit emails.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD daily_report_processing_limit_reached_notification_enabled BOOLEAN DEFAULT true NOT NULL');
        $this->addSql('ALTER TABLE "user" ADD unusual_new_login_notification_enabled BOOLEAN DEFAULT true NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP daily_report_processing_limit_reached_notification_enabled');
        $this->addSql('ALTER TABLE "user" DROP unusual_new_login_notification_enabled');
    }
}
