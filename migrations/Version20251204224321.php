<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251204224321 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE postbox DROP CONSTRAINT fk_25618e267e3c61f9');
        $this->addSql('DROP INDEX idx_25618e267e3c61f9');
        $this->addSql('ALTER TABLE postbox ADD type VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE postbox DROP owner_id');
        $this->addSql('ALTER TABLE report DROP CONSTRAINT fk_c42f7784c5341b4c');
        $this->addSql('DROP INDEX idx_c42f7784c5341b4c');
        $this->addSql('ALTER TABLE report ADD user_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE report DROP postbox_id');
        $this->addSql('COMMENT ON COLUMN report.user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE report ADD CONSTRAINT FK_C42F7784A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_C42F7784A76ED395 ON report (user_id)');
        $this->addSql('ALTER TABLE "user" ADD shared_postbox_identifier_token VARCHAR(32) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D6497AE42377 ON "user" (shared_postbox_identifier_token)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE report DROP CONSTRAINT FK_C42F7784A76ED395');
        $this->addSql('DROP INDEX IDX_C42F7784A76ED395');
        $this->addSql('ALTER TABLE report ADD postbox_id UUID NOT NULL');
        $this->addSql('ALTER TABLE report DROP user_id');
        $this->addSql('COMMENT ON COLUMN report.postbox_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE report ADD CONSTRAINT fk_c42f7784c5341b4c FOREIGN KEY (postbox_id) REFERENCES postbox (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_c42f7784c5341b4c ON report (postbox_id)');
        $this->addSql('ALTER TABLE postbox ADD owner_id UUID NOT NULL');
        $this->addSql('ALTER TABLE postbox DROP type');
        $this->addSql('COMMENT ON COLUMN postbox.owner_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE postbox ADD CONSTRAINT fk_25618e267e3c61f9 FOREIGN KEY (owner_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_25618e267e3c61f9 ON postbox (owner_id)');
        $this->addSql('DROP INDEX UNIQ_8D93D6497AE42377');
        $this->addSql('ALTER TABLE "user" DROP shared_postbox_identifier_token');
    }
}
