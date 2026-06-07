<?php

declare(strict_types=1);

namespace App\Tests\Command\Email;

use App\Command\Email\ProcessEmailBulkCommand;
use App\Command\Email\ProcessEmailCommand;
use App\Entity\Email\Email;
use App\Entity\User\User;
use App\Message\Email\ProcessEmail;
use App\Repository\Email\EmailRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(ProcessEmailCommand::class)]
#[CoversClass(ProcessEmailBulkCommand::class)]
final class EmailCommandsTest extends TestCase
{
    public function testProcessEmailCommandDispatches(): void
    {
        $email = $this->createEmail();

        $repository = $this->createStub(EmailRepository::class);
        $repository->method('find')->willReturn($email);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (ProcessEmail $message) use ($email): bool {
                return $message->emailId === $email->getId()->toRfc4122();
            }))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message))
        ;

        $command = new ProcessEmailCommand($repository, $messageBus);

        $tester = new CommandTester($command);
        $tester->execute(['id' => $email->getId()->toRfc4122()]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testProcessEmailCommandPromptsForId(): void
    {
        $email = $this->createEmail();

        $repository = $this->createStub(EmailRepository::class);
        $repository->method('find')->willReturn($email);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (ProcessEmail $message) use ($email): bool {
                return $message->emailId === $email->getId()->toRfc4122();
            }))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message))
        ;

        $command = new ProcessEmailCommand($repository, $messageBus);

        $tester = new CommandTester($command);
        $tester->setInputs([$email->getId()->toRfc4122()]);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testProcessEmailCommandFailsWhenMissing(): void
    {
        $repository = $this->createStub(EmailRepository::class);
        $repository->method('find')->willReturn(null);

        $command = new ProcessEmailCommand($repository, $this->createStub(MessageBusInterface::class));

        $tester = new CommandTester($command);
        $tester->execute(['id' => 'missing']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testProcessEmailCommandFailsWhenEmailIdIsMissing(): void
    {
        $email = new Email();
        $email
            ->setCreatedAt(new \DateTimeImmutable())
            ->setFromAddress('reporter@example.com')
            ->setToAddress('inbound@example.com')
            ->setMessageId('<message@example.com>')
            ->setAttachmentBucket('bucket')
            ->setAttachmentKey('path/report.xml')
            ->setAttachmentFilename('report.xml')
            ->setAttachmentContentType('application/xml')
        ;

        $repository = $this->createStub(EmailRepository::class);
        $repository->method('find')->willReturn($email);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        $command = new ProcessEmailCommand($repository, $messageBus);

        $tester = new CommandTester($command);
        $tester->execute(['id' => 'existing-email-without-id']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testProcessEmailBulkCommandDispatches(): void
    {
        $repository = $this->createStub(EmailRepository::class);
        $repository->method('getEmailIdsWithoutReport')->willReturn(['id-1', 'id-2']);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects(self::exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message))
        ;

        $command = new ProcessEmailBulkCommand($repository, $messageBus);

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testProcessEmailBulkCommandNoEmails(): void
    {
        $repository = $this->createStub(EmailRepository::class);
        $repository->method('getEmailIdsWithoutReport')->willReturn([]);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        $command = new ProcessEmailBulkCommand($repository, $messageBus);

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testCommandDefinitionsAreConfigured(): void
    {
        $command = new ProcessEmailCommand(
            $this->createStub(EmailRepository::class),
            $this->createStub(MessageBusInterface::class),
        );

        $definition = $command->getDefinition();

        self::assertTrue($definition->hasArgument('id'));
    }

    private function createEmail(): Email
    {
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setSharedPostboxIdentifierToken('token');

        $email = new Email();
        $email
            ->setCreatedAt(new \DateTimeImmutable())
            ->setFromAddress('reporter@example.com')
            ->setToAddress('inbound@example.com')
            ->setMessageId('<message@example.com>')
            ->setAttachmentBucket('bucket')
            ->setAttachmentKey('path/report.xml')
            ->setAttachmentFilename('report.xml')
            ->setAttachmentContentType('application/xml')
            ->setOwner($user)
        ;

        $this->setEntityId($email, Uuid::v4());

        return $email;
    }

    private function setEntityId(object $entity, Uuid $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }
}
