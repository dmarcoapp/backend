<?php

declare(strict_types=1);

namespace App\Repository\DMARC;

use App\DTO\Input\Common\Pagination;
use App\Entity\DMARC\Report;
use App\Entity\DMARC\ReportRecord;
use App\Repository\PaginationAwareTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReportRecord>
 */
class ReportRecordRepository extends ServiceEntityRepository
{
    use PaginationAwareTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReportRecord::class);
    }

    public function getReportRecordsForReportPaginated(Report $report, Pagination $pagination): Paginator
    {
        $queryBuilder = $this->createQueryBuilder('rr')
            ->where('rr.report = :report')
            ->setParameter('report', $report)
        ;

        return $this->paginate($pagination, $queryBuilder);
    }
}
