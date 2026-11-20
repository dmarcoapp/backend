<?php

declare(strict_types=1);

namespace App\Repository\Intake;

use App\Entity\Intake\ApiIntakeKey;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ApiIntakeKey>
 */
class ApiIntakeKeyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiIntakeKey::class);
    }

    public function save(ApiIntakeKey $key): void
    {
        $this->getEntityManager()->persist($key);
        $this->getEntityManager()->flush();
    }

    public function delete(ApiIntakeKey $key): void
    {
        $this->getEntityManager()->remove($key);
        $this->getEntityManager()->flush();
    }
}
