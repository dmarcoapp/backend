<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260129135524 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE plan (id UUID NOT NULL, name VARCHAR(255) NOT NULL, daily_limit INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN plan.id IS \'(DC2Type:uuid)\'');
        $this->addSql('INSERT INTO plan (id, name, daily_limit) VALUES (\'00000000-0000-0000-0000-000000000000\', \'Free\', 10)');
        $this->addSql('CREATE TABLE subscription (id UUID NOT NULL, plan_id UUID NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_A3C664D3E899029B ON subscription (plan_id)');
        $this->addSql('COMMENT ON COLUMN subscription.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN subscription.plan_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE subscription ADD CONSTRAINT FK_A3C664D3E899029B FOREIGN KEY (plan_id) REFERENCES plan (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE report ADD is_legit BOOLEAN DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD subscription_id UUID DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN "user".subscription_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE "user" ADD CONSTRAINT FK_8D93D6499A1887DC FOREIGN KEY (subscription_id) REFERENCES subscription (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D6499A1887DC ON "user" (subscription_id)');

        $this->addSql('
            INSERT INTO subscription (id, plan_id)
            SELECT 
                gen_random_uuid(),
                \'00000000-0000-0000-0000-000000000000\'
            FROM "user"
        ');
        $this->addSql('
            UPDATE "user" u
            SET subscription_id = s.id
            FROM (
                SELECT id, ROW_NUMBER() OVER (ORDER BY id) rn FROM "user"
            ) u2
            JOIN (
                SELECT id, ROW_NUMBER() OVER (ORDER BY id) rn FROM subscription
            ) s ON u2.rn = s.rn
            WHERE u.id = u2.id
        ');

        $this->addSql('ALTER TABLE "user" ALTER COLUMN subscription_id SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "user" DROP CONSTRAINT FK_8D93D6499A1887DC');
        $this->addSql('ALTER TABLE subscription DROP CONSTRAINT FK_A3C664D3E899029B');
        $this->addSql('DROP TABLE plan');
        $this->addSql('DROP TABLE subscription');
        $this->addSql('DROP INDEX UNIQ_8D93D6499A1887DC');
        $this->addSql('ALTER TABLE "user" DROP subscription_id');
        $this->addSql('ALTER TABLE report DROP is_legit');
    }
}
