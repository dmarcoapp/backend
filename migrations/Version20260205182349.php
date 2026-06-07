<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260205182349 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE usage_balance (id UUID NOT NULL, user_id UUID NOT NULL, daily_remaining INT NOT NULL, extra_remaining INT NOT NULL, last_daily_reset_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F27069E1A76ED395 ON usage_balance (user_id)');
        $this->addSql('COMMENT ON COLUMN usage_balance.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN usage_balance.user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN usage_balance.last_daily_reset_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE usage_ledger_entry (id UUID NOT NULL, user_id UUID NOT NULL, type VARCHAR(255) NOT NULL, amount INT NOT NULL, balance_daily_after INT NOT NULL, balance_extra_after INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, note VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_AC716FCA76ED395 ON usage_ledger_entry (user_id)');
        $this->addSql('CREATE INDEX idx_usage_ledger_user_created_at ON usage_ledger_entry (user_id, created_at)');
        $this->addSql('COMMENT ON COLUMN usage_ledger_entry.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN usage_ledger_entry.user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN usage_ledger_entry.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE usage_balance ADD CONSTRAINT FK_F27069E1A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE usage_ledger_entry ADD CONSTRAINT FK_AC716FCA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE usage_balance DROP CONSTRAINT FK_F27069E1A76ED395');
        $this->addSql('ALTER TABLE usage_ledger_entry DROP CONSTRAINT FK_AC716FCA76ED395');
        $this->addSql('DROP TABLE usage_balance');
        $this->addSql('DROP TABLE usage_ledger_entry');
    }
}
