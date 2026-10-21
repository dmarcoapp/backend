<?php

declare(strict_types=1);

namespace App\Tests\Service\User\DMARC;

use App\DTO\Output\User\WeeklyOverview\DomainSummary;
use App\DTO\Output\User\WeeklyOverview\Finding;
use App\DTO\Output\User\WeeklyOverview\SourceSummary;
use App\DTO\Output\User\WeeklyOverview\TrafficTotals;
use App\DTO\Output\User\WeeklyOverview\WeeklyOverview;
use App\Enum\User\WeeklyFindingType;

/**
 * Weekly overviews for the shapes the email takes.
 */
final class WeeklyOverviewFixture
{
    public static function needingAttention(): WeeklyOverview
    {
        $monitored = self::domain('example.org', 1, 'none', new TrafficTotals(2428, 1241, 1187, 0), needsAttention: true);
        $enforced = self::domain('example.com', 2, 'reject', new TrafficTotals(7293, 6704, 0, 589));
        $new = self::domain('example.net', 3, 'quarantine', new TrafficTotals(928, 549, 0, 379), isNew: true);

        return self::overview(
            traffic: new TrafficTotals(10649, 8494, 1187, 968),
            previousTraffic: new TrafficTotals(9192, 7914, 1019, 256),
            domains: [$monitored, $enforced, $new],
            findings: [
                new Finding(
                    type: WeeklyFindingType::UNALIGNED_SERVICE,
                    domain: $monitored,
                    messages: 956,
                    delivered: 956,
                    sourceCount: 2,
                    // Names come from WHOIS, so the email must escape them.
                    topSource: new SourceSummary('198.51.100.10', 'Mail <Service> Inc', 'United States', 900),
                    signingDomain: 'mailer.example',
                ),
                new Finding(
                    type: WeeklyFindingType::DELIVERED_FAILURES,
                    domain: $monitored,
                    messages: 231,
                    delivered: 231,
                    sourceCount: 1,
                    topSource: new SourceSummary('203.0.113.61', 'Hosting & Co', 'Poland', 231),
                ),
                new Finding(type: WeeklyFindingType::NEW_DOMAIN, domain: $new, messages: 928),
            ],
        );
    }

    public static function healthy(): WeeklyOverview
    {
        $domain = self::domain('example.com', 2, 'reject', new TrafficTotals(7293, 7000, 0, 293));

        return self::overview(
            traffic: $domain->traffic,
            previousTraffic: new TrafficTotals(7100, 6900, 0, 200),
            domains: [$domain],
            findings: [],
        );
    }

    /**
     * Enforced policies, with receivers delivering some failing mail anyway,
     * which is theirs to decide and no reason for alarm.
     */
    public static function healthyWithOverrides(): WeeklyOverview
    {
        $domain = self::domain('example.com', 2, 'reject', new TrafficTotals(7293, 7000, 12, 281));

        return self::overview(
            traffic: $domain->traffic,
            previousTraffic: new TrafficTotals(7100, 6900, 0, 200),
            domains: [$domain],
            findings: [],
            reportingOrganizations: [],
        );
    }

    /**
     * The first week with reports, a single message failing on its own, and
     * the subdomains left open.
     */
    public static function firstWeek(): WeeklyOverview
    {
        $domain = self::domain('example.com', 2, 'reject', new TrafficTotals(41, 40, 1, 0), needsAttention: true, isNew: true, subdomainPolicy: 'none');

        return self::overview(
            traffic: $domain->traffic,
            previousTraffic: new TrafficTotals(),
            domains: [$domain],
            findings: [
                new Finding(
                    type: WeeklyFindingType::DELIVERED_FAILURES,
                    domain: $domain,
                    messages: 1,
                    delivered: 1,
                    sourceCount: 1,
                    topSource: new SourceSummary('203.0.113.9', null, null, 1),
                ),
                new Finding(type: WeeklyFindingType::NEW_DOMAIN, domain: $domain, messages: 41),
            ],
            reportCount: 1,
            reportingOrganizations: ['google.com'],
        );
    }

    /**
     * A service whose mail was all rejected next to forged mail that was
     * delivered, so the delivered failures did not come from the service.
     * Also a domain that went quiet.
     */
    public static function blockedServiceAndQuietDomain(): WeeklyOverview
    {
        $domain = self::domain('example.org', 1, 'quarantine', new TrafficTotals(1000, 800, 50, 150), needsAttention: true, percent: 25);
        $quiet = self::domain('example.net', 3, null, new TrafficTotals());

        return self::overview(
            traffic: $domain->traffic,
            previousTraffic: new TrafficTotals(1500, 1200, 40, 160),
            domains: [$domain],
            findings: [
                new Finding(
                    type: WeeklyFindingType::UNALIGNED_SERVICE,
                    domain: $domain,
                    messages: 100,
                    rejected: 0,
                    quarantined: 100,
                    sourceCount: 1,
                    topSource: new SourceSummary('198.51.100.20', 'Billing', 'Germany', 100),
                    signingDomain: 'billing.example',
                ),
                new Finding(
                    type: WeeklyFindingType::DELIVERED_FAILURES,
                    domain: $domain,
                    messages: 50,
                    delivered: 50,
                    sourceCount: 3,
                    topSource: new SourceSummary('203.0.113.7', 'Hosting', 'Poland', 30),
                ),
                new Finding(type: WeeklyFindingType::QUIET_DOMAIN, domain: $quiet, messages: 500),
            ],
        );
    }

    public static function withoutReports(): WeeklyOverview
    {
        return self::overview(
            traffic: new TrafficTotals(),
            previousTraffic: new TrafficTotals(),
            domains: [],
            findings: [],
            reportCount: 0,
            reportingOrganizations: [],
        );
    }

    private static function domain(
        string $name,
        int $id,
        ?string $policy,
        TrafficTotals $traffic,
        bool $needsAttention = false,
        bool $isNew = false,
        ?int $percent = 100,
        ?string $subdomainPolicy = null,
    ): DomainSummary {
        return new DomainSummary(
            domain: $name,
            domainId: \sprintf('0192e0c4-0000-7000-8000-%012d', $id),
            policy: $policy,
            policyPercent: $percent,
            subdomainPolicy: $subdomainPolicy,
            isNew: $isNew,
            needsAttention: $needsAttention,
            traffic: $traffic,
        );
    }

    /**
     * @param list<DomainSummary> $domains
     * @param list<Finding>       $findings
     * @param list<string>        $reportingOrganizations
     */
    private static function overview(
        TrafficTotals $traffic,
        TrafficTotals $previousTraffic,
        array $domains,
        array $findings,
        int $reportCount = 57,
        array $reportingOrganizations = ['google.com', 'Enterprise Outlook', 'Yahoo'],
    ): WeeklyOverview {
        return new WeeklyOverview(
            periodStart: new \DateTimeImmutable('2026-10-12 00:00:00'),
            periodEnd: new \DateTimeImmutable('2026-10-18 00:00:00'),
            previousPeriodStart: new \DateTimeImmutable('2026-10-05 00:00:00'),
            previousPeriodEnd: new \DateTimeImmutable('2026-10-11 00:00:00'),
            reportCount: $reportCount,
            reportingOrganizations: $reportingOrganizations,
            traffic: $traffic,
            previousTraffic: $previousTraffic,
            domains: $domains,
            findings: $findings,
            reportAddress: 'postbox-token@aggregate-reports.example.test',
        );
    }
}
