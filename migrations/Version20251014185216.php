<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251014185216 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE report_record (id UUID NOT NULL, report_id UUID NOT NULL, source_ip VARCHAR(255) NOT NULL, count INT NOT NULL, disposition VARCHAR(255) NOT NULL, dkim_align VARCHAR(255) NOT NULL, spf_align VARCHAR(255) NOT NULL, dkim_auth VARCHAR(255) NOT NULL, dkim_domain VARCHAR(255) NOT NULL, dkim_selector VARCHAR(255) NOT NULL, spf_auth VARCHAR(255) NOT NULL, spf_domain VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_B2C74A0E4BD2A4C0 ON report_record (report_id)');
        $this->addSql('COMMENT ON COLUMN report_record.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN report_record.report_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE report_record ADD CONSTRAINT FK_B2C74A0E4BD2A4C0 FOREIGN KEY (report_id) REFERENCES report (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE report ADD reporting_organization VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE report ADD reporting_organization_email VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE report ADD reporting_organization_extra_contact VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE report ADD report_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE report ADD begin_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE report ADD end_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE report ADD domain VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE report ADD adkim_policy VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE report ADD aspf_policy VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE report ADD p_policy VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE report ADD sp_policy VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE report ADD pct_policy VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE report ADD np_policy VARCHAR(255) DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN report.begin_date IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN report.end_date IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE report_record DROP CONSTRAINT FK_B2C74A0E4BD2A4C0');
        $this->addSql('DROP TABLE report_record');
        $this->addSql('ALTER TABLE report DROP reporting_organization');
        $this->addSql('ALTER TABLE report DROP reporting_organization_email');
        $this->addSql('ALTER TABLE report DROP reporting_organization_extra_contact');
        $this->addSql('ALTER TABLE report DROP report_id');
        $this->addSql('ALTER TABLE report DROP begin_date');
        $this->addSql('ALTER TABLE report DROP end_date');
        $this->addSql('ALTER TABLE report DROP domain');
        $this->addSql('ALTER TABLE report DROP adkim_policy');
        $this->addSql('ALTER TABLE report DROP aspf_policy');
        $this->addSql('ALTER TABLE report DROP p_policy');
        $this->addSql('ALTER TABLE report DROP sp_policy');
        $this->addSql('ALTER TABLE report DROP pct_policy');
        $this->addSql('ALTER TABLE report DROP np_policy');
    }
}
