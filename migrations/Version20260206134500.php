<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260206134500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Backfill usage balances for existing users.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            INSERT INTO usage_balance (id, user_id, daily_remaining, extra_remaining, last_daily_reset_at)
            SELECT
                gen_random_uuid(),
                u.id,
                p.daily_limit,
                0,
                date_trunc(\'day\', timezone(\'UTC\', now()))
            FROM "user" u
            JOIN subscription s ON s.id = u.subscription_id
            JOIN plan p ON p.id = s.plan_id
            LEFT JOIN usage_balance ub ON ub.user_id = u.id
            WHERE ub.user_id IS NULL
        ');
    }

    public function down(Schema $schema): void
    {
        // No-op: safe rollback is not feasible without risking data loss.
    }
}
