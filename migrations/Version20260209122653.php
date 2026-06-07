<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260209122653 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "user" ADD two_factor_method VARCHAR(255) DEFAULT \'email\' NOT NULL');
        $this->addSql('ALTER TABLE "user" ADD two_factor_email_secret VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD two_factor_app_secret VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "user" DROP two_factor_method');
        $this->addSql('ALTER TABLE "user" DROP two_factor_email_secret');
        $this->addSql('ALTER TABLE "user" DROP two_factor_app_secret');
    }
}
