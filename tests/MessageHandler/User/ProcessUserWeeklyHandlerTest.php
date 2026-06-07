<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler\User;

use App\DTO\Output\User\Dashboard;
use App\Entity\User\User;
use App\Message\User\ProcessUserWeekly;
use App\MessageHandler\User\ProcessUserWeeklyHandler;
use App\Repository\DMARC\DomainRepository;
use App\Repository\DMARC\ReportRepository;
use App\Repository\User\UserRepository;
use App\Service\User\DashboardMetricsProvider;
use App\Service\User\DMARC\WeeklyOverviewMailer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;

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
            (new \ReflectionClass(DashboardMetricsProvider::class))->newInstanceWithoutConstructor(),
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

        $handler = new ProcessUserWeeklyHandler(
            $userRepository,
            (new \ReflectionClass(DashboardMetricsProvider::class))->newInstanceWithoutConstructor(),
            new WeeklyOverviewMailer($this->createStub(MailerInterface::class)),
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

        $handler = new ProcessUserWeeklyHandler(
            $userRepository,
            (new \ReflectionClass(DashboardMetricsProvider::class))->newInstanceWithoutConstructor(),
            new WeeklyOverviewMailer($this->createStub(MailerInterface::class)),
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

        $dashboard = new Dashboard(
            reportCount: 1,
            reportTrend: 0.0,
            messageCount: 2,
            messageTrend: 0.0,
            passRate: 0.75,
            passTrend: 0.0,
            threatsBlockedCount: 3,
            threatsBlockedTrend: 0.0,
            messageCountByDate: [],
            threatCountByDate: [],
            compliance: ['dmarc' => 90.0, 'dkim' => 80.0, 'spf' => 70.0],
            complianceTrend: ['dmarc' => 0.0, 'dkim' => 0.0, 'spf' => 0.0],
            newDomainCount: 0,
            newDomainTrend: 0.0,
            newDomains: [],
            topOffenders: [],
            topSenders: [],
            sourceCountryDistribution: [],
            topReportingOrganizations: [],
        );

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::once())->method('find')->willReturn($user);

        $reportRepository = $this->createStub(ReportRepository::class);
        $reportRepository->method('getReportCountInDatePeriod')->willReturn(1);
        $reportRepository->method('getSumCountInDatePeriod')->willReturn(2);
        $reportRepository->method('getPassRate')->willReturn(0.75);
        $reportRepository->method('getThreatCount')->willReturn(3);
        $reportRepository->method('getMessageCountByDate')->willReturn([]);
        $reportRepository->method('getThreatCountByDate')->willReturn([]);
        $reportRepository
            ->method('getComplianceAverage')
            ->willReturn([['dmarc' => 90.0, 'dkim' => 80.0, 'spf' => 70.0]])
        ;
        $reportRepository->method('getOffendersInDatePeriod')->willReturn([]);
        $reportRepository->method('getTopSendersInDatePeriod')->willReturn([]);
        $reportRepository->method('getSourceCountryDistributionInDatePeriod')->willReturn([]);
        $reportRepository->method('getTopReportingOrganizationsInDatePeriod')->willReturn([]);

        $domainRepository = $this->createStub(DomainRepository::class);
        $domainRepository->method('getNewDomainCount')->willReturn(0);
        $domainRepository->method('getNewDomains')->willReturn([]);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer
            ->expects(self::once())
            ->method('send')
            ->with(self::isInstanceOf(RawMessage::class))
        ;

        $handler = new ProcessUserWeeklyHandler(
            $userRepository,
            new DashboardMetricsProvider($reportRepository, $domainRepository),
            new WeeklyOverviewMailer($mailer),
        );

        $handler(new ProcessUserWeekly('user-id'));
    }
}
