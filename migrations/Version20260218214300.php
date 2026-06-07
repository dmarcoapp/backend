<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260218214300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE subscription_change DROP CONSTRAINT fk_89a1e6a733da5175');
        $this->addSql('ALTER TABLE subscription_change DROP CONSTRAINT fk_89a1e6a79a1887dc');
        $this->addSql('ALTER TABLE subscription_change DROP CONSTRAINT fk_89a1e6a79b0cdbcb');
        $this->addSql('ALTER TABLE proration_snapshot DROP CONSTRAINT fk_3168b82333da5175');
        $this->addSql('ALTER TABLE proration_snapshot DROP CONSTRAINT fk_3168b8239a1887dc');
        $this->addSql('ALTER TABLE proration_snapshot DROP CONSTRAINT fk_3168b8239b0cdbcb');
        $this->addSql('DROP TABLE subscription_change');
        $this->addSql('DROP TABLE proration_snapshot');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE subscription_change (id UUID NOT NULL, subscription_id UUID NOT NULL, old_plan_id UUID NOT NULL, new_plan_id UUID NOT NULL, change_type VARCHAR(255) NOT NULL, requested_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, effective_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_89a1e6a733da5175 ON subscription_change (new_plan_id)');
        $this->addSql('CREATE INDEX idx_89a1e6a79a1887dc ON subscription_change (subscription_id)');
        $this->addSql('CREATE INDEX idx_89a1e6a79b0cdbcb ON subscription_change (old_plan_id)');
        $this->addSql('COMMENT ON COLUMN subscription_change.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN subscription_change.subscription_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN subscription_change.old_plan_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN subscription_change.new_plan_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN subscription_change.requested_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN subscription_change.effective_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE proration_snapshot (id UUID NOT NULL, subscription_id UUID NOT NULL, old_plan_id UUID NOT NULL, new_plan_id UUID NOT NULL, period_start TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, period_end TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, change_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, days_in_period INT NOT NULL, days_remaining INT NOT NULL, ratio_remaining DOUBLE PRECISION NOT NULL, amount_cents INT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_3168b82333da5175 ON proration_snapshot (new_plan_id)');
        $this->addSql('CREATE INDEX idx_3168b8239a1887dc ON proration_snapshot (subscription_id)');
        $this->addSql('CREATE INDEX idx_3168b8239b0cdbcb ON proration_snapshot (old_plan_id)');
        $this->addSql('COMMENT ON COLUMN proration_snapshot.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN proration_snapshot.subscription_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN proration_snapshot.old_plan_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN proration_snapshot.new_plan_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN proration_snapshot.period_start IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN proration_snapshot.period_end IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN proration_snapshot.change_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE subscription_change ADD CONSTRAINT fk_89a1e6a733da5175 FOREIGN KEY (new_plan_id) REFERENCES plan (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE subscription_change ADD CONSTRAINT fk_89a1e6a79a1887dc FOREIGN KEY (subscription_id) REFERENCES subscription (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE subscription_change ADD CONSTRAINT fk_89a1e6a79b0cdbcb FOREIGN KEY (old_plan_id) REFERENCES plan (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE proration_snapshot ADD CONSTRAINT fk_3168b82333da5175 FOREIGN KEY (new_plan_id) REFERENCES plan (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE proration_snapshot ADD CONSTRAINT fk_3168b8239a1887dc FOREIGN KEY (subscription_id) REFERENCES subscription (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE proration_snapshot ADD CONSTRAINT fk_3168b8239b0cdbcb FOREIGN KEY (old_plan_id) REFERENCES plan (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
