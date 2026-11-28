<?php

declare(strict_types=1);

namespace App\Tests\Command\Auth;

use App\Command\User\CreateUserCommand;
use App\Command\User\DeleteUserCommand;
use App\Command\User\DisableTwoFactorCommand;
use App\Command\User\ResetUserPasswordCommand;
use App\Command\User\SendWeeklyOverviewCommand;
use App\Entity\DMARC\Domain;
use App\Entity\User\User;
use App\Enum\User\TwoFactorMethod;
use App\Message\User\SendWeeklyOverview;
use App\Repository\User\UserRepository;
use App\Service\User\SessionRevoker;
use App\Entity\RefreshToken;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use App\Service\User\UserFactory;
use App\Tests\Api\ApiIntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(CreateUserCommand::class)]
#[CoversClass(DeleteUserCommand::class)]
#[CoversClass(DisableTwoFactorCommand::class)]
#[CoversClass(ResetUserPasswordCommand::class)]
#[CoversClass(SendWeeklyOverviewCommand::class)]
final class AuthCommandsTest extends ApiIntegrationTestCase
{
    public function testCreateUserCommandCreatesUser(): void
    {
        $this->removeAccounts();
        $email = 'cmd-create-'.uniqid('', true).'@example.com';

        $command = new CreateUserCommand(
            self::getContainer()->get(UserFactory::class),
            self::getContainer()->get(UserRepository::class),
        );

        $tester = new CommandTester($command);
        $tester->setInputs([$email, 'User', 'Str0ngPassw0rd!@#']);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertNotNull(self::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]));
    }

    public function testCreateUserCommandStillAcceptsTheSimpleOption(): void
    {
        $this->removeAccounts();
        $email = 'cmd-simple-'.uniqid('', true).'@example.com';

        $command = new CreateUserCommand(
            self::getContainer()->get(UserFactory::class),
            self::getContainer()->get(UserRepository::class),
        );

        // Installers released before the option lost its meaning still pass it.
        $tester = new CommandTester($command);
        $tester->setInputs([$email, 'User', 'Str0ngPassw0rd!@#']);
        $tester->execute(['--simple' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringNotContainsString('Roles', $tester->getDisplay());

        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
        self::assertNotNull($user);
        self::assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testCreateUserCommandAsksAgainForATooShortPassword(): void
    {
        $this->removeAccounts();
        $email = 'cmd-short-'.uniqid('', true).'@example.com';

        $command = new CreateUserCommand(
            self::getContainer()->get(UserFactory::class),
            self::getContainer()->get(UserRepository::class),
        );

        $tester = new CommandTester($command);
        $tester->setInputs([$email, 'User', 'short', 'Str0ngPassw0rd!@#']);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Use at least 8 characters.', $tester->getDisplay());
        self::assertNotNull(self::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]));
    }

    public function testCreateUserCommandRefusesASecondAccount(): void
    {
        $this->createAccount('cmd-existing-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');

        $command = new CreateUserCommand(
            self::getContainer()->get(UserFactory::class),
            self::getContainer()->get(UserRepository::class),
        );

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('DMARCo has one account, and it already exists.', $tester->getDisplay());
        self::assertStringNotContainsString('Email', $tester->getDisplay());
    }

    public function testDeleteUserCommandSuccess(): void
    {
        $email = 'cmd-delete-'.uniqid('', true).'@example.com';
        $this->createAccount($email, 'Str0ngPassw0rd!@#');

        $command = new DeleteUserCommand(
            self::getContainer()->get(UserRepository::class),
            $this->createStub(LoggerInterface::class),
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
            $this->createStub(LoggerInterface::class),
        );

        $tester = new CommandTester($command);
        $tester->setInputs(['missing@example.com']);
        $tester->execute([]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testDeleteUserCommandFailsWhenTheDeleteFails(): void
    {
        $user = (new User())
            ->setEmail('cmd-delete-fail@example.com')
            ->setName('Delete Fail')
        ;

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['email' => 'cmd-delete-fail@example.com'])
            ->willReturn($user)
        ;
        $userRepository->expects(self::once())->method('delete')->with($user)->willThrowException(new \RuntimeException());

        $command = new DeleteUserCommand($userRepository, $this->createStub(LoggerInterface::class));

        $tester = new CommandTester($command);
        $tester->setInputs(['cmd-delete-fail@example.com']);
        $tester->execute([]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Failed to delete user.', $tester->getDisplay());
    }

    public function testDeleteUserCommandKeepsTheReports(): void
    {
        $email = 'cmd-delete-keeps-'.uniqid('', true).'@example.com';
        $this->createAccount($email, 'Str0ngPassw0rd!@#');
        $domain = new Domain('kept-'.uniqid('', true).'.example');
        $this->entityManager->persist($domain);
        $this->entityManager->flush();

        $command = new DeleteUserCommand(
            self::getContainer()->get(UserRepository::class),
            $this->createStub(LoggerInterface::class),
        );

        $tester = new CommandTester($command);
        $tester->setInputs([$email]);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->entityManager->clear();
        self::assertNotNull($this->entityManager->getRepository(Domain::class)->find($domain->getId()));
    }

    public function testSendWeeklyOverviewCommandDispatches(): void
    {
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(SendWeeklyOverview::class))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message))
        ;

        $tester = new CommandTester(new SendWeeklyOverviewCommand($messageBus));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame('app:weekly-overview:send', new SendWeeklyOverviewCommand($messageBus)->getName());
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

        $command = new ResetUserPasswordCommand($userRepository, $hasher, $this->sessionRevoker($user));

        $tester = new CommandTester($command);
        $tester->setInputs(['reset@example.com', 'Str0ngPassw0rd!']);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame('hashed', $user->getPassword());
        self::assertSame(1, $user->getSessionVersion());
    }

    public function testResetUserPasswordCommandFailsWhenUserMissing(): void
    {
        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn(null);

        $command = new ResetUserPasswordCommand(
            $userRepository,
            $this->createStub(UserPasswordHasherInterface::class),
            $this->sessionRevoker(),
        );

        $tester = new CommandTester($command);
        $tester->setInputs(['missing@example.com']);
        $tester->execute([]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testDisableTwoFactorCommandTurnsItOff(): void
    {
        $user = new User();
        $user
            ->setEmail('lost-phone@example.com')
            ->setTwoFactorMethod(TwoFactorMethod::APP)
            ->setTwoFactorAppSecret('SECRET')
        ;

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn($user);
        $userRepository->expects(self::once())->method('save')->with($user);

        $tester = new CommandTester(new DisableTwoFactorCommand($userRepository));
        $tester->setInputs(['lost-phone@example.com']);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertNull($user->getTwoFactorMethod());
        self::assertNull($user->getTwoFactorAppSecret());
    }

    public function testDisableTwoFactorCommandFailsWhenUserMissing(): void
    {
        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn(null);

        $tester = new CommandTester(new DisableTwoFactorCommand($userRepository));
        $tester->setInputs(['missing@example.com']);
        $tester->execute([]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    private function setEntityId(object $entity, Uuid $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }

    private function sessionRevoker(?User $expectedUser = null): SessionRevoker
    {
        if (null === $expectedUser) {
            $connection = $this->createStub(Connection::class);
        } else {
            $connection = $this->createMock(Connection::class);
            $connection
                ->expects(self::once())
                ->method('delete')
                ->with('refresh_tokens', ['username' => $expectedUser->getUserIdentifier()])
                ->willReturn(1)
            ;
        }
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->method('getTableName')->willReturn('refresh_tokens');
        $metadata->method('getColumnName')->willReturn('username');
        $entityManager->method('getClassMetadata')->willReturn($metadata);

        return new SessionRevoker(
            $entityManager,
            $this->createStub(JWTTokenManagerInterface::class),
            $this->createStub(RefreshTokenGeneratorInterface::class),
            $this->createStub(RefreshTokenManagerInterface::class),
            2592000,
        );
    }
}
