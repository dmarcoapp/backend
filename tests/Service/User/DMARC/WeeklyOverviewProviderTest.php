<?php

declare(strict_types=1);

namespace App\Tests\Service\User\DMARC;

use App\DTO\Output\User\WeeklyOverview\DomainSummary;
use App\DTO\Output\User\WeeklyOverview\Finding;
use App\DTO\Output\User\WeeklyOverview\WeeklyOverview;
use App\Entity\DMARC\Domain;
use App\Entity\User\User;
use App\Repository\DMARC\DomainRepository;
use App\Repository\DMARC\ReportRepository;
use App\Service\Email\AggregateReportPostboxAddressProvider;
use App\Service\User\DMARC\WeeklyOverviewProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(WeeklyOverviewProvider::class)]
#[CoversClass(DomainSummary::class)]
final class WeeklyOverviewProviderTest extends TestCase
{
    private const string MONITORED_ID = '0192e0c4-0000-7000-8000-000000000001';

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function sendDates(): iterable
    {
        yield 'Tuesday morning' => ['2026-10-20 06:00', '2026-10-12', '2026-10-18'];
        yield 'later in the week' => ['2026-10-23 15:00', '2026-10-12', '2026-10-18'];
        yield 'Monday, a day early' => ['2026-10-19 00:01', '2026-10-12', '2026-10-18'];
        yield 'Sunday night' => ['2026-10-25 23:59', '2026-10-12', '2026-10-18'];
    }

    #[DataProvider('sendDates')]
    public function testCoversTheLastFullMondayToSundayWeek(string $now, string $start, string $end): void
    {
        $overview = self::provide(self::user(), $now, traffic: [], previousTraffic: [], policies: [], failingSources: [], knownDomains: []);

        self::assertSame($start, $overview->periodStart->format('Y-m-d'));
        self::assertSame($end, $overview->periodEnd->format('Y-m-d'));
        self::assertEquals($overview->periodStart->modify('-7 days'), $overview->previousPeriodStart);
        self::assertEquals($overview->periodStart->modify('-1 day'), $overview->previousPeriodEnd);
    }

