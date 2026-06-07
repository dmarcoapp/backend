<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260218233725 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE subscription ADD paddle_scheduled_change_target_plan_id UUID DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN subscription.paddle_scheduled_change_target_plan_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE subscription ADD CONSTRAINT FK_A3C664D3CADD44D4 FOREIGN KEY (paddle_scheduled_change_target_plan_id) REFERENCES plan (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_A3C664D3CADD44D4 ON subscription (paddle_scheduled_change_target_plan_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE subscription DROP CONSTRAINT FK_A3C664D3CADD44D4');
        $this->addSql('DROP INDEX IDX_A3C664D3CADD44D4');
        $this->addSql('ALTER TABLE subscription DROP paddle_scheduled_change_target_plan_id');
    }
}
