<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251228213111 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX email_lookup_idx');
        $this->addSql('ALTER TABLE report ADD spf_compliance DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE report ADD dkim_compliance DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('CREATE INDEX email_lookup_idx ON report (postbox_id, email_number) WHERE postbox_id IS NOT NULL AND email_number IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX email_lookup_idx');
        $this->addSql('ALTER TABLE report DROP spf_compliance');
        $this->addSql('ALTER TABLE report DROP dkim_compliance');
        $this->addSql('CREATE INDEX email_lookup_idx ON report (postbox_id, email_number) WHERE ((postbox_id IS NOT NULL) AND (email_number IS NOT NULL))');
    }
}
