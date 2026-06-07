<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260227141145 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add domain limit to plans with backfilled defaults.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plan ADD domain_limit INT DEFAULT 0 NOT NULL');
        $this->addSql('UPDATE plan SET domain_limit = 1 WHERE id = \'00000000-0000-0000-0000-000000000000\'');
        $this->addSql('UPDATE plan SET domain_limit = 100 WHERE id = \'01f2929a-8322-48e4-a6fc-ae36697e9b20\'');
        $this->addSql('ALTER TABLE plan ALTER domain_limit DROP DEFAULT;');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plan DROP domain_limit');
    }
}
