<?php

declare(strict_types=1);

namespace App\Tests\Command\Auth;

use App\Command\User\CreateUserCommand;
use App\Command\User\DeleteUserCommand;
use App\Command\User\ProcessUserDailyBulkCommand;
use App\Command\User\ProcessUserDailyCommand;
use App\Command\User\ProcessUserHourlyBulkCommand;
use App\Command\User\ProcessUserHourlyCommand;
use App\Command\User\ProcessUserWeeklyBulkCommand;
use App\Command\User\ProcessUserWeeklyCommand;
use App\Command\User\ResendUserEmailVerificationCommand;
use App\Command\User\ResetUserPasswordCommand;
use App\Entity\User\User;
use App\Event\User\EmailVerificationResendEvent;
use App\Event\User\PasswordResetEvent;
use App\Message\User\ProcessUserDaily;
use App\Message\User\ProcessUserHourly;
use App\Message\User\ProcessUserWeekly;
use App\Repository\User\UserRepository;
use App\Service\User\UserDeleteProcessor;
use App\Service\User\UserFactory;
use App\Tests\Api\ApiIntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(CreateUserCommand::class)]
#[CoversClass(DeleteUserCommand::class)]
#[CoversClass(ProcessUserDailyCommand::class)]
#[CoversClass(ProcessUserDailyBulkCommand::class)]
#[CoversClass(ProcessUserHourlyCommand::class)]
#[CoversClass(ProcessUserHourlyBulkCommand::class)]
#[CoversClass(ProcessUserWeeklyCommand::class)]
#[CoversClass(ProcessUserWeeklyBulkCommand::class)]
#[CoversClass(ResendUserEmailVerificationCommand::class)]
#[CoversClass(ResetUserPasswordCommand::class)]
final class AuthCommandsTest extends ApiIntegrationTestCase
{
    public function testCreateUserCommandCreatesUser(): void
    {
        $email = 'cmd-create-'.uniqid('', true).'@example.com';

        $command = new CreateUserCommand(
            self::getContainer()->get(UserFactory::class),
            self::getContainer()->get(UserRepository::class),
        );

        $tester = new CommandTester($command);
        $tester->setInputs([$email, 'User', 'Str0ngPassw0rd!@#', 'ROLE_USER']);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertNotNull(self::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]));
    }

    public function testCreateUserCommandFailsWhenUserExists(): void
    {
        $email = 'cmd-existing-'.uniqid('', true).'@example.com';
        $this->createVerifiedUser($email, 'Str0ngPassw0rd!@#');

        $command = new CreateUserCommand(
            self::getContainer()->get(UserFactory::class),
            self::getContainer()->get(UserRepository::class),
        );

        $tester = new CommandTester($command);
        $tester->setInputs([$email]);
        $tester->execute([]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('already exists', $tester->getDisplay());
    }

    public function testDeleteUserCommandSuccess(): void
    {
        $email = 'cmd-delete-'.uniqid('', true).'@example.com';
        $this->createVerifiedUser($email, 'Str0ngPassw0rd!@#');

        $command = new DeleteUserCommand(
            self::getContainer()->get(UserRepository::class),
            self::getContainer()->get(UserDeleteProcessor::class),
        );

        $tester = new CommandTester($command);
        $tester->setInputs([$email]);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertNull(self::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]));
    }

    public function testDeleteUserCommandFailsWhenMissing(): void
    {
        $command = new DeleteUserCommand(
            self::getContainer()->get(UserRepository::class),
            self::getContainer()->get(UserDeleteProcessor::class),
        );

        $tester = new CommandTester($command);
        $tester->setInputs(['missing@example.com']);
        $tester->execute([]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testDeleteUserCommandFailsWhenProcessorFails(): void
    {
        $user = (new User())
            ->setEmail('cmd-delete-fail@example.com')
            ->setName('Delete Fail')
            ->setSharedPostboxIdentifierToken('cmd-delete-fail-token')
        ;

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['email' => 'cmd-delete-fail@example.com'])
            ->willReturn($user)
        ;
        $userRepository->expects(self::once())->method('delete')->with($user)->willThrowException(new \RuntimeException());

        $processor = new UserDeleteProcessor(
            $userRepository,
            $this->createStub(LoggerInterface::class),
        );

        $command = new DeleteUserCommand($userRepository, $processor);

        $tester = new CommandTester($command);
        $tester->setInputs(['cmd-delete-fail@example.com']);
        $tester->execute([]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Failed to delete user.', $tester->getDisplay());
    }

    public function testProcessUserDailyCommandDispatches(): void
    {
        $user = $this->createVerifiedUser('cmd-daily-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (ProcessUserDaily $message) use ($user): bool {
                return $message->userId === $user->getId()->toRfc4122();
            }))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message))
        ;

        $command = new ProcessUserDailyCommand(
            self::getContainer()->get(UserRepository::class),
            $messageBus
        );

        $tester = new CommandTester($command);
        $tester->execute(['id' => $user->getId()->toRfc4122()]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testProcessUserDailyCommandPromptsForId(): void
    {
        $user = $this->createVerifiedUser('cmd-daily-prompt-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (ProcessUserDaily $message) use ($user): bool {
                return $message->userId === $user->getId()->toRfc4122();
            }))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message))
        ;

        $command = new ProcessUserDailyCommand(
            self::getContainer()->get(UserRepository::class),
            $messageBus,
        );

        $tester = new CommandTester($command);
        $tester->setInputs([$user->getId()->toRfc4122()]);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testProcessUserDailyCommandFailsWhenMissing(): void
    {
        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('find')->willReturn(null);

        $command = new ProcessUserDailyCommand($userRepository, $this->createStub(MessageBusInterface::class));

        $tester = new CommandTester($command);
        $tester->execute(['id' => 'missing']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testProcessUserDailyCommandFailsWhenUserIdIsMissing(): void
    {
        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('find')->willReturn(new User());

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        $command = new ProcessUserDailyCommand($userRepository, $messageBus);

        $tester = new CommandTester($command);
        $tester->execute(['id' => 'existing-user-without-id']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testProcessUserDailyBulkCommandDispatches(): void
    {
        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('getAllUserIds')->willReturn(['id-1', 'id-2']);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects(self::exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message))
        ;

        $command = new ProcessUserDailyBulkCommand($userRepository, $messageBus);

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testProcessUserDailyBulkCommandNoUsers(): void
    {
        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('getAllUserIds')->willReturn([]);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        $command = new ProcessUserDailyBulkCommand($userRepository, $messageBus);

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testProcessUserGeneralCommandDispatches(): void
    {
        $user = $this->createVerifiedUser('cmd-general-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (ProcessUserHourly $message) use ($user): bool {
                return $message->userId === $user->getId()->toRfc4122();
            }))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message))
        ;

        $command = new ProcessUserHourlyCommand(
            self::getContainer()->get(UserRepository::class),
            $messageBus
        );

        $tester = new CommandTester($command);
        $tester->execute(['id' => $user->getId()->toRfc4122()]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testProcessUserGeneralCommandPromptsForId(): void
    {
        $user = $this->createVerifiedUser('cmd-general-prompt-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (ProcessUserHourly $message) use ($user): bool {
                return $message->userId === $user->getId()->toRfc4122();
            }))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message))
        ;

        $command = new ProcessUserHourlyCommand(
            self::getContainer()->get(UserRepository::class),
            $messageBus,
        );

        $tester = new CommandTester($command);
        $tester->setInputs([$user->getId()->toRfc4122()]);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testProcessUserGeneralCommandFailsWhenMissing(): void
    {
        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('find')->willReturn(null);

        $command = new ProcessUserHourlyCommand($userRepository, $this->createStub(MessageBusInterface::class));

        $tester = new CommandTester($command);
        $tester->execute(['id' => 'missing']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testProcessUserGeneralCommandFailsWhenUserIdIsMissing(): void
    {
        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('find')->willReturn(new User());

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        $command = new ProcessUserHourlyCommand($userRepository, $messageBus);

        $tester = new CommandTester($command);
        $tester->execute(['id' => 'existing-user-without-id']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testProcessUserGeneralBulkCommandDispatches(): void
    {
        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('getAllUserIds')->willReturn(['id-1']);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message))
        ;

        $command = new ProcessUserHourlyBulkCommand($userRepository, $messageBus);

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testProcessUserGeneralBulkCommandSucceedsWhenNoUsers(): void
    {
        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('getAllUserIds')->willReturn([]);

        $command = new ProcessUserHourlyBulkCommand($userRepository, $this->createStub(MessageBusInterface::class));

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testProcessUserWeeklyCommandDispatches(): void
    {
        $user = $this->createVerifiedUser('cmd-weekly-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (ProcessUserWeekly $message) use ($user): bool {
                return $message->userId === $user->getId()->toRfc4122();
            }))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message))
        ;

        $command = new ProcessUserWeeklyCommand(
            self::getContainer()->get(UserRepository::class),
            $messageBus,
        );

        $tester = new CommandTester($command);
        $tester->execute(['id' => $user->getId()->toRfc4122()]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testProcessUserWeeklyCommandPromptsForId(): void
    {
        $user = $this->createVerifiedUser('cmd-weekly-prompt-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (ProcessUserWeekly $message) use ($user): bool {
                return $message->userId === $user->getId()->toRfc4122();
            }))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message))
        ;

        $command = new ProcessUserWeeklyCommand(
            self::getContainer()->get(UserRepository::class),
            $messageBus,
        );

        $tester = new CommandTester($command);
        $tester->setInputs([$user->getId()->toRfc4122()]);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testProcessUserWeeklyCommandFailsWhenMissing(): void
    {
        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('find')->willReturn(null);

        $command = new ProcessUserWeeklyCommand($userRepository, $this->createStub(MessageBusInterface::class));

        $tester = new CommandTester($command);
        $tester->execute(['id' => 'missing']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testProcessUserWeeklyCommandFailsWhenUserIdIsMissing(): void
    {
        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('find')->willReturn(new User());

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        $command = new ProcessUserWeeklyCommand($userRepository, $messageBus);

        $tester = new CommandTester($command);
        $tester->execute(['id' => 'existing-user-without-id']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testProcessUserWeeklyBulkCommandDispatches(): void
    {
        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('getAllUserIds')->willReturn(['id-1', 'id-2']);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects(self::exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message))
        ;

        $command = new ProcessUserWeeklyBulkCommand($userRepository, $messageBus);

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testProcessUserWeeklyBulkCommandNoUsers(): void
    {
        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('getAllUserIds')->willReturn([]);

        $command = new ProcessUserWeeklyBulkCommand($userRepository, $this->createStub(MessageBusInterface::class));

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testResendUserEmailVerificationCommandDispatches(): void
    {
        $user = new User();
        $user->setEmail('resend@example.com');

        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn($user);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(EmailVerificationResendEvent::class))
        ;

        $command = new ResendUserEmailVerificationCommand($userRepository, $dispatcher);

        $tester = new CommandTester($command);
        $tester->setInputs(['resend@example.com']);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testResendUserEmailVerificationCommandFailsWhenMissing(): void
    {
        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn(null);

        $dispatcher = $this->createStub(EventDispatcherInterface::class);
        $command = new ResendUserEmailVerificationCommand($userRepository, $dispatcher);

        $tester = new CommandTester($command);
        $tester->setInputs(['missing@example.com']);
        $tester->execute([]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testResetUserPasswordCommandHashesPassword(): void
    {
        $user = new User();
        $this->setEntityId($user, Uuid::v4());
        $user->setEmail('reset@example.com');

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn($user);
        $userRepository->expects(self::once())->method('save')->with($user);

        $hasher = $this->createStub(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('hashed');

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::never())->method('dispatch');

        $command = new ResetUserPasswordCommand($userRepository, $hasher, $dispatcher);

        $tester = new CommandTester($command);
        $tester->setInputs(['reset@example.com', 'Str0ngPassw0rd!']);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame('hashed', $user->getPassword());
    }

    public function testResetUserPasswordCommandDispatchesWhenPasswordMissing(): void
    {
        $user = new User();
        $user->setEmail('reset-email@example.com');

        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn($user);

        $hasher = $this->createStub(UserPasswordHasherInterface::class);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(PasswordResetEvent::class))
        ;

        $command = new ResetUserPasswordCommand($userRepository, $hasher, $dispatcher);

        $tester = new CommandTester($command);
        $tester->setInputs(['reset-email@example.com', '']);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testResetUserPasswordCommandFailsWhenUserMissing(): void
    {
        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn(null);

        $command = new ResetUserPasswordCommand(
            $userRepository,
            $this->createStub(UserPasswordHasherInterface::class),
            $this->createStub(EventDispatcherInterface::class),
        );

        $tester = new CommandTester($command);
        $tester->setInputs(['missing@example.com']);
        $tester->execute([]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testCommandDefinitionsAreConfigured(): void
    {
        $processDaily = new ProcessUserDailyCommand(
            self::getContainer()->get(UserRepository::class),
            self::getContainer()->get(MessageBusInterface::class),
        );
        $processGeneral = new ProcessUserHourlyCommand(
            self::getContainer()->get(UserRepository::class),
            self::getContainer()->get(MessageBusInterface::class),
        );
        $processGeneralBulk = new ProcessUserHourlyBulkCommand(
            self::getContainer()->get(UserRepository::class),
            self::getContainer()->get(MessageBusInterface::class),
        );

        self::assertTrue($processDaily->getDefinition()->hasArgument('id'));
        self::assertTrue($processGeneral->getDefinition()->hasArgument('id'));
        self::assertTrue($processGeneralBulk->getDefinition()->hasOption('limit'));
    }

    private function setEntityId(object $entity, Uuid $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }
}
