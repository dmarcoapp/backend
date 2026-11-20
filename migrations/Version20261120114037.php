<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120114037 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Serve one account: domains, report emails and the blocklist stop belonging to a user, and a report delivered twice is kept once';
    }

    public function up(Schema $schema): void
    {
        // Several accounts would pour their domains and reports into one pool
        // here, and two of them watching the same domain would not fit into it
        // at all. Which ones to keep is the operator's decision, not this one's.
        $accounts = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM "user"');
        $this->abortIf(
            $accounts > 1,
            sprintf(
                'DMARCo now has one account, and this database has %d. On the version you are upgrading from, delete the accounts you do not need with "bin/console app:user:delete", which deletes their reports as well, then upgrade again.',
                $accounts,
            ),
        );

        $this->addSql('ALTER TABLE domain DROP CONSTRAINT fk_a7a91e0ba76ed395');
        $this->addSql('DROP INDEX idx_a7a91e0ba76ed395');
        $this->addSql('DROP INDEX uniq_user_domain');
        $this->addSql('ALTER TABLE domain DROP user_id');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_DOMAIN ON domain (domain)');
        $this->addSql('ALTER TABLE email DROP CONSTRAINT fk_e7927c747e3c61f9');
        $this->addSql('DROP INDEX idx_e7927c747e3c61f9');
        $this->addSql('ALTER TABLE email DROP owner_id');
        $this->addSql('ALTER TABLE email_blocklist_entry DROP CONSTRAINT fk_6da1ad73a76ed395');
        $this->addSql('DROP INDEX idx_blocklist_user');
        $this->addSql('DROP INDEX uniq_user_blocklist_pattern');
        $this->addSql('ALTER TABLE email_blocklist_entry DROP user_id');
        $this->addSql('CREATE UNIQUE INDEX uniq_blocklist_pattern ON email_blocklist_entry (pattern)');

        // A report that reached two report addresses was stored twice and
        // counted twice. The copy received first stays. Deleting the email
        // takes its report and the report's records with it.
        $this->addSql(<<<'SQL'
            DELETE FROM email
            WHERE id IN (
                SELECT copies.email_id
                FROM (
                    SELECT r.email_id, ROW_NUMBER() OVER (
                        PARTITION BY r.reporting_organization, r.report_id, r.domain, r.begin_date
                        ORDER BY e.created_at, r.id
                    ) AS position
                    FROM report r
                    JOIN email e ON e.id = r.email_id
                    WHERE r.reporting_organization IS NOT NULL
                        AND r.report_id IS NOT NULL
                        AND r.domain IS NOT NULL
                        AND r.begin_date IS NOT NULL
                ) copies
                WHERE copies.position > 1
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_report_origin ON report (reporting_organization, report_id, domain, begin_date)');

        $this->addSql('ALTER TABLE "user" DROP roles');
    }

    public function down(Schema $schema): void
    {
        // Everything goes back to the one account. Without one there is no
        // owner to give the domains, report emails and blocklist back to.
        $rows = (int) $this->connection->fetchOne(
            'SELECT (SELECT COUNT(*) FROM domain) + (SELECT COUNT(*) FROM email) + (SELECT COUNT(*) FROM email_blocklist_entry)'
        );
        $accounts = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM "user"');
        $this->abortIf(
            $rows > 0 && 0 === $accounts,
            'There is no account to give the domains, report emails and blocklist back to.',
        );

        $account = '(SELECT id FROM "user" ORDER BY created_at LIMIT 1)';

        $this->addSql('ALTER TABLE "user" ADD roles JSON DEFAULT \'["ROLE_USER"]\' NOT NULL');
        $this->addSql('ALTER TABLE "user" ALTER roles DROP DEFAULT');

        // The copies deleted on the way up stay deleted.
        $this->addSql('DROP INDEX uniq_report_origin');

        $this->addSql('DROP INDEX uniq_blocklist_pattern');
        $this->addSql('ALTER TABLE email_blocklist_entry ADD user_id UUID DEFAULT NULL');
        $this->addSql('UPDATE email_blocklist_entry SET user_id = '.$account);
        $this->addSql('ALTER TABLE email_blocklist_entry ALTER user_id SET NOT NULL');
        $this->addSql('COMMENT ON COLUMN email_blocklist_entry.user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE email_blocklist_entry ADD CONSTRAINT fk_6da1ad73a76ed395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_blocklist_user ON email_blocklist_entry (user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_blocklist_pattern ON email_blocklist_entry (user_id, pattern)');

        $this->addSql('ALTER TABLE email ADD owner_id UUID DEFAULT NULL');
        $this->addSql('UPDATE email SET owner_id = '.$account);
        $this->addSql('ALTER TABLE email ALTER owner_id SET NOT NULL');
        $this->addSql('COMMENT ON COLUMN email.owner_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE email ADD CONSTRAINT fk_e7927c747e3c61f9 FOREIGN KEY (owner_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_e7927c747e3c61f9 ON email (owner_id)');

        $this->addSql('DROP INDEX UNIQ_DOMAIN');
        $this->addSql('ALTER TABLE domain ADD user_id UUID DEFAULT NULL');
        $this->addSql('UPDATE domain SET user_id = '.$account);
        $this->addSql('ALTER TABLE domain ALTER user_id SET NOT NULL');
        $this->addSql('COMMENT ON COLUMN domain.user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE domain ADD CONSTRAINT fk_a7a91e0ba76ed395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_a7a91e0ba76ed395 ON domain (user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_domain ON domain (user_id, domain)');
    }
}
