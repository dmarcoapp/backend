<?php

declare(strict_types=1);

namespace App\Tests\Service\User\DMARC;

use App\DTO\Output\User\WeeklyOverview\DomainSummary;
use App\DTO\Output\User\WeeklyOverview\TrafficTotals;
use App\DTO\Output\User\WeeklyOverview\WeeklyOverview;
use App\Entity\User\User;
use App\Service\User\DMARC\WeeklyOverviewMailer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

/**
 * @internal
 */
#[CoversClass(WeeklyOverviewMailer::class)]
final class WeeklyOverviewMailerTest extends TestCase
{
    public function testSendUsesMailerWhenUserHasEmailAndNotificationEnabled(): void
    {
        $overview = WeeklyOverviewFixture::needingAttention();

        $mailer = $this->createMock(MailerInterface::class);
        $mailer
            ->expects(self::once())
            ->method('send')
            ->with(self::callback(static function (TemplatedEmail $email) use ($overview): bool {
                self::assertSame('weekly@example.com', $email->getTo()[0]->getAddress());
                self::assertSame('DMARC weekly, Oct 12–18: example.org needs attention', $email->getSubject());
                self::assertSame('emails/user/dmarc/weekly-overview.html.twig', $email->getHtmlTemplate());
                self::assertSame('emails/user/dmarc/weekly-overview.txt.twig', $email->getTextTemplate());
                self::assertSame(['overview' => $overview, 'recipient' => 'weekly@example.com'], $email->getContext());

                return true;
            }))
        ;

        new WeeklyOverviewMailer($mailer)->send($this->createUser(), $overview);
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

        new WeeklyOverviewMailer($mailer)->send($user, WeeklyOverviewFixture::healthy());
    }

    public function testSendDoesNothingWhenNotificationDisabled(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        new WeeklyOverviewMailer($mailer)->send(
            $this->createUser()->setWeeklyOverviewNotificationEnabled(false),
            WeeklyOverviewFixture::healthy(),
        );
    }

    public function testSubjectSaysWhatTheWeekCameDownTo(): void
    {
        self::assertSame('DMARC weekly, Oct 12–18: no issues found', WeeklyOverviewMailer::subject(WeeklyOverviewFixture::healthy()));
        self::assertSame('DMARC weekly, Oct 12–18: no reports received', WeeklyOverviewMailer::subject(WeeklyOverviewFixture::withoutReports()));

        $attention = static fn (string $name): DomainSummary => new DomainSummary(
            domain: $name,
            domainId: null,
            policy: 'none',
            policyPercent: null,
            subdomainPolicy: null,
            isNew: false,
            needsAttention: true,
            traffic: new TrafficTotals(10, 5, 5, 0),
        );
        $base = WeeklyOverviewFixture::healthy();
        $twoDomains = new WeeklyOverview(
            periodStart: $base->periodStart,
            periodEnd: $base->periodEnd,
            previousPeriodStart: $base->previousPeriodStart,
            previousPeriodEnd: $base->previousPeriodEnd,
            reportCount: $base->reportCount,
            reportingOrganizations: $base->reportingOrganizations,
            traffic: $base->traffic,
            previousTraffic: $base->previousTraffic,
            domains: [$attention('example.org'), $attention('example.net')],
            findings: [],
            reportAddress: $base->reportAddress,
        );

        self::assertSame('DMARC weekly, Oct 12–18: 2 domains need attention', WeeklyOverviewMailer::subject($twoDomains));
    }

    private function createUser(): User
    {
        $user = new User();
        $user
            ->setName('Weekly User')
            ->setEmail('weekly@example.com')
            ->setSharedPostboxIdentifierToken('token')
        ;

        return $user;
    }
}
