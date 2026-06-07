<?php

declare(strict_types=1);

namespace App\Tests\Scheduler;

use App\Entity\DMARC\Domain;
use App\Entity\User\User;
use App\Message\DMARC\ProcessDomain;
use App\Message\User\ProcessUserDaily;
use App\Message\User\ProcessUserHourly;
use App\Message\User\ProcessUserWeekly;
use App\Repository\DMARC\DomainRepository;
use App\Repository\Email\EmailRepository;
use App\Repository\User\AuthLogRepository;
use App\Repository\User\UserRepository;
use App\Scheduler\Auth\ClearInvalidRefreshTokens;
use App\Scheduler\Auth\ClearOldAuthLogs;
use App\Scheduler\DMARC\ProcessDomainProducer;
use App\Scheduler\Email\ClearInvalidEmails;
use App\Scheduler\User\ClearUnverifiedUsers;
use App\Scheduler\User\ProcessUserDailyProducer;
use App\Scheduler\User\ProcessUserHourlyProducer;
use App\Scheduler\User\ProcessUserWeeklyProducer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(ClearInvalidRefreshTokens::class)]
#[CoversClass(ClearOldAuthLogs::class)]
#[CoversClass(ClearUnverifiedUsers::class)]
#[CoversClass(ProcessUserDailyProducer::class)]
#[CoversClass(ProcessUserHourlyProducer::class)]
#[CoversClass(ProcessUserWeeklyProducer::class)]
#[CoversClass(ProcessDomainProducer::class)]
#[CoversClass(ClearInvalidEmails::class)]
final class SchedulerTest extends TestCase
{
    public function testClearInvalidRefreshTokensRuns(): void
    {
        $scheduler = new ClearInvalidRefreshTokens();

        $scheduler();

        self::assertTrue(true);
    }

    public function testClearUnverifiedUsersDeletesInChunks(): void
    {
        $repository = $this->createMock(UserRepository::class);
        $repository->method('getUnverifiedUserIdsToDelete')->willReturn(['a', 'b']);
        $repository->expects(self::once())->method('deleteByIds')->with(['a', 'b']);

        $scheduler = new ClearUnverifiedUsers($repository);

        $scheduler();
    }

    public function testClearOldAuthLogsDeletesOlderThanThreshold(): void
    {
        $repository = $this->createMock(AuthLogRepository::class);
        $repository
            ->expects(self::once())
            ->method('deleteOlderThan')
            ->with(self::isInstanceOf(\DateTimeImmutable::class))
        ;

        $scheduler = new ClearOldAuthLogs($repository, '30 days');

        $scheduler();
    }

    public function testClearOldAuthLogsThrowsForInvalidRetentionInterval(): void
    {
        $repository = $this->createMock(AuthLogRepository::class);
        $repository->expects(self::never())->method('deleteOlderThan');

        $scheduler = new ClearOldAuthLogs($repository, 'invalid interval');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Invalid auth log retention interval.');
        $scheduler();
    }

    public function testProcessUserDailyProducerDispatches(): void
    {
        $user = new User();
        $this->setEntityId($user, Uuid::v4());

        $repository = $this->createStub(UserRepository::class);
        $repository->method('findAll')->willReturn([$user]);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(ProcessUserDaily::class))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message))
        ;

        $scheduler = new ProcessUserDailyProducer($repository, $messageBus);

        $scheduler();
    }

    public function testProcessUserDailyProducerSkipsUserWithoutId(): void
    {
        $repository = $this->createStub(UserRepository::class);
        $repository->method('findAll')->willReturn([new User()]);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        $scheduler = new ProcessUserDailyProducer($repository, $messageBus);

        $scheduler();
    }

    public function testProcessUserGeneralProducerDispatches(): void
    {
        $user = new User();
        $this->setEntityId($user, Uuid::v4());

        $repository = $this->createStub(UserRepository::class);
        $repository->method('findAll')->willReturn([$user]);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(ProcessUserHourly::class))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message))
        ;

        $scheduler = new ProcessUserHourlyProducer($repository, $messageBus);

        $scheduler();
    }

    public function testProcessUserGeneralProducerSkipsUserWithoutId(): void
    {
        $repository = $this->createStub(UserRepository::class);
        $repository->method('findAll')->willReturn([new User()]);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        $scheduler = new ProcessUserHourlyProducer($repository, $messageBus);

        $scheduler();
    }

    public function testProcessUserWeeklySummaryProducerDispatches(): void
    {
        $user = new User();
        $this->setEntityId($user, Uuid::v4());

        $repository = $this->createStub(UserRepository::class);
        $repository->method('findAll')->willReturn([$user]);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(ProcessUserWeekly::class))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message))
        ;

        $scheduler = new ProcessUserWeeklyProducer($repository, $messageBus);

        $scheduler();
    }

    public function testProcessUserWeeklySummaryProducerSkipsUserWithoutId(): void
    {
        $repository = $this->createStub(UserRepository::class);
        $repository->method('findAll')->willReturn([new User()]);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        $scheduler = new ProcessUserWeeklyProducer($repository, $messageBus);

        $scheduler();
    }

    public function testProcessDomainProducerDispatches(): void
    {
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setSharedPostboxIdentifierToken('token');

        $domain = new Domain($user, 'example.com');
        $this->setEntityId($domain, Uuid::v4());

        $repository = $this->createStub(DomainRepository::class);
        $repository->method('getDomainsForRegularProcessing')->willReturn([$domain]);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(ProcessDomain::class))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message))
        ;

        $scheduler = new ProcessDomainProducer($repository, $messageBus);

        $scheduler();
    }

    public function testProcessDomainProducerSkipsDomainWithoutId(): void
    {
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setSharedPostboxIdentifierToken('token');

        $domain = new Domain($user, 'example.com');

        $repository = $this->createStub(DomainRepository::class);
        $repository->method('getDomainsForRegularProcessing')->willReturn([$domain]);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        $scheduler = new ProcessDomainProducer($repository, $messageBus);

        $scheduler();
    }

    public function testClearInvalidEmailsDeletesInChunks(): void
    {
        $repository = $this->createMock(EmailRepository::class);
        $repository->method('getJunkEmailIdsToDelete')->willReturn(['a', 'b']);
        $repository->expects(self::once())->method('deleteByIds')->with(['a', 'b']);

        $scheduler = new ClearInvalidEmails($repository);

        $scheduler();
    }

    private function setEntityId(object $entity, Uuid $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }
}
