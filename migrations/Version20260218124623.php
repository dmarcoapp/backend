<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260218124623 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Paddle metadata columns for plans/subscriptions and billing webhook idempotency table.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE billing_webhook_event (id UUID NOT NULL, event_id VARCHAR(255) NOT NULL, event_type VARCHAR(255) NOT NULL, payload TEXT NOT NULL, processed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_billing_webhook_event_id ON billing_webhook_event (event_id)');
        $this->addSql('COMMENT ON COLUMN billing_webhook_event.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN billing_webhook_event.processed_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE plan ADD paddle_price_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_DD5A5B7D569E0D49 ON plan (paddle_price_id)');
        $this->addSql('ALTER TABLE subscription ADD paddle_customer_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE subscription ADD paddle_subscription_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE subscription ADD paddle_status VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE subscription ADD paddle_scheduled_change_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN subscription.paddle_scheduled_change_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A3C664D32BF00C35 ON subscription (paddle_subscription_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE billing_webhook_event');
        $this->addSql('DROP INDEX UNIQ_DD5A5B7D569E0D49');
        $this->addSql('ALTER TABLE plan DROP paddle_price_id');
        $this->addSql('DROP INDEX UNIQ_A3C664D32BF00C35');
        $this->addSql('ALTER TABLE subscription DROP paddle_customer_id');
        $this->addSql('ALTER TABLE subscription DROP paddle_subscription_id');
        $this->addSql('ALTER TABLE subscription DROP paddle_status');
        $this->addSql('ALTER TABLE subscription DROP paddle_scheduled_change_at');
    }
}
