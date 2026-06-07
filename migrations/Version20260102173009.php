<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260102173009 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE report_record ADD source_ip_info_org_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE report_record ADD source_ip_info_org_country VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE report_record ADD source_ip_info_org_abuse_email VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE report_record ADD source_ip_info_org_tech_email VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE report_record DROP source_ip_info_org_name');
        $this->addSql('ALTER TABLE report_record DROP source_ip_info_org_country');
        $this->addSql('ALTER TABLE report_record DROP source_ip_info_org_abuse_email');
        $this->addSql('ALTER TABLE report_record DROP source_ip_info_org_tech_email');
    }
}