    public function testBuildsDomainsAndFindingsFromTheWeek(): void
    {
        $user = self::user();

        $overview = self::provide(
            $user,
            '2026-10-20 06:00',
            traffic: [
                self::traffic('example.com', 7000, 6900, 5, 95),
                self::traffic('example.org', 2000, 1000, 1000, 0),
                self::traffic('example.net', 500, 450, 0, 50),
                self::traffic('partial.example', 400, 350, 30, 20),
                self::traffic('open.example', 300, 291, 9, 0),
                self::traffic('enforced.example', 300, 291, 9, 0),
                // Reports anyone can send name whatever they like.
                self::traffic('your bank, act now', 1000, 0, 1000, 0),
            ],
            previousTraffic: [
                self::traffic('example.com', 6000, 5900, 0, 100),
                self::traffic('quiet.example', 120, 120, 0, 0),
            ],
            policies: [
                'enforced.example' => self::policy('reject'),
                'example.com' => self::policy('reject', 100),
                'example.net' => self::policy('quarantine'),
                'example.org' => self::policy('none'),
                'open.example' => self::policy('reject', null, 'none'),
                'partial.example' => self::policy('quarantine', 50),
            ],
            failingSources: [
                // One service, two addresses, DKIM-signing with its own domain.
                self::source('example.org', '198.51.100.10', 'fail/pass', 'example.org/mailer.example', 600, delivered: 600, orgName: 'Mail Service'),
                self::source('example.org', '198.51.100.11', 'pass', 'mailer.example', 100, delivered: 100, orgName: 'Mail Service'),
                // Passing SPF for its own domain is what forgers do: no service.
                self::source('example.org', '192.0.2.40', 'none', '', 20, delivered: 20, spfAuth: 'pass', spfDomain: 'bounce.crm.example'),
                // Signed for the domain itself, or for one of its subdomains
                // under strict alignment: no third party either.
                self::source('example.org', '203.0.113.5', 'pass', 'example.org', 20, delivered: 20),
                self::source('example.org', '203.0.113.6', 'pass', 'news.example.org', 10, delivered: 10),
                self::source('example.org', '203.0.113.61', 'none', '', 280, delivered: 280, orgName: 'Hosting', orgCountry: 'Poland'),
                // A service whose mail an enforced policy rejected still needs attention.
                self::source('example.com', '192.0.2.20', 'pass', 'crm-mailer.example', 80, rejected: 80, orgName: 'CRM'),
                // A mailing list signing again: under 1% of the domain's mail.
                self::source('example.com', '192.0.2.30', 'pass', 'lists.example', 5, delivered: 5),
                // Forgeries the policy blocked are the policy working.
                self::source('example.com', '203.0.113.99', 'fail', 'example.com', 15, rejected: 10, quarantined: 5),
                self::source('partial.example', '203.0.113.70', 'none', '', 30, delivered: 30),
                self::source('open.example', '203.0.113.75', 'none', '', 9, delivered: 9),
                // Delivered by the receiver's choice under p=reject.
                self::source('enforced.example', '203.0.113.80', 'none', '', 9, delivered: 9),
                self::source('your bank, act now', '203.0.113.90', 'pass', 'evil.example', 1000, delivered: 1000),
            ],
            knownDomains: [
                self::domain($user, 'Example.org', '0192e0c4-0000-7000-8000-000000000009', '2026-08-01'),
                self::domain($user, 'example.org', self::MONITORED_ID, '2026-08-02'),
                self::domain($user, 'quiet.example', '0192e0c4-0000-7000-8000-000000000004', '2026-08-01'),
            ],
            firstReports: [
                'example.net' => '2026-10-14',
                'example.org' => '2026-08-02',
            ],
        );

        self::assertSame(57, $overview->reportCount);
        self::assertSame(['google.com', 'Yahoo'], $overview->reportingOrganizations);
        self::assertSame('postbox-token@aggregate-reports.example.test', $overview->reportAddress);
        self::assertSame(10500, $overview->traffic->messages);
        self::assertSame(1053, $overview->traffic->failedDelivered);
        self::assertSame(6120, $overview->previousTraffic->messages);

        self::assertSame(
            ['example.com', 'example.org', 'partial.example', 'open.example', 'example.net', 'enforced.example'],
            array_map(static fn (DomainSummary $domain): string => $domain->domain, $overview->domains),
        );
        [$serviceRejected, $monitored, $partial, $open, $new, $enforced] = $overview->domains;

        self::assertTrue($serviceRejected->needsAttention);
        self::assertTrue($serviceRejected->isEnforced());
        self::assertNull($serviceRejected->domainId);

        self::assertTrue($monitored->needsAttention);
        // The lower-case spelling wins over the older mixed-case one.
        self::assertSame(self::MONITORED_ID, $monitored->domainId);
        self::assertFalse($monitored->isNew);

        self::assertTrue($partial->needsAttention);
        self::assertTrue($partial->coversPartOfMail());

        self::assertTrue($open->needsAttention);
        self::assertTrue($open->leavesSubdomainsOpen());
        self::assertFalse($open->isEnforced());

        self::assertTrue($new->isNew);
        self::assertFalse($new->needsAttention);

        // Failing mail delivered under p=reject was the receivers' choice.
        self::assertFalse($enforced->needsAttention);

        self::assertSame(
            [
                'unaligned_service example.org mailer.example',
                'unaligned_service example.com crm-mailer.example',
                'delivered_failures example.org',
                'delivered_failures partial.example',
                'delivered_failures open.example',
                'new_domain example.net',
                'quiet_domain quiet.example',
            ],
            array_map(
                static fn (Finding $finding): string => trim($finding->type->value.' '.$finding->domain->domain.' '.$finding->signingDomain),
                $overview->findings,
            ),
        );

        [$service, $crm, $failures] = $overview->findings;
        self::assertSame([700, 700, 2], [$service->messages, $service->delivered, $service->sourceCount]);
        self::assertSame('198.51.100.10', $service->topSource?->ip);
        self::assertSame('Mail Service', $service->topSource?->orgName);
        self::assertSame([80, 0, 80, 0], [$crm->messages, $crm->delivered, $crm->rejected, $crm->quarantined]);

        // Everything delivered that is not the named service is accounted for.
        self::assertSame([330, 4], [$failures->delivered, $failures->sourceCount]);
        self::assertSame('203.0.113.61', $failures->topSource?->ip);
        self::assertSame('Poland', $failures->topSource?->orgCountry);

        $quiet = $overview->findings[6];
        self::assertSame(120, $quiet->messages);
        self::assertSame('0192e0c4-0000-7000-8000-000000000004', $quiet->domain->domainId);
    }

