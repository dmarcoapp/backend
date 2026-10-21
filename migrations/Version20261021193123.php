<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021193123 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop email verification, emailed password resets, and emailed two-factor codes';
    }

    public function up(Schema $schema): void
    {
        // An account that never confirmed its address could belong to anyone who
        // typed it in while registration was open. Without verification it would
        // become usable, so it goes, as the daily cleanup would have done.
        $this->addSql('DELETE FROM "user" WHERE email_verification_token IS NOT NULL');
        $this->addSql('DROP INDEX uniq_email_verification_token');
        $this->addSql('DROP INDEX uniq_reset_password_token');
        $this->addSql('ALTER TABLE "user" DROP password_reset_token');
        $this->addSql('ALTER TABLE "user" DROP password_reset_token_expires_at');
        $this->addSql('ALTER TABLE "user" DROP email_verification_token');
        $this->addSql('ALTER TABLE "user" DROP email_verification_token_expires_at');
        $this->addSql('ALTER TABLE "user" DROP two_factor_email_secret');
        $this->addSql('ALTER TABLE "user" ALTER two_factor_method DROP DEFAULT');
        $this->addSql('ALTER TABLE "user" ALTER two_factor_method DROP NOT NULL');
        // Codes by email are gone, so whoever used them signs in with the password
        // alone until they set up an authenticator app.
        $this->addSql('UPDATE "user" SET two_factor_method = NULL WHERE two_factor_method = \'email\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD password_reset_token VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD password_reset_token_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD email_verification_token VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD email_verification_token_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD two_factor_email_secret VARCHAR(64) DEFAULT NULL');
        $this->addSql('UPDATE "user" SET two_factor_method = \'email\' WHERE two_factor_method IS NULL');
        $this->addSql('ALTER TABLE "user" ALTER two_factor_method SET DEFAULT \'email\'');
        $this->addSql('ALTER TABLE "user" ALTER two_factor_method SET NOT NULL');
        $this->addSql('COMMENT ON COLUMN "user".password_reset_token_expires_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN "user".email_verification_token_expires_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE UNIQUE INDEX uniq_email_verification_token ON "user" (email_verification_token)');
        $this->addSql('CREATE UNIQUE INDEX uniq_reset_password_token ON "user" (password_reset_token)');
    }
}
