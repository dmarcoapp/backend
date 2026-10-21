<?php

declare(strict_types=1);

namespace App\Repository\DMARC;

use App\DTO\Input\Common\Pagination;
use App\Entity\DMARC\Report;
use App\Entity\DMARC\ReportRecord;
use App\Entity\User\User;
use App\Enum\DMARC\DispositionType;
use App\Enum\DMARC\DKIMAlign;
use App\Enum\DMARC\SPFAlign;
use App\Enum\DMARC\SPFResult;
use App\Repository\PaginationAwareTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Report>
 */
class ReportRepository extends ServiceEntityRepository
{
    use PaginationAwareTrait;

    // DQL condition of a record that passed DMARC: aligned on DKIM or on SPF.
    // Needs the parameters createRecordsInPeriodQueryBuilder() sets.
    private const string ALIGNED = 'rr.dkimAlign = :dkimPass OR rr.spfAlign = :spfPass';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Report::class);
    }

    public function getReportsForUserPaginated(User $user, Pagination $pagination): Paginator
    {
        $queryBuilder = $this->createQueryBuilder('r')
            ->join('r.email', 'em')
            ->where('IDENTITY(em.owner) = :userId')
            ->andWhere('r.isProcessed = true')
            ->setParameter('userId', $user->getId(), UuidType::NAME)
        ;

        return $this->paginate($pagination, $queryBuilder);
    }

    public function deleteReportsForUserOlderThan(User $user, \DateTimeImmutable $threshold): int
    {
        $subQueryBuilder = $this->createQueryBuilder('r_sub');
        $subQueryBuilder
            ->select('r_sub.id')
            ->join('r_sub.email', 'em_sub')
            ->andWhere('IDENTITY(em_sub.owner) = :userId')
            ->andWhere('em_sub.createdAt < :threshold')
        ;

        $queryBuilder = $this->createQueryBuilder('r');
        $queryBuilder
            ->delete()
            ->andWhere($queryBuilder->expr()->in('r.id', $subQueryBuilder->getDQL()))
            ->setParameter('userId', $user->getId(), UuidType::NAME)
            ->setParameter('threshold', $threshold)
        ;

        return $queryBuilder->getQuery()->execute();
    }

    /**
     * @param list<string> $reportIds
     *
     * @return list<Report>
     */
    public function getReportsByIdsForUser(User $user, array $reportIds): array
    {
        if ([] === $reportIds) {
            return [];
        }

        $reports = [];
        foreach ($reportIds as $reportId) {
            $report = $this->createQueryBuilder('r')
                ->join('r.email', 'em')
                ->andWhere('IDENTITY(em.owner) = :userId')
                ->andWhere('r.id = :reportId')
                ->setParameter('userId', $user->getId(), UuidType::NAME)
                ->setParameter('reportId', Uuid::fromString($reportId), UuidType::NAME)
                ->getQuery()
                ->getOneOrNullResult()
            ;

            if ($report instanceof Report) {
                $reports[] = $report;
            }
        }

        return $reports;
    }

    public function getUnprocessedReportIds(?int $limit = null): array
    {
        $queryBuilder = $this->createQueryBuilder('r')
            ->select('r.id')
            ->andWhere('r.isProcessed = false')
        ;

        if (null !== $limit) {
            $queryBuilder->setMaxResults($limit);
        }

        return $queryBuilder->getQuery()->getSingleColumnResult();
    }

    public function getReportCountInDatePeriod(\DatePeriod $period, User $user): int
    {
        $queryBuilder = $this->createQueryBuilder('r');
        $queryBuilder
            ->select('COUNT(r)')
            ->join('r.email', 'em')
            ->where('r.beginDate >= :startDate')
            ->andWhere('r.endDate <= :endDate')
            ->andWhere('IDENTITY(em.owner) = :userId')
            ->andWhere('r.isProcessed = true')
            ->setParameter('startDate', $period->getStartDate())
            ->setParameter('endDate', $period->getEndDate())
            ->setParameter('userId', $user->getId(), UuidType::NAME)
        ;

        try {
            return (int) $queryBuilder->getQuery()->getSingleScalarResult();
        } catch (NoResultException) {
            return 0;
        }
    }

    public function getSumCountInDatePeriod(\DatePeriod $period, User $user): int
    {
        $queryBuilder = $this->createQueryBuilder('r');
        $queryBuilder
            ->select('SUM(r.sumCount)')
            ->join('r.email', 'em')
            ->where('r.beginDate >= :startDate')
            ->andWhere('r.endDate <= :endDate')
            ->andWhere('IDENTITY(em.owner) = :userId')
            ->andWhere('r.isProcessed = true')
            ->setParameter('startDate', $period->getStartDate())
            ->setParameter('endDate', $period->getEndDate())
            ->setParameter('userId', $user->getId(), UuidType::NAME)
        ;

        try {
            return (int) $queryBuilder->getQuery()->getSingleScalarResult();
        } catch (NoResultException) {
            return 0;
        }
    }

    /**
     * Share of messages that passed DMARC, that is, whose DKIM or SPF result
     * was aligned with the domain. Weighted by message count, so a row that
     * stands for a thousand messages counts a thousand times.
     */
    public function getPassRate(\DatePeriod $period, User $user): float
    {
        $queryBuilder = $this->createRecordsInPeriodQueryBuilder($period, $user)
            ->select(
                'SUM(rr.count) AS messages',
                'SUM(CASE WHEN '.self::ALIGNED.' THEN rr.count ELSE 0 END) AS passed',
            )
        ;

        /** @var array{messages: null|int|string, passed: null|int|string} $result */
        $result = $queryBuilder->getQuery()->getSingleResult();
        $messages = (int) $result['messages'];

        return $messages > 0
            ? (int) $result['passed'] / $messages
            : 0.0;
    }

    /**
     * Message counts per domain: all of them, the ones that passed DMARC, the
     * ones that failed it and were delivered anyway, and the ones a receiver
     * rejected or quarantined. Domains are lower-cased, because reporters do
     * not agree on how to spell them.
     *
     * @return list<array{domain: string, messages: int, passed: int, failedDelivered: int, blocked: int}>
     */
    public function getTrafficByDomain(\DatePeriod $period, User $user): array
    {
        $queryBuilder = $this->createRecordsInPeriodQueryBuilder($period, $user)
            ->select(
                'LOWER(r.domain) AS domain',
                'SUM(rr.count) AS messages',
                'SUM(CASE WHEN '.self::ALIGNED.' THEN rr.count ELSE 0 END) AS passed',
                'SUM(CASE WHEN NOT ('.self::ALIGNED.') AND rr.disposition = :delivered THEN rr.count ELSE 0 END) AS failedDelivered',
                'SUM(CASE WHEN rr.disposition = :quarantine OR rr.disposition = :reject THEN rr.count ELSE 0 END) AS blocked',
            )
            ->andWhere('r.domain IS NOT NULL')
            ->groupBy('domain')
            ->orderBy('messages', 'DESC')
            ->setParameter('delivered', DispositionType::NONE->value)
            ->setParameter('quarantine', DispositionType::QUARANTINE->value)
            ->setParameter('reject', DispositionType::REJECT->value)
        ;

        return array_map(
            static fn (array $row): array => [
                'domain' => (string) $row['domain'],
                'messages' => (int) $row['messages'],
                'passed' => (int) $row['passed'],
                'failedDelivered' => (int) $row['failedDelivered'],
                'blocked' => (int) $row['blocked'],
            ],
            $queryBuilder->getQuery()->getArrayResult(),
        );
    }

    /**
     * The p=, pct= and sp= policy each domain published in its latest report
     * of the period, keyed by the lower-cased domain. A policy the report did
     * not state in a known form is left out.
     *
     * @return array<string, array{policy: string, percent: ?int, subdomainPolicy: ?string}>
     */
    public function getLatestPolicyByDomain(\DatePeriod $period, User $user): array
    {
        $queryBuilder = $this->createQueryBuilder('r')
            ->select('LOWER(r.domain) AS domain', 'r.pPolicy AS policy', 'r.pctPolicy AS percent', 'r.spPolicy AS subdomainPolicy')
            ->join('r.email', 'em')
            ->where('r.beginDate >= :startDate')
            ->andWhere('r.endDate <= :endDate')
            ->andWhere('IDENTITY(em.owner) = :userId')
            ->andWhere('r.isProcessed = true')
            ->andWhere('r.domain IS NOT NULL')
            ->andWhere('r.pPolicy IS NOT NULL')
            ->orderBy('r.endDate', 'DESC')
            // Reports for the same day end together; the one received last wins.
            ->addOrderBy('em.createdAt', 'DESC')
            ->setParameter('startDate', $period->getStartDate())
            ->setParameter('endDate', $period->getEndDate())
            ->setParameter('userId', $user->getId(), UuidType::NAME)
        ;

        $policies = [];
        foreach ($queryBuilder->getQuery()->getArrayResult() as $row) {
            $domain = (string) $row['domain'];
            $policy = $row['policy'];
            $policy = $policy instanceof DispositionType ? $policy : DispositionType::tryFrom((string) $policy);
            if (isset($policies[$domain]) || null === $policy || DispositionType::UNKNOWN === $policy) {
                continue;
            }

            $subdomainPolicy = $row['subdomainPolicy'];
            $subdomainPolicy = $subdomainPolicy instanceof DispositionType ? $subdomainPolicy : DispositionType::tryFrom((string) $subdomainPolicy);

            $policies[$domain] = [
                'policy' => $policy->value,
                'percent' => null === $row['percent'] ? null : (int) $row['percent'],
                'subdomainPolicy' => null === $subdomainPolicy || DispositionType::UNKNOWN === $subdomainPolicy ? null : $subdomainPolicy->value,
            ];
        }

        return $policies;
    }

    /**
     * Messages that failed DMARC, grouped by domain, source and the results
     * they did get, which tell a sending service from a forger.
     *
     * @return list<array{domain: string, sourceIp: string, orgName: ?string, orgCountry: ?string, dkimAuth: string, dkimDomain: string, spfAuth: string, spfDomain: string, messages: int, delivered: int, quarantined: int, rejected: int}>
     */
    public function getFailingSources(\DatePeriod $period, User $user): array
    {
        $queryBuilder = $this->createRecordsInPeriodQueryBuilder($period, $user)
            ->select(
                'LOWER(r.domain) AS domain',
                'rr.sourceIp AS sourceIp',
                'MAX(rr.sourceIpInfo.orgName) AS orgName',
                'MAX(rr.sourceIpInfo.orgCountry) AS orgCountry',
                'rr.dkimAuth AS dkimAuth',
                'rr.dkimDomain AS dkimDomain',
                'rr.spfAuth AS spfAuth',
                'rr.spfDomain AS spfDomain',
                'SUM(rr.count) AS messages',
                'SUM(CASE WHEN rr.disposition = :delivered THEN rr.count ELSE 0 END) AS delivered',
                'SUM(CASE WHEN rr.disposition = :quarantine THEN rr.count ELSE 0 END) AS quarantined',
                'SUM(CASE WHEN rr.disposition = :reject THEN rr.count ELSE 0 END) AS rejected',
            )
            ->andWhere('r.domain IS NOT NULL')
            ->andWhere('NOT ('.self::ALIGNED.')')
            ->groupBy('domain', 'rr.sourceIp', 'rr.dkimAuth', 'rr.dkimDomain', 'rr.spfAuth', 'rr.spfDomain')
            ->orderBy('messages', 'DESC')
            ->setParameter('delivered', DispositionType::NONE->value)
            ->setParameter('quarantine', DispositionType::QUARANTINE->value)
            ->setParameter('reject', DispositionType::REJECT->value)
        ;

        return array_map(
            static function (array $row): array {
                $spfAuth = $row['spfAuth'];

                return [
                    'domain' => (string) $row['domain'],
                    'sourceIp' => (string) $row['sourceIp'],
                    'orgName' => null === $row['orgName'] || '' === $row['orgName'] ? null : (string) $row['orgName'],
                    'orgCountry' => null === $row['orgCountry'] || '' === $row['orgCountry'] ? null : (string) $row['orgCountry'],
                    'dkimAuth' => (string) $row['dkimAuth'],
                    'dkimDomain' => (string) $row['dkimDomain'],
                    'spfAuth' => $spfAuth instanceof SPFResult ? $spfAuth->value : (string) $spfAuth,
                    'spfDomain' => (string) $row['spfDomain'],
                    'messages' => (int) $row['messages'],
                    'delivered' => (int) $row['delivered'],
                    'quarantined' => (int) $row['quarantined'],
                    'rejected' => (int) $row['rejected'],
                ];
            },
            $queryBuilder->getQuery()->getArrayResult(),
        );
    }

    /**
     * When each of the user's domains was first reported on, by the start of
     * its earliest report still kept, keyed by the lower-cased domain.
     *
     * @return array<string, \DateTimeImmutable>
     */
    public function getFirstReportDateByDomain(User $user): array
    {
        $queryBuilder = $this->createQueryBuilder('r')
            ->select('LOWER(r.domain) AS domain', 'MIN(r.beginDate) AS firstReport')
            ->join('r.email', 'em')
            ->where('IDENTITY(em.owner) = :userId')
            ->andWhere('r.isProcessed = true')
            ->andWhere('r.domain IS NOT NULL')
            ->groupBy('domain')
            ->setParameter('userId', $user->getId(), UuidType::NAME)
        ;

        $dates = [];
        foreach ($queryBuilder->getQuery()->getArrayResult() as $row) {
            if (null !== $row['firstReport']) {
                $dates[(string) $row['domain']] = new \DateTimeImmutable((string) $row['firstReport'], new \DateTimeZone('UTC'));
            }
        }

        return $dates;
    }

    public function getThreatCount(\DatePeriod $period, User $user): int
    {
        $subQueryBuilder = $this->createQueryBuilder('r');
        $subQueryBuilder
            ->select('r.id')
            ->join('r.email', 'em')
            ->where('r.beginDate >= :startDate')
            ->andWhere('r.endDate <= :endDate')
            ->andWhere('IDENTITY(em.owner) = :userId')
            ->andWhere('r.isProcessed = true')
        ;

        $someDpRecordsQueryBuilder = $this->getEntityManager()->createQueryBuilder();
        $someDpRecordsQueryBuilder
            ->select('SUM(rr.count)')
            ->from(ReportRecord::class, 'rr')
            ->where($someDpRecordsQueryBuilder->expr()->in('rr.report', $subQueryBuilder->getDQL()))
            ->andWhere('rr.disposition != :disposition')
            ->setParameter('disposition', DispositionType::NONE->value)
            ->setParameter('startDate', $period->getStartDate())
            ->setParameter('endDate', $period->getEndDate())
            ->setParameter('userId', $user->getId(), UuidType::NAME)
        ;

        try {
            return (int) $someDpRecordsQueryBuilder->getQuery()->getSingleScalarResult();
        } catch (NoResultException) {
            return 0;
        }
    }

    public function getMessageCountByDate(\DatePeriod $period, User $user): array
    {
        $results = [];
        foreach ($period as $date) {
            $key = $date->format('Y-m-d');
            $results[$key] = 0;
        }

        $qb = $this->createQueryBuilder('r');

        $qb->select('r.beginDate AS date', 'r.sumCount AS cnt')
            ->join('r.email', 'em')
            ->where('IDENTITY(em.owner) = :userId')
            ->andWhere('r.beginDate >= :start')
            ->andWhere('r.endDate <= :end')
            ->setParameter('userId', $user->getId(), UuidType::NAME)
            ->setParameter('start', $period->getStartDate()->format('Y-m-d 00:00:00'))
            ->setParameter('end', $period->getEndDate()->format('Y-m-d 23:59:59'))
        ;

        $dbResults = $qb->getQuery()->getArrayResult();

        foreach ($dbResults as $row) {
            $key = $row['date']->format('Y-m-d');
            if (isset($results[$key])) {
                $results[$key] += (int) $row['cnt'];
            }
        }

        return $results;
    }

    public function getThreatCountByDate(\DatePeriod $period, User $user): array
    {
        $results = [];
        foreach ($period as $date) {
            $key = $date->format('Y-m-d');
            $results[$key] = 0;
        }

        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('r.beginDate AS date', 'rr.count AS cnt')
            ->from(ReportRecord::class, 'rr')
            ->join('rr.report', 'r')
            ->join('r.email', 'em')
            ->andWhere('IDENTITY(em.owner) = :userId')
            ->andWhere('r.beginDate >= :start')
            ->andWhere('r.endDate <= :end')
            ->andWhere('rr.disposition != :dispositions')
            ->setParameter('userId', $user->getId(), UuidType::NAME)
            ->setParameter('start', $period->getStartDate()->format('Y-m-d 00:00:00'))
            ->setParameter('end', $period->getEndDate()->format('Y-m-d 23:59:59'))
            ->setParameter('dispositions', DispositionType::NONE->value)
        ;

        $dbResults = $qb->getQuery()->getArrayResult();

        foreach ($dbResults as $row) {
            $key = $row['date']->format('Y-m-d');
            if (isset($results[$key])) {
                $results[$key] += (int) $row['cnt'];
            }
        }

        return $results;
    }

    /**
     * Share of messages aligned on DKIM, on SPF, and on either (DMARC pass),
     * between 0 and 1, weighted by message count like getPassRate(), so the
     * dashboard's DMARC gauge and pass rate agree.
     *
     * @return list<array{dmarc: float, dkim: float, spf: float}>
     */
    public function getComplianceAverage(\DatePeriod $period, User $user): array
    {
        $queryBuilder = $this->createRecordsInPeriodQueryBuilder($period, $user)
            ->select(
                'SUM(rr.count) AS messages',
                'SUM(CASE WHEN '.self::ALIGNED.' THEN rr.count ELSE 0 END) AS dmarc',
                'SUM(CASE WHEN rr.dkimAlign = :dkimPass THEN rr.count ELSE 0 END) AS dkim',
                'SUM(CASE WHEN rr.spfAlign = :spfPass THEN rr.count ELSE 0 END) AS spf',
            )
        ;

        /** @var array{messages: null|int|string, dmarc: null|int|string, dkim: null|int|string, spf: null|int|string} $result */
        $result = $queryBuilder->getQuery()->getSingleResult();
        $messages = (int) $result['messages'];
        $share = static fn (int|string|null $count): float => $messages > 0 ? (int) $count / $messages : 0.0;

        return [[
            'dmarc' => $share($result['dmarc']),
            'dkim' => $share($result['dkim']),
            'spf' => $share($result['spf']),
        ]];
    }

    public function getOffendersInDatePeriod(\DatePeriod $period, User $user, int $limit = 5): array
    {
        $affectedReportsQueryBuilder = $this->createQueryBuilder('r');
        $affectedReportsQueryBuilder
            ->select('r.id')
            ->join('r.email', 'em')
            ->where('r.beginDate >= :startDate')
            ->andWhere('r.endDate <= :endDate')
            ->andWhere('IDENTITY(em.owner) = :userId')
            ->andWhere('r.isProcessed = true')
        ;

        $topOffendingIpsQueryBuilder = $this->getEntityManager()->createQueryBuilder();
        $topOffendingIpsQueryBuilder
            ->select(
                'rr.sourceIp AS sourceIp',
                'SUM(rr.count) AS count',
                'MAX(rr.sourceIpInfo.orgAbuseEmail) AS orgAbuseEmail',
                'MAX(rr.sourceIpInfo.orgCountry) AS orgCountry',
                'MAX(rr.sourceIpInfo.orgName) AS orgName',
                'MAX(rr.sourceIpInfo.orgTechEmail) AS orgTechEmail',
            )
            ->from(ReportRecord::class, 'rr')
            ->where($topOffendingIpsQueryBuilder->expr()->in('rr.report', $affectedReportsQueryBuilder->getDQL()))
            ->andWhere('rr.disposition != :disposition')
            ->groupBy('rr.sourceIp')
            ->orderBy('count', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('disposition', DispositionType::NONE->value)
            ->setParameter('startDate', $period->getStartDate())
            ->setParameter('endDate', $period->getEndDate())
            ->setParameter('userId', $user->getId(), UuidType::NAME)
        ;

        return array_map(
            static fn (array $offender): array => [
                'count' => (int) ($offender['count'] ?? 0),
                'sourceIp' => $offender['sourceIp'] ?? null,
                'sourceIpInfo' => [
                    'orgAbuseEmail' => $offender['orgAbuseEmail'] ?? null,
                    'orgCountry' => $offender['orgCountry'] ?? null,
                    'orgName' => $offender['orgName'] ?? null,
                    'orgTechEmail' => $offender['orgTechEmail'] ?? null,
                ],
            ],
            $topOffendingIpsQueryBuilder->getQuery()->getArrayResult(),
        );
    }

    public function getTopSendersInDatePeriod(\DatePeriod $period, User $user, int $limit = 5): array
    {
        $affectedReportsQueryBuilder = $this->createQueryBuilder('r');
        $affectedReportsQueryBuilder
            ->select('r.id')
            ->join('r.email', 'em')
            ->where('r.beginDate >= :startDate')
            ->andWhere('r.endDate <= :endDate')
            ->andWhere('IDENTITY(em.owner) = :userId')
            ->andWhere('r.isProcessed = true')
        ;

        $topSendingIpsQueryBuilder = $this->getEntityManager()->createQueryBuilder();
        $topSendingIpsQueryBuilder
            ->select(
                'rr.sourceIp AS sourceIp',
                'SUM(rr.count) AS count',
                'MAX(rr.sourceIpInfo.orgAbuseEmail) AS orgAbuseEmail',
                'MAX(rr.sourceIpInfo.orgCountry) AS orgCountry',
                'MAX(rr.sourceIpInfo.orgName) AS orgName',
                'MAX(rr.sourceIpInfo.orgTechEmail) AS orgTechEmail',
            )
            ->from(ReportRecord::class, 'rr')
            ->where($topSendingIpsQueryBuilder->expr()->in('rr.report', $affectedReportsQueryBuilder->getDQL()))
            ->groupBy('rr.sourceIp')
            ->orderBy('count', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('startDate', $period->getStartDate())
            ->setParameter('endDate', $period->getEndDate())
            ->setParameter('userId', $user->getId(), UuidType::NAME)
        ;

        return array_map(
            static fn (array $sender): array => [
                'count' => (int) ($sender['count'] ?? 0),
                'sourceIp' => $sender['sourceIp'] ?? null,
                'sourceIpInfo' => [
                    'orgAbuseEmail' => $sender['orgAbuseEmail'] ?? null,
                    'orgCountry' => $sender['orgCountry'] ?? null,
                    'orgName' => $sender['orgName'] ?? null,
                    'orgTechEmail' => $sender['orgTechEmail'] ?? null,
                ],
            ],
            $topSendingIpsQueryBuilder->getQuery()->getArrayResult(),
        );
    }

    public function getSourceCountryDistributionInDatePeriod(\DatePeriod $period, User $user, int $limit = 10): array
    {
        $affectedReportsQueryBuilder = $this->createQueryBuilder('r');
        $affectedReportsQueryBuilder
            ->select('r.id')
            ->join('r.email', 'em')
            ->where('r.beginDate >= :startDate')
            ->andWhere('r.endDate <= :endDate')
            ->andWhere('IDENTITY(em.owner) = :userId')
            ->andWhere('r.isProcessed = true')
        ;

        $sourceCountryDistributionQueryBuilder = $this->getEntityManager()->createQueryBuilder();
        $sourceCountryDistributionQueryBuilder
            ->select(
                'rr.sourceIpInfo.orgCountry AS country',
                'SUM(rr.count) AS count',
            )
            ->from(ReportRecord::class, 'rr')
            ->where($sourceCountryDistributionQueryBuilder->expr()->in('rr.report', $affectedReportsQueryBuilder->getDQL()))
            ->andWhere('rr.sourceIpInfo.orgCountry IS NOT NULL')
            ->andWhere('rr.sourceIpInfo.orgCountry != :emptyCountry')
            ->groupBy('rr.sourceIpInfo.orgCountry')
            ->orderBy('count', 'DESC')
            ->addOrderBy('country', 'ASC')
            ->setMaxResults($limit)
            ->setParameter('emptyCountry', '')
            ->setParameter('startDate', $period->getStartDate())
            ->setParameter('endDate', $period->getEndDate())
            ->setParameter('userId', $user->getId(), UuidType::NAME)
        ;

        return array_map(
            static fn (array $country): array => [
                'count' => (int) ($country['count'] ?? 0),
                'country' => $country['country'] ?? null,
            ],
            $sourceCountryDistributionQueryBuilder->getQuery()->getArrayResult(),
        );
    }

    public function getTopReportingOrganizationsInDatePeriod(\DatePeriod $period, User $user, int $limit = 5): array
    {
        $queryBuilder = $this->createQueryBuilder('r');
        $queryBuilder
            ->select(
                'r.reportingOrganization AS reportingOrganization',
                'COUNT(r.id) AS count',
            )
            ->join('r.email', 'em')
            ->andWhere('r.beginDate >= :startDate')
            ->andWhere('r.endDate <= :endDate')
            ->andWhere('IDENTITY(em.owner) = :userId')
            ->andWhere('r.isProcessed = true')
            ->andWhere('r.reportingOrganization IS NOT NULL')
            ->groupBy('r.reportingOrganization')
            ->orderBy('count', 'DESC')
            ->addOrderBy('reportingOrganization', 'ASC')
            ->setMaxResults($limit)
            ->setParameter('startDate', $period->getStartDate())
            ->setParameter('endDate', $period->getEndDate())
            ->setParameter('userId', $user->getId(), UuidType::NAME)
        ;

        return array_map(
            static fn (array $organization): array => [
                'count' => (int) ($organization['count'] ?? 0),
                'reportingOrganization' => $organization['reportingOrganization'] ?? null,
            ],
            $queryBuilder->getQuery()->getArrayResult(),
        );
    }

    public function save(Report $report): void
    {
        $this->getEntityManager()->persist($report);
        $this->getEntityManager()->flush();
    }

    public function delete(Report $report): void
    {
        $this->getEntityManager()->remove($report);
        $this->getEntityManager()->flush();
    }

    /**
     * @param list<Report> $reports
     */
    public function deleteMany(array $reports): void
    {
        foreach ($reports as $report) {
            assert($report instanceof Report);
            $this->getEntityManager()->remove($report);
        }

        $this->getEntityManager()->flush();
    }

    /**
     * Report records of the user's processed reports in the period, aliased
     * rr, with r for the report. Sets the parameters ALIGNED needs.
     */
    private function createRecordsInPeriodQueryBuilder(\DatePeriod $period, User $user): QueryBuilder
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->from(ReportRecord::class, 'rr')
            ->join('rr.report', 'r')
            ->join('r.email', 'em')
            ->where('r.beginDate >= :startDate')
            ->andWhere('r.endDate <= :endDate')
            ->andWhere('IDENTITY(em.owner) = :userId')
            ->andWhere('r.isProcessed = true')
            ->setParameter('dkimPass', DKIMAlign::PASS->value)
            ->setParameter('spfPass', SPFAlign::PASS->value)
            ->setParameter('startDate', $period->getStartDate())
            ->setParameter('endDate', $period->getEndDate())
            ->setParameter('userId', $user->getId(), UuidType::NAME)
        ;
    }
}
