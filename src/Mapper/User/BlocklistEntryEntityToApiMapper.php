<?php

declare(strict_types=1);

namespace App\Mapper\User;

use App\DTO\Output\User\BlocklistEntryApi;
use App\Entity\User\BlocklistEntry;
use Symfonycasts\MicroMapper\AsMapper;
use Symfonycasts\MicroMapper\MapperInterface;

#[AsMapper(from: BlocklistEntry::class, to: BlocklistEntryApi::class)]
final readonly class BlocklistEntryEntityToApiMapper implements MapperInterface
{
    #[\Override]
    public function load(object $from, string $toClass, array $context): BlocklistEntryApi
    {
        $entity = $from;

        assert($entity instanceof BlocklistEntry);
        $entryId = $entity->getId();
        if (null === $entryId) {
            throw new \LogicException('Blocklist entry ID is missing.');
        }

        return new BlocklistEntryApi($entryId);
    }

    #[\Override]
    public function populate(object $from, object $to, array $context): object
    {
        $entity = $from;
        $dto = $to;

        assert($entity instanceof BlocklistEntry);
        assert($dto instanceof BlocklistEntryApi);

        $dto->pattern = $entity->getPattern();
        $dto->createdAt = $entity->getCreatedAt();

        return $dto;
    }
}
