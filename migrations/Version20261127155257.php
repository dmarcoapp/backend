<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261127155257 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remember the time zone the dashboard shows dates and times in';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE platform_settings ADD display_timezone VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE platform_settings DROP display_timezone');
    }
}
