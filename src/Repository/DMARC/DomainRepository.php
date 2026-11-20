<?php

declare(strict_types=1);

namespace App\Repository\DMARC;

use App\DTO\Input\Common\Pagination;
use App\Entity\DMARC\Domain;
use App\Repository\PaginationAwareTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Domain>
 */
class DomainRepository extends ServiceEntityRepository
{
    use PaginationAwareTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Domain::class);
    }

    public function getDomainsPaginated(Pagination $pagination): Paginator
    {
        $queryBuilder = $this->createQueryBuilder('d')
            ->where('d.lastChecked IS NOT NULL')
        ;

        return $this->paginate($pagination, $queryBuilder);
    }

    /**
     * @return Domain[]
     */
    public function getDomainsForRegularProcessing(): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.lastChecked < :threshold')
            ->andWhere('d.lastChecked IS NOT NULL')
            ->setParameter('threshold', new \DateTime('-1 day'))
            ->orderBy('d.lastChecked', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return Domain[]
     */
    public function getUncheckedDomains(): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.lastChecked IS NULL')
            ->orderBy('d.createdAt', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function getNewDomainCount(\DatePeriod $period): int
    {
        $queryBuilder = $this->createQueryBuilder('d');
        $queryBuilder
            ->select('COUNT(d.domain)')
            ->where('d.createdAt >= :startDate')
            ->andWhere('d.createdAt <= :endDate')
            ->setParameter('startDate', $period->getStartDate(), Types::DATETIME_IMMUTABLE)
            ->setParameter('endDate', $period->getEndDate(), Types::DATETIME_IMMUTABLE)
        ;

        try {
            return (int) $queryBuilder->getQuery()->getSingleScalarResult();
        } catch (NoResultException) {
            return 0;
        }
    }

    public function getNewDomains(\DatePeriod $period): array
    {
        $queryBuilder = $this->createQueryBuilder('d');
        $queryBuilder
            ->select('d.domain')
            ->where('d.createdAt >= :startDate')
            ->andWhere('d.createdAt <= :endDate')
            ->orderBy('d.createdAt', 'DESC')
            ->setParameter('startDate', $period->getStartDate(), Types::DATETIME_IMMUTABLE)
            ->setParameter('endDate', $period->getEndDate(), Types::DATETIME_IMMUTABLE)
        ;

        return array_column($queryBuilder->getQuery()->getArrayResult(), 'domain');
    }

    public function save(Domain $domain): void
    {
        $this->getEntityManager()->persist($domain);
        $this->getEntityManager()->flush();
    }
}
