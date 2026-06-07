<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260228180033 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Renames the report is_legit column to is_verified.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE report RENAME COLUMN is_legit TO is_verified');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE report RENAME COLUMN is_verified TO is_legit');
    }
}
