<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251230165855 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE postbox_logs (id UUID NOT NULL, postbox_id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, level VARCHAR(255) NOT NULL, message VARCHAR(255) NOT NULL, context JSON DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_4F4BDC1BC5341B4C ON postbox_logs (postbox_id)');
        $this->addSql('COMMENT ON COLUMN postbox_logs.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN postbox_logs.postbox_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN postbox_logs.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE postbox_logs ADD CONSTRAINT FK_4F4BDC1BC5341B4C FOREIGN KEY (postbox_id) REFERENCES postbox (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE postbox DROP last_connected');
        $this->addSql('ALTER TABLE postbox DROP last_error');
        $this->addSql('ALTER TABLE postbox DROP last_downloaded_emails');
        $this->addSql('ALTER TABLE postbox ALTER processing_strategy SET DEFAULT \'soft_delete\'');
        $this->addSql('UPDATE postbox SET processing_strategy = \'soft_delete\' WHERE processing_strategy = \'mark_as_seen\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE postbox_logs DROP CONSTRAINT FK_4F4BDC1BC5341B4C');
        $this->addSql('DROP TABLE postbox_logs');
        $this->addSql('ALTER TABLE postbox ADD last_connected TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE postbox ADD last_error TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE postbox ADD last_downloaded_emails TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE postbox ALTER processing_strategy SET DEFAULT \'mark_as_seen\'');
    }
}
