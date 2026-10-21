<?php

declare(strict_types=1);

namespace App\Service\User\DMARC;

use App\DTO\Output\User\WeeklyOverview\DomainSummary;
use App\DTO\Output\User\WeeklyOverview\Finding;
use App\DTO\Output\User\WeeklyOverview\SourceSummary;
use App\DTO\Output\User\WeeklyOverview\TrafficTotals;
use App\DTO\Output\User\WeeklyOverview\WeeklyOverview;
use App\Entity\DMARC\Domain;
use App\Entity\User\User;
use App\Enum\User\WeeklyFindingType;
use App\Repository\DMARC\DomainRepository;
use App\Repository\DMARC\ReportRepository;
use App\Service\Email\AggregateReportPostboxAddressProvider;
use Psr\Clock\ClockInterface;

/**
 * Builds the weekly overview of the last full Monday-to-Sunday week, compared
 * with the week before it.
 *
 * @psalm-type FailingSource = array{domain: string, sourceIp: string, orgName: ?string, orgCountry: ?string, dkimAuth: string, dkimDomain: string, spfAuth: string, spfDomain: string, messages: int, delivered: int, quarantined: int, rejected: int}
 * @psalm-type TrafficRow = array{domain: string, messages: int, passed: int, failedDelivered: int, blocked: int}
 * @psalm-type Group = array{messages: int, delivered: int, quarantined: int, rejected: int, sources: array<array-key, SourceSummary>}
 */
