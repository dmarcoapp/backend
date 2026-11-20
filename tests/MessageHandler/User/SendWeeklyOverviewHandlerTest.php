<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler\User;

use App\Entity\User\User;
use App\Message\User\SendWeeklyOverview;
use App\MessageHandler\User\SendWeeklyOverviewHandler;
use App\Repository\DMARC\DomainRepository;
use App\Repository\DMARC\ReportRepository;
use App\Repository\User\UserRepository;
use App\Service\User\DMARC\WeeklyOverviewMailer;
use App\Service\User\DMARC\WeeklyOverviewProvider;
use App\Tests\Support\PlatformSettingsTestFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Mailer\MailerInterface;

/**
 * @internal
 */
#[CoversClass(SendWeeklyOverviewHandler::class)]
final class SendWeeklyOverviewHandlerTest extends TestCase
{
    public function testHandlerReturnsEarlyWithoutAnAccount(): void
    {
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::once())->method('findAccount')->willReturn(null);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $handler = new SendWeeklyOverviewHandler(
            $userRepository,
            (new \ReflectionClass(WeeklyOverviewProvider::class))->newInstanceWithoutConstructor(),
            new WeeklyOverviewMailer($mailer),
        );

        $handler(new SendWeeklyOverview());
    }

    public function testHandlerSkipsWhenNotificationDisabled(): void
    {
        $user = new User();
        $user
            ->setEmail('weekly-disabled@example.com')
            ->setName('Weekly Disabled')
            ->setWeeklyOverviewNotificationEnabled(false)
        ;

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::once())->method('findAccount')->willReturn($user);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $handler = new SendWeeklyOverviewHandler(
            $userRepository,
            (new \ReflectionClass(WeeklyOverviewProvider::class))->newInstanceWithoutConstructor(),
            new WeeklyOverviewMailer($mailer),
        );

        $handler(new SendWeeklyOverview());
    }

    public function testHandlerSkipsAnAccountYoungerThanAWeek(): void
    {
        $user = new User();
        $user
            ->setEmail('weekly-new@example.com')
            ->setName('Weekly New')
            ->setCreatedAt(new \DateTimeImmutable('-3 days', new \DateTimeZone('UTC')))
        ;

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::once())->method('findAccount')->willReturn($user);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $handler = new SendWeeklyOverviewHandler(
            $userRepository,
            (new \ReflectionClass(WeeklyOverviewProvider::class))->newInstanceWithoutConstructor(),
            new WeeklyOverviewMailer($mailer),
        );

        $handler(new SendWeeklyOverview());
    }

    public function testHandlerSendsWeeklyOverviewWhenEnabled(): void
    {
        $user = new User();
        $user
            ->setEmail('weekly@example.com')
            ->setName('Weekly User')
            ->setCreatedAt(new \DateTimeImmutable('-8 days', new \DateTimeZone('UTC')))
        ;

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::once())->method('findAccount')->willReturn($user);

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
            ->with(self::callback(static fn (TemplatedEmail $email): bool => str_ends_with((string) $email->getSubject(), 'no reports received')
                && 'weekly@example.com' === $email->getTo()[0]->getAddress()))
        ;

        $handler = new SendWeeklyOverviewHandler(
            $userRepository,
            new WeeklyOverviewProvider(
                $reportRepository,
                $domainRepository,
                PlatformSettingsTestFactory::intakeAddresses(PlatformSettingsTestFactory::gatewaySettings('postbox-token', 'aggregate-reports.example.test')),
                new MockClock('2026-10-20 06:00:00 UTC'),
            ),
            new WeeklyOverviewMailer($mailer),
        );

        $handler(new SendWeeklyOverview());
    }
}
