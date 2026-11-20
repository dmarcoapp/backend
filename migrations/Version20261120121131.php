<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\String\ByteString;

final class Version20261120121131 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Keep the settings that are not infrastructure in the database, and the report address with them instead of with the account';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE platform_settings (id INT NOT NULL, report_address_token VARCHAR(32) NOT NULL, report_address_domain VARCHAR(255) DEFAULT NULL, report_retention_days INT DEFAULT NULL, ip_lookup_enabled BOOLEAN DEFAULT NULL, smtp_host VARCHAR(255) DEFAULT NULL, smtp_port INT DEFAULT NULL, smtp_security VARCHAR(16) DEFAULT NULL, smtp_username VARCHAR(255) DEFAULT NULL, smtp_password TEXT DEFAULT NULL, sender_address VARCHAR(255) DEFAULT NULL, imported_from_environment JSON DEFAULT \'{}\' NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN platform_settings.updated_at IS \'(DC2Type:datetime_immutable)\'');

        // The report address the DMARC records already name moves over from the
        // account, so the records keep working, and a new account later keeps
        // it. Without an account there is no record naming one yet.
        $token = $this->connection->fetchOne('SELECT shared_postbox_identifier_token FROM "user" ORDER BY created_at LIMIT 1');
        if (!is_string($token) || '' === $token) {
            $token = ByteString::fromRandom(32, 'abcdefghijklmnopqrstuvwxyz0123456789')->toString();
        }
        $this->addSql('INSERT INTO platform_settings (id, report_address_token) VALUES (1, :token)', ['token' => $token]);

        $this->addSql('DROP INDEX uniq_shared_postbox_id_token');
        $this->addSql('ALTER TABLE "user" DROP shared_postbox_identifier_token');
    }

    public function down(Schema $schema): void
    {
        // The settings made since go with the table. The account gets the
        // report address back.
        $this->addSql('ALTER TABLE "user" ADD shared_postbox_identifier_token VARCHAR(32) DEFAULT NULL');
        $this->addSql('UPDATE "user" SET shared_postbox_identifier_token = (SELECT report_address_token FROM platform_settings WHERE id = 1)');
        $this->addSql('ALTER TABLE "user" ALTER shared_postbox_identifier_token SET NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_shared_postbox_id_token ON "user" (shared_postbox_identifier_token)');
        $this->addSql('DROP TABLE platform_settings');
    }
}