final readonly class WeeklyOverviewProvider
{
    // One more than the email names, so it knows to say "and others".
    private const int REPORTING_ORGANIZATION_LIMIT = 6;
    // Share of a domain's mail an unaligned signer needs before it is worth
    // a word. Below it, the signer is usually a mailing list or a forwarder
    // that signs the mail again, which the domain owner cannot change.
    private const float SERVICE_MIN_SHARE = 0.01;

    public function __construct(
        private ReportRepository $reportRepository,
        private DomainRepository $domainRepository,
        private AggregateReportPostboxAddressProvider $postboxAddressProvider,
        private ClockInterface $clock,
    ) {}

    public function provide(User $user): WeeklyOverview
    {
        [$week, $previousWeek] = self::lastFullWeeks($this->clock->now());

        // Reports are sent by whoever knows the user's report address, so a
        // domain name that is no hostname never makes it into the email.
        $trafficRows = self::onlyHostnames($this->reportRepository->getTrafficByDomain($week, $user));
        $previousRows = self::onlyHostnames($this->reportRepository->getTrafficByDomain($previousWeek, $user));
        $failingSources = self::onlyHostnames($this->reportRepository->getFailingSources($week, $user));

        $messagesByDomain = [];
        foreach ($trafficRows as $row) {
            $messagesByDomain[$row['domain']] = $row['messages'];
        }
        $services = self::significantServices(self::collectServices($failingSources), $messagesByDomain);

        $knownDomains = $this->knownDomains($user);
        $domains = $this->summarizeDomains($user, $week, $trafficRows, $services, $knownDomains);

        $traffic = new TrafficTotals();
        foreach ($domains as $domain) {
            $traffic = $traffic->add($domain->traffic);
        }

        $previousTraffic = new TrafficTotals();
        foreach ($previousRows as $row) {
            $previousTraffic = $previousTraffic->add(self::trafficFromRow($row));
        }

        $reportingOrganizations = array_values(array_filter(array_map(
            static fn (array $organization): string => (string) $organization['reportingOrganization'],
            $this->reportRepository->getTopReportingOrganizationsInDatePeriod($week, $user, self::REPORTING_ORGANIZATION_LIMIT),
        )));

        return new WeeklyOverview(
            periodStart: \DateTimeImmutable::createFromInterface($week->getStartDate()),
            periodEnd: self::lastDay($week),
            previousPeriodStart: \DateTimeImmutable::createFromInterface($previousWeek->getStartDate()),
            previousPeriodEnd: self::lastDay($previousWeek),
            reportCount: $this->reportRepository->getReportCountInDatePeriod($week, $user),
            reportingOrganizations: $reportingOrganizations,
            traffic: $traffic,
            previousTraffic: $previousTraffic,
            domains: $domains,
            findings: [
                ...self::serviceFindings($domains, $services),
                ...self::deliveredFailureFindings($domains, $failingSources, $services),
                ...self::newDomainFindings($domains),
                ...self::quietDomainFindings($domains, $previousRows, $knownDomains),
            ],
            reportAddress: $this->postboxAddressProvider->getAddressForUser($user),
        );
    }

    /**
     * The last Monday-to-Sunday week that is over, and the one before it.
     * Reports for a day arrive during the next one, which is why the email
     * goes out on Tuesday rather than right after midnight on Monday.
     *
     * @return array{\DatePeriod, \DatePeriod}
     */
    private static function lastFullWeeks(\DateTimeImmutable $now): array
    {
        $end = $now->setTimezone(new \DateTimeZone('UTC'))->modify('monday this week')->setTime(0, 0);
        $start = $end->modify('-7 days');
        $day = new \DateInterval('P1D');

        return [
            new \DatePeriod($start, $day, $end),
            new \DatePeriod($start->modify('-7 days'), $day, $start),
        ];
    }

    /**
     * @param list<TrafficRow>                          $trafficRows
     * @param array<array-key, array<array-key, Group>> $services
     * @param array<array-key, Domain>                  $knownDomains
     *
     * @return list<DomainSummary>
     */
    private function summarizeDomains(User $user, \DatePeriod $week, array $trafficRows, array $services, array $knownDomains): array
    {
        $policies = $this->reportRepository->getLatestPolicyByDomain($week, $user);
        $firstReports = $this->reportRepository->getFirstReportDateByDomain($user);
        $weekStart = $week->getStartDate();

        $domains = [];
        foreach ($trafficRows as $row) {
            $name = $row['domain'];
            $traffic = self::trafficFromRow($row);
            $known = $knownDomains[$name] ?? null;
            $policy = $policies[$name] ?? null;
            $firstReport = $firstReports[$name] ?? null;

            // Failing mail delivered under an enforced policy was the
            // receiver's choice, so only the other domains are to blame.
            $enforced = DomainSummary::enforces($policy['policy'] ?? null, $policy['percent'] ?? null, $policy['subdomainPolicy'] ?? null);

            $domains[] = new DomainSummary(
                domain: $name,
                domainId: $known?->getId()?->toRfc4122(),
                policy: $policy['policy'] ?? null,
                policyPercent: $policy['percent'] ?? null,
                subdomainPolicy: $policy['subdomainPolicy'] ?? null,
                isNew: null !== $firstReport && $firstReport >= $weekStart,
                needsAttention: (!$enforced && $traffic->failedDelivered > 0) || isset($services[$name]),
                traffic: $traffic,
            );
        }

        usort(
            $domains,
            static fn (DomainSummary $a, DomainSummary $b): int => [$b->needsAttention, $b->traffic->messages, $a->domain]
                <=> [$a->needsAttention, $a->traffic->messages, $b->domain],
        );

        return $domains;
    }

    /**
     * The user's domains by lower-cased name. Where reporters spelled a name
     * in several ways, the lower-case one, or else the oldest, is the one the
     * email links to.
     *
     * @return array<array-key, Domain>
     */
    private function knownDomains(User $user): array
    {
        $known = [];
        foreach ($this->domainRepository->findBy(['user' => $user], ['createdAt' => 'ASC']) as $domain) {
            $key = strtolower($domain->getDomain());
            if (!isset($known[$key]) || $domain->getDomain() === $key) {
                $known[$key] = $domain;
            }
        }

        return $known;
    }

    /**
     * Failing mail DKIM-signed by some other domain, which is what a sending
     * service looks like before DKIM is set up for the domain it sends as.
     * Passing SPF alone proves nothing: forgers pass it for their own domain
     * all the time. Grouped by domain and signer, because one service usually
     * sends from many addresses.
     *
     * @param list<FailingSource> $failingSources
     *
     * @return array<array-key, array<array-key, Group>>
     */
    private static function collectServices(array $failingSources): array
    {
        $services = [];
        foreach ($failingSources as $source) {
            $signingDomain = self::signingDomain($source);
            if (null === $signingDomain) {
                continue;
            }

            $services[$source['domain']][$signingDomain] = self::addToGroup(
                $services[$source['domain']][$signingDomain] ?? null,
                $source,
                $source['messages'],
            );
        }

        return $services;
    }

    /**
     * @param array<array-key, array<array-key, Group>> $services
     * @param array<array-key, int>                     $messagesByDomain
     *
     * @return array<array-key, array<array-key, Group>>
     */
    private static function significantServices(array $services, array $messagesByDomain): array
    {
        $significant = [];
        foreach ($services as $domain => $bySigningDomain) {
            $total = $messagesByDomain[$domain] ?? 0;
            foreach ($bySigningDomain as $signingDomain => $service) {
                if ($total > 0 && $service['messages'] >= $total * self::SERVICE_MIN_SHARE) {
                    $significant[$domain][$signingDomain] = $service;
                }
            }
        }

        return $significant;
    }

    /**
     * @param list<DomainSummary>                       $domains
     * @param array<array-key, array<array-key, Group>> $services
     *
     * @return list<Finding>
     */
    private static function serviceFindings(array $domains, array $services): array
    {
        $findings = [];
        foreach ($domains as $domain) {
            // Array keys made of digits turn into integers, hence the cast.
            foreach ($services[$domain->domain] ?? [] as $signingDomain => $service) {
                $findings[] = new Finding(
                    type: WeeklyFindingType::UNALIGNED_SERVICE,
                    domain: $domain,
                    messages: $service['messages'],
                    delivered: $service['delivered'],
                    quarantined: $service['quarantined'],
                    rejected: $service['rejected'],
                    sourceCount: \count($service['sources']),
                    topSource: self::largestSource($service['sources']),
                    signingDomain: (string) $signingDomain,
                );
            }
        }

        usort($findings, static fn (Finding $a, Finding $b): int => $b->messages <=> $a->messages);

        return $findings;
    }

    /**
     * Failing mail that reached receivers because the domain's policy let it,
     * apart from the services named on their own, so every domain flagged
     * for delivering failing mail has a finding that says where it came from.
     *
     * @param list<DomainSummary>                       $domains
     * @param list<FailingSource>                       $failingSources
     * @param array<array-key, array<array-key, Group>> $services
     *
     * @return list<Finding>
     */
    private static function deliveredFailureFindings(array $domains, array $failingSources, array $services): array
    {
        $groups = [];
        foreach ($failingSources as $source) {
            $signingDomain = self::signingDomain($source);
            if (0 === $source['delivered'] || (null !== $signingDomain && isset($services[$source['domain']][$signingDomain]))) {
                continue;
            }

            $groups[$source['domain']] = self::addToGroup($groups[$source['domain']] ?? null, $source, $source['delivered']);
        }

        $findings = [];
        foreach ($domains as $domain) {
            $group = $groups[$domain->domain] ?? null;
            if (null === $group || $domain->isEnforced()) {
                continue;
            }

            $findings[] = new Finding(
                type: WeeklyFindingType::DELIVERED_FAILURES,
                domain: $domain,
                messages: $group['delivered'],
                delivered: $group['delivered'],
                sourceCount: \count($group['sources']),
                topSource: self::largestSource($group['sources']),
            );
        }

        usort($findings, static fn (Finding $a, Finding $b): int => $b->messages <=> $a->messages);

        return $findings;
    }

    /**
     * @param list<DomainSummary> $domains
     *
     * @return list<Finding>
     */
    private static function newDomainFindings(array $domains): array
    {
        $findings = [];
        foreach ($domains as $domain) {
            if ($domain->isNew) {
                $findings[] = new Finding(type: WeeklyFindingType::NEW_DOMAIN, domain: $domain, messages: $domain->traffic->messages);
            }
        }

        return $findings;
    }

    /**
     * Domains that reported the week before and not this one, which is what
     * a changed DMARC record or report address looks like.
     *
     * @param list<DomainSummary>      $domains
     * @param list<TrafficRow>         $previousRows
     * @param array<array-key, Domain> $knownDomains
     *
     * @return list<Finding>
     */
    private static function quietDomainFindings(array $domains, array $previousRows, array $knownDomains): array
    {
        $reporting = [];
        foreach ($domains as $domain) {
            $reporting[$domain->domain] = true;
        }

        $findings = [];
        foreach ($previousRows as $row) {
            if (isset($reporting[$row['domain']])) {
                continue;
            }

            $findings[] = new Finding(
                type: WeeklyFindingType::QUIET_DOMAIN,
                domain: new DomainSummary(
                    domain: $row['domain'],
                    domainId: ($knownDomains[$row['domain']] ?? null)?->getId()?->toRfc4122(),
                    policy: null,
                    policyPercent: null,
                    subdomainPolicy: null,
                    isNew: false,
                    needsAttention: false,
                    traffic: new TrafficTotals(),
                ),
                messages: $row['messages'],
            );
        }

        usort($findings, static fn (Finding $a, Finding $b): int => $b->messages <=> $a->messages);

        return $findings;
    }

    /**
     * The domain that DKIM-signed a failing message, when it is some other
     * organization's. Reports list several signatures as slash-separated
     * results and domains. The domain's own subdomains are left out: those
     * fail only under strict alignment and are no third party.
     *
     * @param array{domain: string, dkimAuth: string, dkimDomain: string, ...} $source
     */
    private static function signingDomain(array $source): ?string
    {
        $domain = strtolower($source['domain']);
        $signers = explode('/', $source['dkimDomain']);
        foreach (explode('/', $source['dkimAuth']) as $index => $result) {
            $signer = strtolower(trim($signers[$index] ?? ''));
            if ('pass' === $result && $signer !== $domain && !str_ends_with($signer, '.'.$domain) && self::isHostname($signer)) {
                return $signer;
            }
        }

        return null;
    }

    private static function isHostname(string $name): bool
    {
        return str_contains($name, '.') && false !== filter_var($name, \FILTER_VALIDATE_DOMAIN, \FILTER_FLAG_HOSTNAME);
    }

    /**
     * @template T of array{domain: string, ...}
     *
     * @param list<T> $rows
     *
     * @return list<T>
     */
    private static function onlyHostnames(array $rows): array
    {
        return array_values(array_filter($rows, static fn (array $row): bool => self::isHostname($row['domain'])));
    }

    /**
     * @param null|Group    $group
     * @param FailingSource $source
     *
     * @return Group
     */
    private static function addToGroup(?array $group, array $source, int $messages): array
    {
        $group ??= ['delivered' => 0, 'messages' => 0, 'quarantined' => 0, 'rejected' => 0, 'sources' => []];
        $group['messages'] += $source['messages'];
        $group['delivered'] += $source['delivered'];
        $group['quarantined'] += $source['quarantined'];
        $group['rejected'] += $source['rejected'];

        $existing = $group['sources'][$source['sourceIp']] ?? null;
        $group['sources'][$source['sourceIp']] = new SourceSummary(
            ip: $source['sourceIp'],
            orgName: $existing?->orgName ?? $source['orgName'],
            orgCountry: $existing?->orgCountry ?? $source['orgCountry'],
            messages: ($existing?->messages ?? 0) + $messages,
        );

        return $group;
    }

    /**
     * @param array<array-key, SourceSummary> $sources
     */
    private static function largestSource(array $sources): ?SourceSummary
    {
        $largest = null;
        foreach ($sources as $source) {
            if (null === $largest || $source->messages > $largest->messages) {
                $largest = $source;
            }
        }

        return $largest;
    }

    /**
     * @param TrafficRow $row
     */
    private static function trafficFromRow(array $row): TrafficTotals
    {
        return new TrafficTotals(
            messages: $row['messages'],
            passed: $row['passed'],
            failedDelivered: $row['failedDelivered'],
            blocked: $row['blocked'],
        );
    }

    private static function lastDay(\DatePeriod $period): \DateTimeImmutable
    {
        $end = $period->getEndDate() ?? $period->getStartDate();

        return \DateTimeImmutable::createFromInterface($end)->sub(new \DateInterval('P1D'));
    }
}
