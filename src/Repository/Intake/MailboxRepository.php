<?php

declare(strict_types=1);

namespace App\Repository\Intake;

use App\Entity\Intake\Mailbox;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Mailbox>
 */
class MailboxRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Mailbox::class);
    }

    /**
     * @return list<Mailbox>
     */
    public function findAllByName(): array
    {
        return $this->findBy([], ['name' => 'ASC', 'createdAt' => 'ASC']);
    }

    /**
     * @return list<Mailbox>
     */
    public function findEnabled(): array
    {
        return $this->findBy(['enabled' => true], ['createdAt' => 'ASC']);
    }

    public function save(Mailbox $mailbox): void
    {
        $this->getEntityManager()->persist($mailbox);
        $this->getEntityManager()->flush();
    }

    public function delete(Mailbox $mailbox): void
    {
        $this->getEntityManager()->remove($mailbox);
        $this->getEntityManager()->flush();
    }
}
