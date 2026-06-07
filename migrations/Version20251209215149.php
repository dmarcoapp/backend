<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251209215149 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE report DROP CONSTRAINT FK_C42F7784C5341B4C');
        $this->addSql('ALTER TABLE report ADD CONSTRAINT FK_C42F7784C5341B4C FOREIGN KEY (postbox_id) REFERENCES postbox (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE report DROP CONSTRAINT fk_c42f7784c5341b4c');
        $this->addSql('ALTER TABLE report ADD CONSTRAINT fk_c42f7784c5341b4c FOREIGN KEY (postbox_id) REFERENCES postbox (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
