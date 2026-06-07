<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260125213839 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE report DROP CONSTRAINT FK_C42F7784A832C1C9');
        $this->addSql('ALTER TABLE report ADD CONSTRAINT FK_C42F7784A832C1C9 FOREIGN KEY (email_id) REFERENCES email (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE report DROP CONSTRAINT fk_c42f7784a832c1c9');
        $this->addSql('ALTER TABLE report ADD CONSTRAINT fk_c42f7784a832c1c9 FOREIGN KEY (email_id) REFERENCES email (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
