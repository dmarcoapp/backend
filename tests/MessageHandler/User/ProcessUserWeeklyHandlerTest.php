<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler\User;

use App\Entity\User\User;
use App\Message\User\ProcessUserWeekly;
use App\MessageHandler\User\ProcessUserWeeklyHandler;
use App\Repository\DMARC\DomainRepository;
use App\Repository\DMARC\ReportRepository;
use App\Repository\User\UserRepository;
use App\Service\Email\AggregateReportPostboxAddressProvider;
use App\Service\User\DMARC\WeeklyOverviewMailer;
use App\Service\User\DMARC\WeeklyOverviewProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Mailer\MailerInterface;

/**
 * @internal
 */
#[CoversClass(ProcessUserWeeklyHandler::class)]
final class ProcessUserWeeklyHandlerTest extends TestCase
{
    public function testHandlerReturnsEarlyWhenUserMissing(): void
    {
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::once())->method('find')->willReturn(null);

        $handler = new ProcessUserWeeklyHandler(
            $userRepository,
            (new \ReflectionClass(WeeklyOverviewProvider::class))->newInstanceWithoutConstructor(),
            new WeeklyOverviewMailer($this->createStub(MailerInterface::class)),
        );

        $handler(new ProcessUserWeekly('missing-user-id'));
    }

    public function testHandlerSkipsWhenNotificationDisabled(): void
    {
        $user = new User();
        $user
            ->setEmail('weekly-disabled@example.com')
            ->setName('Weekly Disabled')
            ->setSharedPostboxIdentifierToken('weekly-disabled-token')
            ->setWeeklyOverviewNotificationEnabled(false)
        ;

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::once())->method('find')->willReturn($user);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $handler = new ProcessUserWeeklyHandler(
            $userRepository,
            (new \ReflectionClass(WeeklyOverviewProvider::class))->newInstanceWithoutConstructor(),
            new WeeklyOverviewMailer($mailer),
        );

        $handler(new ProcessUserWeekly('user-id'));
    }

    public function testHandlerSkipsWhenUserIsRegisteredLessThanAWeek(): void
    {
        $user = new User();
        $user
            ->setEmail('weekly-new@example.com')
            ->setName('Weekly New')
            ->setSharedPostboxIdentifierToken('weekly-new-token')
            ->setCreatedAt(new \DateTimeImmutable('-3 days', new \DateTimeZone('UTC')))
        ;

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::once())->method('find')->willReturn($user);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $handler = new ProcessUserWeeklyHandler(
            $userRepository,
            (new \ReflectionClass(WeeklyOverviewProvider::class))->newInstanceWithoutConstructor(),
            new WeeklyOverviewMailer($mailer),
        );

        $handler(new ProcessUserWeekly('user-id'));
    }

    public function testHandlerSendsWeeklyOverviewWhenEnabled(): void
    {
        $user = new User();
        $user
            ->setEmail('weekly@example.com')
            ->setName('Weekly User')
            ->setSharedPostboxIdentifierToken('weekly-token')
            ->setCreatedAt(new \DateTimeImmutable('-8 days', new \DateTimeZone('UTC')))
        ;

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::once())->method('find')->willReturn($user);

        $reportRepository = $this->createStub(ReportRepository::class);
        $reportRepository->method('getFailingSources')->willReturn([]);
        $reportRepository->method('getTrafficByDomain')->willReturn([]);
        $reportRepository->method('getLatestPolicyByDomain')->willReturn([]);
        $reportRepository->method('getTopReportingOrganizationsInDatePeriod')->willReturn([]);
        $reportRepository->method('getReportCountInDatePeriod')->willReturn(0);

        $domainRepository = $this->createStub(DomainRepository::class);
        $domainRepository->method('findBy')->willReturn([]);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer
            ->expects(self::once())
            ->method('send')
            ->with(self::callback(static fn (TemplatedEmail $email): bool => str_ends_with((string) $email->getSubject(), 'no reports received')))
        ;

        $handler = new ProcessUserWeeklyHandler(
            $userRepository,
            new WeeklyOverviewProvider(
                $reportRepository,
                $domainRepository,
                new AggregateReportPostboxAddressProvider('aggregate-reports.example.test'),
                new MockClock('2026-10-20 06:00:00 UTC'),
            ),
            new WeeklyOverviewMailer($mailer),
        );

        $handler(new ProcessUserWeekly('user-id'));
    }
}
