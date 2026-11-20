<?php

declare(strict_types=1);

namespace App\Tests\Scheduler;

use App\Entity\DMARC\Domain;
use App\Message\DMARC\DeleteExpiredReports;
use App\Message\DMARC\ProcessDomain;
use App\Message\DMARC\ProcessNewDomains;
use App\Message\User\SendWeeklyOverview;
use App\Repository\DMARC\DomainRepository;
use App\Repository\Email\EmailRepository;
use App\Repository\User\AuthLogRepository;
use App\Scheduler\Auth\ClearInvalidRefreshTokens;
use App\Scheduler\Auth\ClearOldAuthLogs;
use App\Scheduler\DMARC\DeleteExpiredReportsProducer;
use App\Scheduler\DMARC\ProcessDomainProducer;
use App\Scheduler\DMARC\ProcessNewDomainsProducer;
use App\Scheduler\Email\ClearInvalidEmails;
use App\Scheduler\User\SendWeeklyOverviewProducer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(ClearInvalidRefreshTokens::class)]
#[CoversClass(ClearOldAuthLogs::class)]
#[CoversClass(DeleteExpiredReportsProducer::class)]
#[CoversClass(ProcessNewDomainsProducer::class)]
#[CoversClass(SendWeeklyOverviewProducer::class)]
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

    /**
     * @return iterable<string, array{callable(MessageBusInterface): callable, class-string}>
     */
    public static function singleTaskProvider(): iterable
    {
        yield 'expired reports' => [static fn (MessageBusInterface $bus): callable => new DeleteExpiredReportsProducer($bus), DeleteExpiredReports::class];
        yield 'new domains' => [static fn (MessageBusInterface $bus): callable => new ProcessNewDomainsProducer($bus), ProcessNewDomains::class];
        yield 'weekly overview' => [static fn (MessageBusInterface $bus): callable => new SendWeeklyOverviewProducer($bus), SendWeeklyOverview::class];
    }

    /**
     * @param callable(MessageBusInterface): callable $producer
     * @param class-string                            $messageClass
     */
    #[DataProvider('singleTaskProvider')]
    public function testSingleTaskProducerDispatchesOneMessage(callable $producer, string $messageClass): void
    {
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf($messageClass))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message))
        ;

        $producer($messageBus)();
    }

    public function testProcessDomainProducerDispatches(): void
    {
        $domain = new Domain('example.com');
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
        $domain = new Domain('example.com');

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
