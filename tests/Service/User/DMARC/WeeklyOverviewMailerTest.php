<?php

declare(strict_types=1);

namespace App\Tests\Service\User\DMARC;

use App\DTO\Output\User\Dashboard;
use App\Entity\User\User;
use App\Service\User\DMARC\WeeklyOverviewMailer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * @internal
 */
#[CoversClass(WeeklyOverviewMailer::class)]
final class WeeklyOverviewMailerTest extends TestCase
{
    public function testSendUsesMailerWhenUserHasEmailAndNotificationEnabled(): void
    {
        $user = new User();
        $user
            ->setName('Weekly User')
            ->setEmail('weekly@example.com')
            ->setSharedPostboxIdentifierToken('token')
        ;

        $mailer = $this->createMock(MailerInterface::class);
        $mailer
            ->expects(self::once())
            ->method('send')
            ->with(self::isInstanceOf(RawMessage::class))
        ;

        $service = new WeeklyOverviewMailer($mailer);
        $service->send(
            $user,
            $this->createDashboard(),
            new \DateTimeImmutable('2025-01-05 00:00:00'),
            new \DateTimeImmutable('2025-01-11 23:59:59'),
        );
    }

    public function testSendDoesNothingWhenUserEmailIsMissing(): void
    {
        $user = new User();
        $user
            ->setName('Weekly User')
            ->setSharedPostboxIdentifierToken('token')
        ;

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $service = new WeeklyOverviewMailer($mailer);
        $service->send(
            $user,
            $this->createDashboard(),
            new \DateTimeImmutable('2025-01-05 00:00:00'),
            new \DateTimeImmutable('2025-01-11 23:59:59'),
        );
    }

    public function testSendDoesNothingWhenNotificationDisabled(): void
    {
        $user = new User();
        $user
            ->setName('Weekly User')
            ->setEmail('weekly@example.com')
            ->setSharedPostboxIdentifierToken('token')
            ->setWeeklyOverviewNotificationEnabled(false)
        ;

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $service = new WeeklyOverviewMailer($mailer);
        $service->send(
            $user,
            $this->createDashboard(),
            new \DateTimeImmutable('2025-01-05 00:00:00'),
            new \DateTimeImmutable('2025-01-11 23:59:59'),
        );
    }

    private function createDashboard(): Dashboard
    {
        return new Dashboard(
            reportCount: 10,
            reportTrend: 5.0,
            messageCount: 100,
            messageTrend: 10.0,
            passRate: 0.9,
            passTrend: 2.0,
            threatsBlockedCount: 3,
            threatsBlockedTrend: -5.0,
            messageCountByDate: ['2025-01-05' => 1],
            threatCountByDate: ['2025-01-05' => 0],
            compliance: ['dmarc' => 95.0, 'dkim' => 90.0, 'spf' => 85.0],
            complianceTrend: ['dmarc' => 1.0, 'dkim' => 1.5, 'spf' => 2.0],
            newDomainCount: 1,
            newDomainTrend: 0.0,
            newDomains: ['example.com'],
            topOffenders: [[
                'count' => 2,
                'sourceIp' => '198.51.100.1',
                'sourceIpInfo' => [
                    'orgAbuseEmail' => 'abuse@example.net',
                    'orgCountry' => 'HU',
                    'orgName' => 'Example Org',
                    'orgTechEmail' => 'tech@example.net',
                ],
            ]],
            topSenders: [[
                'count' => 4,
                'sourceIp' => '198.51.100.2',
                'sourceIpInfo' => [
                    'orgAbuseEmail' => 'abuse-sender@example.net',
                    'orgCountry' => 'US',
                    'orgName' => 'Sender Org',
                    'orgTechEmail' => 'tech-sender@example.net',
                ],
            ]],
            sourceCountryDistribution: [[
                'count' => 4,
                'country' => 'US',
            ]],
            topReportingOrganizations: [[
                'count' => 3,
                'reportingOrganization' => 'Example Reporter',
            ]],
        );
    }
}
