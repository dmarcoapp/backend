<?php

declare(strict_types=1);

namespace App\Repository\User;

use App\DTO\Input\Common\Pagination;
use App\Entity\User\BlocklistEntry;
use App\Repository\PaginationAwareTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<BlocklistEntry>
 */
class BlocklistEntryRepository extends ServiceEntityRepository
{
    use PaginationAwareTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlocklistEntry::class);
    }

    public function getBlocklistEntriesPaginated(Pagination $pagination): Paginator
    {
        return $this->paginate($pagination, $this->createQueryBuilder('b'));
    }

    /**
     * @return BlocklistEntry[]
     */
    public function getBlocklistEntries(): array
    {
        return $this->createQueryBuilder('b')
            ->orderBy('b.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function save(BlocklistEntry $entry): void
    {
        $this->getEntityManager()->persist($entry);
        $this->getEntityManager()->flush();
    }

    public function delete(BlocklistEntry $entry): void
    {
        $this->getEntityManager()->remove($entry);
        $this->getEntityManager()->flush();
    }

    /**
     * @param list<string> $entryIds
     *
     * @return list<BlocklistEntry>
     */
    public function getEntriesByIds(array $entryIds): array
    {
        if ([] === $entryIds) {
            return [];
        }

        $entries = [];
        foreach ($entryIds as $entryId) {
            $entry = $this->createQueryBuilder('b')
                ->andWhere('b.id = :entryId')
                ->setParameter('entryId', Uuid::fromString($entryId), UuidType::NAME)
                ->getQuery()
                ->getOneOrNullResult()
            ;

            if ($entry instanceof BlocklistEntry) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * @param list<BlocklistEntry> $entries
     */
    public function deleteMany(array $entries): void
    {
        foreach ($entries as $entry) {
            assert($entry instanceof BlocklistEntry);
            $this->getEntityManager()->remove($entry);
        }

        $this->getEntityManager()->flush();
    }
}
