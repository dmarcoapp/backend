<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251014185808 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE report_record ALTER dkim_auth DROP NOT NULL');
        $this->addSql('ALTER TABLE report_record ALTER dkim_domain DROP NOT NULL');
        $this->addSql('ALTER TABLE report_record ALTER dkim_selector DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE report_record ALTER dkim_auth SET NOT NULL');
        $this->addSql('ALTER TABLE report_record ALTER dkim_domain SET NOT NULL');
        $this->addSql('ALTER TABLE report_record ALTER dkim_selector SET NOT NULL');
    }
}
