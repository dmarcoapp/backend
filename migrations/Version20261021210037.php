<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021210037 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Keep a report email\'s XML in the database until it is processed, instead of in object storage';
    }

    public function up(Schema $schema): void
    {
        // An email still waiting for its worker loses the object it pointed at.
        // The worker reads one within seconds, so only an upgrade in the middle
        // of a backlog leaves any behind, and those fail as unrecoverable.
        $this->addSql('ALTER TABLE email ADD attachment_content TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE email DROP attachment_bucket');
        $this->addSql('ALTER TABLE email DROP attachment_key');
    }

    public function down(Schema $schema): void
    {
        // The columns come back empty: what was stored is in the database now.
        $this->addSql('ALTER TABLE email ADD attachment_bucket VARCHAR(255) DEFAULT \'\' NOT NULL');
        $this->addSql('ALTER TABLE email ADD attachment_key VARCHAR(255) DEFAULT \'\' NOT NULL');
        $this->addSql('ALTER TABLE email ALTER attachment_bucket DROP DEFAULT');
        $this->addSql('ALTER TABLE email ALTER attachment_key DROP DEFAULT');
        $this->addSql('ALTER TABLE email DROP attachment_content');
    }
}
