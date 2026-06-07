<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260228175359 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Renames stored domain protection_level values to match the renamed enum backing values.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE domain
            SET protection_level = CASE protection_level
                WHEN 'low' THEN 'weak'
                WHEN 'medium' THEN 'moderate'
                WHEN 'perfect' THEN 'strong'
                ELSE protection_level
            END
            WHERE protection_level IN ('low', 'medium', 'perfect')
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE domain
            SET protection_level = CASE protection_level
                WHEN 'weak' THEN 'low'
                WHEN 'moderate' THEN 'medium'
                WHEN 'strong' THEN 'perfect'
                ELSE protection_level
            END
            WHERE protection_level IN ('weak', 'moderate', 'strong')
        SQL);
    }
}
