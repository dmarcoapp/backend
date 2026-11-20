<?php

declare(strict_types=1);

namespace App\Tests\Service\Intake;

use App\DTO\Input\Intake\MailboxInput;
use App\Entity\Intake\Mailbox;
use App\Enum\Intake\AfterImport;
use App\Enum\Intake\MailboxSecurity;
use App\Service\Intake\MailboxCredentials;
use App\Service\Intake\MailboxEditor;
use App\Service\Security\SecretBox;
use App\Tests\Support\PlatformSettingsTestFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(MailboxEditor::class)]
#[CoversClass(MailboxCredentials::class)]
#[CoversClass(Mailbox::class)]
final class MailboxEditorTest extends TestCase
{
    public function testMakesAMailboxWithItsPasswordEncryptedForItAlone(): void
    {
        $credentials = new MailboxCredentials(new SecretBox(PlatformSettingsTestFactory::KEY));
        $editor = new MailboxEditor($credentials);

        $first = $editor->create(new MailboxInput(name: ' Reports ', host: 'IMAP.example.com', username: 'dmarc@example.com', password: 'secret'));
        $second = $editor->create(new MailboxInput(name: 'Other', host: 'imap.example.com', username: 'dmarc@example.com', password: 'secret'));

        self::assertSame('Reports', $first->getName());
        self::assertSame('imap.example.com', $first->getHost());
        self::assertSame(MailboxSecurity::TLS, $first->getSecurity());
        self::assertSame(993, $first->getPort());
        self::assertSame('secret', $credentials->password($first));

        // The same password reads differently, and does not open in another mailbox.
        self::assertNotSame($first->getEncryptedCredentials(), $second->getEncryptedCredentials());
        $second->setEncryptedCredentials($second->getAuthMethod(), $first->getEncryptedCredentials());
        self::assertFalse($credentials->isReadable($second));
    }

    public function testRefusesAMailboxWithoutAPassword(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new MailboxEditor(new MailboxCredentials(new SecretBox(PlatformSettingsTestFactory::KEY)))
            ->create(new MailboxInput(name: 'Reports', host: 'imap.example.com', username: 'dmarc@example.com'));
    }

    public function testRefusesToMoveReportsIntoTheFolderTheyAreReadFrom(): void
    {
        $editor = new MailboxEditor(new MailboxCredentials(new SecretBox(PlatformSettingsTestFactory::KEY)));
        $mailbox = $editor->create(new MailboxInput(name: 'Reports', host: 'imap.example.com', username: 'dmarc', password: 'secret', folder: 'DMARC'));

        $this->expectException(\InvalidArgumentException::class);
        $editor->update($mailbox, new MailboxInput(afterImport: 'move', moveToFolder: 'DMARC'));
    }

    public function testIsDueOnItsOwnScheduleAndBacksOffAfterFailures(): void
    {
        $mailbox = new Mailbox('Reports', 'imap.example.com', MailboxSecurity::TLS, 'dmarc');
        $now = new \DateTimeImmutable('2026-11-20 12:00:00 UTC');

        self::assertTrue($mailbox->isDue($now));

        $mailbox->recordSuccess($now);
        self::assertFalse($mailbox->isDue($now->modify('+14 minutes')));
        self::assertTrue($mailbox->isDue($now->modify('+15 minutes')));

        for ($i = 0; $i < 5; ++$i) {
            $mailbox->recordFailure($now, 'Authentication failed.');
        }
        self::assertSame(5, $mailbox->getConsecutiveFailures());
        self::assertFalse($mailbox->isDue($now->modify('+15 minutes')));
        self::assertTrue($mailbox->isDue($now->modify('+60 minutes')));

        $mailbox->setEnabled(false);
        self::assertFalse($mailbox->isDue($now->modify('+1 day')));
    }

    public function testOnlyMovingKeepsATargetFolder(): void
    {
        $mailbox = new Mailbox('Reports', 'imap.example.com', MailboxSecurity::TLS, 'dmarc');

        $mailbox->setAfterImport(AfterImport::MOVE, 'Done');
        self::assertSame('Done', $mailbox->getMoveToFolder());

        $mailbox->setAfterImport(AfterImport::MARK_SEEN, 'Done');
        self::assertNull($mailbox->getMoveToFolder());
    }
}
