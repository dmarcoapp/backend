<?php

declare(strict_types=1);

namespace App\Repository\Intake;

use App\Entity\Intake\Mailbox;
use App\Entity\Intake\MailboxLogEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MailboxLogEntry>
 */
class MailboxLogEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MailboxLogEntry::class);
    }

    /**
     * @return list<MailboxLogEntry>
     */
    public function findLatest(Mailbox $mailbox, int $limit = 50): array
    {
        return $this->findBy(['mailbox' => $mailbox], ['createdAt' => 'DESC'], $limit);
    }

    public function deleteOlderThan(\DateTimeImmutable $threshold): int
    {
        return (int) $this->createQueryBuilder('l')
            ->delete()
            ->where('l.createdAt < :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->execute()
        ;
    }
}
