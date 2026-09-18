<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260918121323 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Give messenger_messages the composite index its transport has expected since Symfony 7.4.3.';
    }

    /**
     * The table belongs to the Doctrine transport behind the failure_transport,
     * and up to symfony/doctrine-messenger 7.4.1 that transport asked for three
     * single column indexes, which is what the migration creating the table
     * wrote. Since 7.4.3 it asks for one index covering the columns its polling
     * query reads together. Nothing reconciles that on its own: the transport
     * would apply exactly this change, but only ever runs its setup after a
     * missing table, and the table is not missing. Until this lands, every
     * generated migration carries these statements along and
     * doctrine:schema:validate reports the database out of sync.
     *
     * The statements tolerate being a no-op, because an operator may already
     * have run `messenger:setup-transports failed` to get the same result by
     * hand, and a deployment should not break over having been fixed early.
     */
    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_75ea56e016ba31db');
        $this->addSql('DROP INDEX IF EXISTS idx_75ea56e0e3bd61ce');
        $this->addSql('DROP INDEX IF EXISTS idx_75ea56e0fb7336f0');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_75ea56e016ba31db ON messenger_messages (delivered_at)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_75ea56e0e3bd61ce ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_75ea56e0fb7336f0 ON messenger_messages (queue_name)');
    }
}
