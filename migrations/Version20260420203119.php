<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260420203119 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove billing, subscription, Paddle webhook, and token ledger schema.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "user" DROP CONSTRAINT fk_8d93d6499a1887dc');
        $this->addSql('ALTER TABLE usage_balance DROP CONSTRAINT fk_f27069e1a76ed395');
        $this->addSql('ALTER TABLE usage_ledger_entry DROP CONSTRAINT fk_ac716fca76ed395');
        $this->addSql('ALTER TABLE subscription DROP CONSTRAINT fk_a3c664d3cadd44d4');
        $this->addSql('ALTER TABLE subscription DROP CONSTRAINT fk_a3c664d3e899029b');
        $this->addSql('DROP TABLE billing_webhook_event');
        $this->addSql('DROP TABLE usage_balance');
        $this->addSql('DROP TABLE usage_ledger_entry');
        $this->addSql('DROP TABLE plan');
        $this->addSql('DROP TABLE subscription');
        $this->addSql('DROP INDEX uniq_8d93d6499a1887dc');
        $this->addSql('ALTER TABLE "user" DROP subscription_id');
        $this->addSql('ALTER TABLE "user" DROP daily_report_processing_limit_reached_notification_enabled');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE billing_webhook_event (id UUID NOT NULL, event_id VARCHAR(255) NOT NULL, event_type VARCHAR(255) NOT NULL, payload TEXT NOT NULL, processed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_billing_webhook_event_id ON billing_webhook_event (event_id)');
        $this->addSql('COMMENT ON COLUMN billing_webhook_event.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN billing_webhook_event.processed_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE usage_balance (id UUID NOT NULL, user_id UUID NOT NULL, daily_remaining INT NOT NULL, extra_remaining INT NOT NULL, last_daily_reset_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_f27069e1a76ed395 ON usage_balance (user_id)');
        $this->addSql('COMMENT ON COLUMN usage_balance.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN usage_balance.user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN usage_balance.last_daily_reset_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE usage_ledger_entry (id UUID NOT NULL, user_id UUID NOT NULL, type VARCHAR(255) NOT NULL, amount INT NOT NULL, balance_daily_after INT NOT NULL, balance_extra_after INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, note VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_ac716fca76ed395 ON usage_ledger_entry (user_id)');
        $this->addSql('CREATE INDEX idx_usage_ledger_user_created_at ON usage_ledger_entry (user_id, created_at)');
        $this->addSql('COMMENT ON COLUMN usage_ledger_entry.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN usage_ledger_entry.user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN usage_ledger_entry.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE plan (id UUID NOT NULL, name VARCHAR(255) NOT NULL, daily_limit INT NOT NULL, retention_days INT DEFAULT NULL, blocklist_limit INT NOT NULL, paddle_price_id VARCHAR(255) DEFAULT NULL, magnitude INT DEFAULT 0 NOT NULL, domain_limit INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_dd5a5b7d569e0d49 ON plan (paddle_price_id)');
        $this->addSql('COMMENT ON COLUMN plan.id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE subscription (id UUID NOT NULL, plan_id UUID NOT NULL, paddle_scheduled_change_target_plan_id UUID DEFAULT NULL, current_period_start TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, current_period_end TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, missed_reports_count INT DEFAULT 0 NOT NULL, paddle_customer_id VARCHAR(255) DEFAULT NULL, paddle_subscription_id VARCHAR(255) DEFAULT NULL, paddle_status VARCHAR(64) DEFAULT NULL, paddle_scheduled_change_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, paddle_scheduled_change_action VARCHAR(32) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_a3c664d3cadd44d4 ON subscription (paddle_scheduled_change_target_plan_id)');
        $this->addSql('CREATE INDEX idx_a3c664d3e899029b ON subscription (plan_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_a3c664d32bf00c35 ON subscription (paddle_subscription_id)');
        $this->addSql('COMMENT ON COLUMN subscription.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN subscription.plan_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN subscription.paddle_scheduled_change_target_plan_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN subscription.current_period_start IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN subscription.current_period_end IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN subscription.paddle_scheduled_change_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE usage_balance ADD CONSTRAINT fk_f27069e1a76ed395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE usage_ledger_entry ADD CONSTRAINT fk_ac716fca76ed395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE subscription ADD CONSTRAINT fk_a3c664d3cadd44d4 FOREIGN KEY (paddle_scheduled_change_target_plan_id) REFERENCES plan (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE subscription ADD CONSTRAINT fk_a3c664d3e899029b FOREIGN KEY (plan_id) REFERENCES plan (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "user" ADD subscription_id UUID NOT NULL');
        $this->addSql('ALTER TABLE "user" ADD daily_report_processing_limit_reached_notification_enabled BOOLEAN DEFAULT true NOT NULL');
        $this->addSql('COMMENT ON COLUMN "user".subscription_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE "user" ADD CONSTRAINT fk_8d93d6499a1887dc FOREIGN KEY (subscription_id) REFERENCES subscription (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX uniq_8d93d6499a1887dc ON "user" (subscription_id)');
    }
}
