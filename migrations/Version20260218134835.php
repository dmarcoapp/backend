<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260218134835 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove legacy local subscription scheduling columns in favor of Paddle-only lifecycle.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscription DROP CONSTRAINT fk_a3c664d323f6f50a');
        $this->addSql('DROP INDEX idx_a3c664d323f6f50a');
        $this->addSql('ALTER TABLE subscription DROP pending_plan_id');
        $this->addSql('ALTER TABLE subscription DROP pending_effective_at');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscription ADD pending_plan_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE subscription ADD pending_effective_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN subscription.pending_plan_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN subscription.pending_effective_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE subscription ADD CONSTRAINT fk_a3c664d323f6f50a FOREIGN KEY (pending_plan_id) REFERENCES plan (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_a3c664d323f6f50a ON subscription (pending_plan_id)');
    }
}
