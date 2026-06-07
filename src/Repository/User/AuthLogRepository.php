<?php

declare(strict_types=1);

namespace App\Repository\User;

use App\Entity\User\AuthLog;
use App\Enum\Auth\AuthLogAction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<AuthLog>
 */
class AuthLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuthLog::class);
    }

    public function countLoginSuccesses(Uuid $userId): int
    {
        return (int) $this->createQueryBuilder('log')
            ->select('COUNT(log.id)')
            ->where('log.userId = :userId')
            ->andWhere('log.action = :action')
            ->setParameter('userId', $userId, UuidType::NAME)
            ->setParameter('action', AuthLogAction::LOGIN_SUCCESS)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    public function hasLoginSuccessFromCountry(Uuid $userId, string $countryCode): bool
    {
        $result = $this->createQueryBuilder('log')
            ->select('log.id')
            ->where('log.userId = :userId')
            ->andWhere('log.action = :action')
            ->andWhere('log.countryCode = :countryCode')
            ->setParameter('userId', $userId, UuidType::NAME)
            ->setParameter('action', AuthLogAction::LOGIN_SUCCESS)
            ->setParameter('countryCode', $countryCode)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return null !== $result;
    }

    public function deleteOlderThan(\DateTimeImmutable $threshold): int
    {
        return $this->createQueryBuilder('log')
            ->delete()
            ->where('log.createdAt < :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->execute()
        ;
    }
}
