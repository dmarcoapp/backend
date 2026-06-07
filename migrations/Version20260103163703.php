<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260103163703 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE auth_log (id UUID NOT NULL, user_id UUID NOT NULL, action VARCHAR(255) NOT NULL, ip VARCHAR(45) DEFAULT NULL, user_agent VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_audit_action ON auth_log (action)');
        $this->addSql('CREATE INDEX idx_audit_user ON auth_log (user_id)');
        $this->addSql('CREATE INDEX idx_audit_created ON auth_log (created_at)');
        $this->addSql('COMMENT ON COLUMN auth_log.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN auth_log.user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN auth_log.created_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE auth_log');
    }
}
