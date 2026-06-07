<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260206224808 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE proration_snapshot (id UUID NOT NULL, subscription_id UUID NOT NULL, old_plan_id UUID NOT NULL, new_plan_id UUID NOT NULL, period_start TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, period_end TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, change_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, days_in_period INT NOT NULL, days_remaining INT NOT NULL, ratio_remaining DOUBLE PRECISION NOT NULL, amount_cents INT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_3168B8239A1887DC ON proration_snapshot (subscription_id)');
        $this->addSql('CREATE INDEX IDX_3168B8239B0CDBCB ON proration_snapshot (old_plan_id)');
        $this->addSql('CREATE INDEX IDX_3168B82333DA5175 ON proration_snapshot (new_plan_id)');
        $this->addSql('COMMENT ON COLUMN proration_snapshot.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN proration_snapshot.subscription_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN proration_snapshot.old_plan_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN proration_snapshot.new_plan_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN proration_snapshot.period_start IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN proration_snapshot.period_end IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN proration_snapshot.change_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE subscription_change (id UUID NOT NULL, subscription_id UUID NOT NULL, old_plan_id UUID NOT NULL, new_plan_id UUID NOT NULL, change_type VARCHAR(255) NOT NULL, requested_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, effective_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_89A1E6A79A1887DC ON subscription_change (subscription_id)');
        $this->addSql('CREATE INDEX IDX_89A1E6A79B0CDBCB ON subscription_change (old_plan_id)');
        $this->addSql('CREATE INDEX IDX_89A1E6A733DA5175 ON subscription_change (new_plan_id)');
        $this->addSql('COMMENT ON COLUMN subscription_change.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN subscription_change.subscription_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN subscription_change.old_plan_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN subscription_change.new_plan_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN subscription_change.requested_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN subscription_change.effective_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE proration_snapshot ADD CONSTRAINT FK_3168B8239A1887DC FOREIGN KEY (subscription_id) REFERENCES subscription (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE proration_snapshot ADD CONSTRAINT FK_3168B8239B0CDBCB FOREIGN KEY (old_plan_id) REFERENCES plan (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE proration_snapshot ADD CONSTRAINT FK_3168B82333DA5175 FOREIGN KEY (new_plan_id) REFERENCES plan (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE subscription_change ADD CONSTRAINT FK_89A1E6A79A1887DC FOREIGN KEY (subscription_id) REFERENCES subscription (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE subscription_change ADD CONSTRAINT FK_89A1E6A79B0CDBCB FOREIGN KEY (old_plan_id) REFERENCES plan (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE subscription_change ADD CONSTRAINT FK_89A1E6A733DA5175 FOREIGN KEY (new_plan_id) REFERENCES plan (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE subscription ADD pending_plan_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE subscription ADD current_period_start TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE subscription ADD current_period_end TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE subscription ADD pending_effective_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN subscription.pending_plan_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN subscription.current_period_start IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN subscription.current_period_end IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN subscription.pending_effective_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE subscription ADD CONSTRAINT FK_A3C664D323F6F50A FOREIGN KEY (pending_plan_id) REFERENCES plan (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_A3C664D323F6F50A ON subscription (pending_plan_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE proration_snapshot DROP CONSTRAINT FK_3168B8239A1887DC');
        $this->addSql('ALTER TABLE proration_snapshot DROP CONSTRAINT FK_3168B8239B0CDBCB');
        $this->addSql('ALTER TABLE proration_snapshot DROP CONSTRAINT FK_3168B82333DA5175');
        $this->addSql('ALTER TABLE subscription_change DROP CONSTRAINT FK_89A1E6A79A1887DC');
        $this->addSql('ALTER TABLE subscription_change DROP CONSTRAINT FK_89A1E6A79B0CDBCB');
        $this->addSql('ALTER TABLE subscription_change DROP CONSTRAINT FK_89A1E6A733DA5175');
        $this->addSql('DROP TABLE proration_snapshot');
        $this->addSql('DROP TABLE subscription_change');
        $this->addSql('ALTER TABLE subscription DROP CONSTRAINT FK_A3C664D323F6F50A');
        $this->addSql('DROP INDEX IDX_A3C664D323F6F50A');
        $this->addSql('ALTER TABLE subscription DROP pending_plan_id');
        $this->addSql('ALTER TABLE subscription DROP current_period_start');
        $this->addSql('ALTER TABLE subscription DROP current_period_end');
        $this->addSql('ALTER TABLE subscription DROP pending_effective_at');
    }
}
