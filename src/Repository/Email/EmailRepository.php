<?php

declare(strict_types=1);

namespace App\Repository\Email;

use App\Entity\Email\Email;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Email>
 */
class EmailRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Email::class);
    }

    public function save(Email $email): void
    {
        $this->getEntityManager()->persist($email);
        $this->getEntityManager()->flush();
    }

    public function getJunkEmailIdsToDelete(int $retention = 7): array
    {
        $retention = max(1, $retention);

        $threshold = new \DateTime('-'.$retention.' days');

        $qb = $this->createQueryBuilder('em');
        $qb
            ->select('em.id')
            ->leftJoin('em.report', 'r')
            ->andWhere('em.createdAt < :threshold')
            ->andWhere('r.rawXML IS NULL')
            ->setParameter('threshold', $threshold)
            ->orderBy('em.createdAt', 'ASC')
        ;

        return $qb->getQuery()->getSingleColumnResult();
    }

    public function getEmailIdsWithoutReport(?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('em')
            ->select('em.id')
            ->leftJoin('em.report', 'r')
            ->andWhere('r.id IS NULL')
            ->orderBy('em.createdAt', 'ASC')
        ;

        if (null !== $limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getSingleColumnResult();
    }

    public function deleteByIds(array $ids): void
    {
        $this->createQueryBuilder('em')
            ->delete()
            ->where('em.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->execute()
        ;
    }
}
