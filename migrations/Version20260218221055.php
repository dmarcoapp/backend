<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260218221055 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add plan magnitude and seed Free(0), Plus(1)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plan ADD magnitude INT DEFAULT 0 NOT NULL');
        $this->addSql("UPDATE plan SET magnitude = 0 WHERE id = '00000000-0000-0000-0000-000000000000'");
        $this->addSql("UPDATE plan SET magnitude = 1 WHERE id = '01f2929a-8322-48e4-a6fc-ae36697e9b20'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plan DROP magnitude');
    }
}
