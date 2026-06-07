<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260127101550 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE report DROP CONSTRAINT fk_c42f7784a76ed395');
        $this->addSql('ALTER TABLE report DROP CONSTRAINT FK_C42F7784A832C1C9');
        $this->addSql('DROP INDEX idx_c42f7784a76ed395');
        $this->addSql('ALTER TABLE report DROP user_id');
        $this->addSql('ALTER TABLE report DROP email_number');
        $this->addSql('ALTER TABLE report DROP sender_email');
        $this->addSql('ALTER TABLE report ADD CONSTRAINT FK_C42F7784A832C1C9 FOREIGN KEY (email_id) REFERENCES email (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE report DROP CONSTRAINT fk_c42f7784a832c1c9');
        $this->addSql('ALTER TABLE report ADD user_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE report ADD email_number VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE report ADD sender_email VARCHAR(255) DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN report.user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE report ADD CONSTRAINT fk_c42f7784a76ed395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE report ADD CONSTRAINT fk_c42f7784a832c1c9 FOREIGN KEY (email_id) REFERENCES email (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_c42f7784a76ed395 ON report (user_id)');
    }
}
