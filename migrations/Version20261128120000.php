<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261128120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Count the times every session of the account was ended, so access tokens issued before can be refused';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD session_version INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP session_version');
    }
}
