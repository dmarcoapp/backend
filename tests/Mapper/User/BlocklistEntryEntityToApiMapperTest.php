<?php

declare(strict_types=1);

namespace App\Tests\Mapper\User;

use App\DTO\Output\User\BlocklistEntryApi;
use App\Entity\User\BlocklistEntry;
use App\Mapper\User\BlocklistEntryEntityToApiMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(BlocklistEntryEntityToApiMapper::class)]
final class BlocklistEntryEntityToApiMapperTest extends TestCase
{
    public function testLoadAndPopulateMapsEntryFields(): void
    {
        $entry = new BlocklistEntry('example.com');
        $entryId = Uuid::v4();
        $this->setEntityId($entry, $entryId);

        $mapper = new BlocklistEntryEntityToApiMapper();
        $dto = $mapper->load($entry, BlocklistEntryApi::class, []);
        $mapper->populate($entry, $dto, []);

        self::assertSame($entryId, $dto->id);
        self::assertSame('example.com', $dto->pattern);
        self::assertInstanceOf(\DateTimeImmutable::class, $dto->createdAt);
    }

    public function testLoadThrowsWhenEntryIdIsMissing(): void
    {
        $mapper = new BlocklistEntryEntityToApiMapper();

        $this->expectException(\LogicException::class);
        $mapper->load(new BlocklistEntry('example.com'), BlocklistEntryApi::class, []);
    }

    private function setEntityId(object $entity, Uuid $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }
}
