<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251224211249 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE report ALTER adkim_policy TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE report ALTER aspf_policy TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE report ALTER p_policy TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE report ALTER sp_policy TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE report ALTER np_policy TYPE VARCHAR(255)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE report ALTER adkim_policy TYPE VARCHAR(15)');
        $this->addSql('ALTER TABLE report ALTER aspf_policy TYPE VARCHAR(15)');
        $this->addSql('ALTER TABLE report ALTER p_policy TYPE VARCHAR(15)');
        $this->addSql('ALTER TABLE report ALTER sp_policy TYPE VARCHAR(15)');
        $this->addSql('ALTER TABLE report ALTER np_policy TYPE VARCHAR(15)');
    }
}