    /**
     * @param list<array{domain: string, messages: int, passed: int, failedDelivered: int, blocked: int}>                                                                                                                     $traffic
     * @param list<array{domain: string, messages: int, passed: int, failedDelivered: int, blocked: int}>                                                                                                                     $previousTraffic
     * @param array<string, array{policy: string, percent: ?int, subdomainPolicy: ?string}>                                                                                                                                  $policies
     * @param list<array{domain: string, sourceIp: string, orgName: ?string, orgCountry: ?string, dkimAuth: string, dkimDomain: string, spfAuth: string, spfDomain: string, messages: int, delivered: int, quarantined: int, rejected: int}> $failingSources
     * @param list<Domain>                                                                                                                                                                                                    $knownDomains
     * @param array<string, string>                                                                                                                                                                                           $firstReports
     */
    private static function provide(
        User $user,
        string $now,
        array $traffic,
        array $previousTraffic,
        array $policies,
        array $failingSources,
        array $knownDomains,
        array $firstReports = [],
    ): WeeklyOverview {
        $reportRepository = self::createStub(ReportRepository::class);
        $reportRepository->method('getTrafficByDomain')->willReturnCallback(
            static fn (\DatePeriod $period): array => '2026-10-12' === $period->getStartDate()->format('Y-m-d') ? $traffic : $previousTraffic,
        );
        $reportRepository->method('getLatestPolicyByDomain')->willReturn($policies);
        $reportRepository->method('getFailingSources')->willReturn($failingSources);
        $reportRepository->method('getFirstReportDateByDomain')->willReturn(array_map(
            static fn (string $date): \DateTimeImmutable => new \DateTimeImmutable($date.' UTC'),
            $firstReports,
        ));
        $reportRepository->method('getTopReportingOrganizationsInDatePeriod')->willReturn([
            ['count' => 30, 'reportingOrganization' => 'google.com'],
            ['count' => 27, 'reportingOrganization' => 'Yahoo'],
            ['count' => 1, 'reportingOrganization' => null],
        ]);
        $reportRepository->method('getReportCountInDatePeriod')->willReturn(57);

        $domainRepository = self::createStub(DomainRepository::class);
        $domainRepository->method('findBy')->willReturn($knownDomains);

        return new WeeklyOverviewProvider(
            $reportRepository,
            $domainRepository,
            new AggregateReportPostboxAddressProvider('aggregate-reports.example.test'),
            new MockClock($now.' UTC'),
        )->provide($user);
    }

    private static function user(): User
    {
        $user = new User();
        $user
            ->setEmail('weekly@example.com')
            ->setName('Weekly User')
            ->setSharedPostboxIdentifierToken('postbox-token')
        ;

        return $user;
    }

    /**
     * @return array{domain: string, messages: int, passed: int, failedDelivered: int, blocked: int}
     */
    private static function traffic(string $domain, int $messages, int $passed, int $failedDelivered, int $blocked): array
    {
        return ['domain' => $domain, 'messages' => $messages, 'passed' => $passed, 'failedDelivered' => $failedDelivered, 'blocked' => $blocked];
    }

    /**
     * @return array{policy: string, percent: ?int, subdomainPolicy: ?string}
     */
    private static function policy(string $policy, ?int $percent = null, ?string $subdomainPolicy = null): array
    {
        return ['policy' => $policy, 'percent' => $percent, 'subdomainPolicy' => $subdomainPolicy];
    }

    /**
     * @return array{domain: string, sourceIp: string, orgName: ?string, orgCountry: ?string, dkimAuth: string, dkimDomain: string, spfAuth: string, spfDomain: string, messages: int, delivered: int, quarantined: int, rejected: int}
     */
    private static function source(
        string $domain,
        string $ip,
        string $dkimAuth,
        string $dkimDomain,
        int $messages,
        int $delivered = 0,
        int $quarantined = 0,
        int $rejected = 0,
        string $spfAuth = 'fail',
        string $spfDomain = '',
        ?string $orgName = null,
        ?string $orgCountry = null,
    ): array {
        return [
            'domain' => $domain,
            'sourceIp' => $ip,
            'orgName' => $orgName,
            'orgCountry' => $orgCountry,
            'dkimAuth' => $dkimAuth,
            'dkimDomain' => $dkimDomain,
            'spfAuth' => $spfAuth,
            'spfDomain' => '' === $spfDomain ? $domain : $spfDomain,
            'messages' => $messages,
            'delivered' => $delivered,
            'quarantined' => $quarantined,
            'rejected' => $rejected,
        ];
    }

    private static function domain(User $user, string $name, string $id, string $createdAt): Domain
    {
        $domain = new Domain($user, $name);
        $domain->setCreatedAt(new \DateTimeImmutable($createdAt.' UTC'));
        new \ReflectionProperty(Domain::class, 'id')->setValue($domain, Uuid::fromString($id));

        return $domain;
    }
}
