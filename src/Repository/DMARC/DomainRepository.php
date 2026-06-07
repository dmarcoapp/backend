<?php

declare(strict_types=1);

namespace App\Repository\DMARC;

use App\DTO\Input\Common\Pagination;
use App\Entity\DMARC\Domain;
use App\Entity\User\User;
use App\Repository\PaginationAwareTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;

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

    public function getDomainsForUserPaginated(User $user, Pagination $pagination): Paginator
    {
        $queryBuilder = $this->createQueryBuilder('d')
            ->where('IDENTITY(d.user) = :userId')
            ->andWhere('d.lastChecked IS NOT NULL')
            ->setParameter('userId', $user->getId(), UuidType::NAME)
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
    public function getUncheckedDomainsForUser(User $user): array
    {
        return $this->createQueryBuilder('d')
            ->where('IDENTITY(d.user) = :userId')
            ->andWhere('d.lastChecked IS NULL')
            ->setParameter('userId', $user->getId(), UuidType::NAME)
            ->orderBy('d.createdAt', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function getUserDomainCount(User $user): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('IDENTITY(d.user) = :userId')
            ->setParameter('userId', $user->getId(), UuidType::NAME)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    public function getNewDomainCount(\DatePeriod $period, User $user): int
    {
        $queryBuilder = $this->createQueryBuilder('d');
        $queryBuilder
            ->select('COUNT(d.domain)')
            ->where('IDENTITY(d.user) = :userId')
            ->andWhere('d.createdAt >= :startDate')
            ->andWhere('d.createdAt <= :endDate')
            ->setParameter('startDate', $period->getStartDate(), Types::DATETIME_IMMUTABLE)
            ->setParameter('endDate', $period->getEndDate(), Types::DATETIME_IMMUTABLE)
            ->setParameter('userId', $user->getId(), UuidType::NAME)
        ;

        try {
            return (int) $queryBuilder->getQuery()->getSingleScalarResult();
        } catch (NoResultException) {
            return 0;
        }
    }

    public function getNewDomains(\DatePeriod $period, User $user): array
    {
        $queryBuilder = $this->createQueryBuilder('d');
        $queryBuilder
            ->select('d.domain')
            ->where('IDENTITY(d.user) = :userId')
            ->andWhere('d.createdAt >= :startDate')
            ->andWhere('d.createdAt <= :endDate')
            ->orderBy('d.createdAt', 'DESC')
            ->setParameter('startDate', $period->getStartDate(), Types::DATETIME_IMMUTABLE)
            ->setParameter('endDate', $period->getEndDate(), Types::DATETIME_IMMUTABLE)
            ->setParameter('userId', $user->getId(), UuidType::NAME)
        ;

        return array_column($queryBuilder->getQuery()->getArrayResult(), 'domain');
    }

    public function save(Domain $domain): void
    {
        $this->getEntityManager()->persist($domain);
        $this->getEntityManager()->flush();
    }

    public function deleteAllForUser(User $user): int
    {
        return $this->createQueryBuilder('d')
            ->delete()
            ->where('IDENTITY(d.user) = :userId')
            ->setParameter('userId', $user->getId(), UuidType::NAME)
            ->getQuery()
            ->execute()
        ;
    }
}
