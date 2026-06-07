<?php

declare(strict_types=1);

namespace App\Repository\DMARC;

use App\DTO\Input\Common\Pagination;
use App\Entity\DMARC\Report;
use App\Entity\DMARC\ReportRecord;
use App\Entity\User\User;
use App\Enum\DMARC\DispositionType;
use App\Repository\PaginationAwareTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NoResultException;
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

    public function getPassRate(\DatePeriod $period, User $user): float
    {
        try {
            $subQueryBuilder = $this->createQueryBuilder('r');
            $subQueryBuilder
                ->select('r.id')
                ->join('r.email', 'em')
                ->where('r.beginDate >= :startDate')
                ->andWhere('r.endDate <= :endDate')
                ->andWhere('IDENTITY(em.owner) = :userId')
                ->andWhere('r.isProcessed = true')
            ;

            $noneDpRecordsQueryBuilder = $this->getEntityManager()->createQueryBuilder();
            $noneDpRecordsQueryBuilder
                ->select('COUNT(rr.id)')
                ->from(ReportRecord::class, 'rr')
                ->where('rr.report IN ('.$subQueryBuilder->getDQL().')')
                ->andWhere('rr.disposition = :disposition')
                ->setParameter('disposition', 'none')
                ->setParameter('startDate', $period->getStartDate())
                ->setParameter('endDate', $period->getEndDate())
                ->setParameter('userId', $user->getId(), UuidType::NAME)
            ;
            $noneDpRecordsCount = (int) $noneDpRecordsQueryBuilder->getQuery()->getSingleScalarResult();

            $allRecordsQueryBuilder = $this->getEntityManager()->createQueryBuilder();
            $allRecordsQueryBuilder
                ->select('COUNT(rr.id)')
                ->from(ReportRecord::class, 'rr')
                ->where('rr.report IN ('.$subQueryBuilder->getDQL().')')
                ->setParameter('startDate', $period->getStartDate())
                ->setParameter('endDate', $period->getEndDate())
                ->setParameter('userId', $user->getId(), UuidType::NAME)
            ;
            $allRecordsCount = (int) $allRecordsQueryBuilder->getQuery()->getSingleScalarResult();

            return $allRecordsCount
                ? $noneDpRecordsCount / $allRecordsCount
                : 0.0;
        } catch (NoResultException) {
            return 0.0;
        }
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

    public function getComplianceAverage(\DatePeriod $period, User $user): array
    {
        $queryBuilder = $this->createQueryBuilder('r');
        $queryBuilder
            ->select(
                'SUM(r.dmarcCompliance)/COUNT(r.id) as dmarc',
                'SUM(r.dkimCompliance)/COUNT(r.id) as dkim',
                'SUM(r.spfCompliance)/COUNT(r.id) as spf'
            )
            ->join('r.email', 'em')
            ->where('r.beginDate >= :startDate')
            ->andWhere('r.endDate <= :endDate')
            ->andWhere('IDENTITY(em.owner) = :userId')
            ->andWhere('r.isProcessed = true')
            ->setParameter('startDate', $period->getStartDate())
            ->setParameter('endDate', $period->getEndDate())
            ->setParameter('userId', $user->getId(), UuidType::NAME)
        ;

        return $queryBuilder->getQuery()->getArrayResult();
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
}
