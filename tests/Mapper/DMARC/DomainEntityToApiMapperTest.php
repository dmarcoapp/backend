<?php

declare(strict_types=1);

namespace App\Tests\Mapper\DMARC;

use App\DTO\Output\DMARC\DomainApi;
use App\Entity\DMARC\Domain;
use App\Entity\User\User;
use App\Enum\DMARC\Domain\ProtectionLevel;
use App\Mapper\DMARC\DomainEntityToApiMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(DomainEntityToApiMapper::class)]
final class DomainEntityToApiMapperTest extends TestCase
{
    public function testLoadAndPopulateMapsDomainFields(): void
    {
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setName('Example User');
        $user->setSharedPostboxIdentifierToken('shared-token');

        $domain = new Domain($user, 'example.com');
        $domainId = Uuid::v4();
        $this->setEntityId($domain, $domainId);

        $lastChecked = new \DateTimeImmutable('2024-01-01 12:00:00');
        $createdAt = new \DateTimeImmutable('2024-01-02 09:30:00');

        $domain
            ->setLastChecked($lastChecked)
            ->setDmarcRecord('v=DMARC1; p=none')
            ->setProtectionLevel(ProtectionLevel::MODERATE)
            ->setCreatedAt($createdAt)
            ->setIsConfiguredCorrectly(true)
        ;

        $mapper = new DomainEntityToApiMapper();
        $dto = $mapper->load($domain, DomainApi::class, []);
        $mapper->populate($domain, $dto, []);

        self::assertSame($domainId, $dto->id);
        self::assertSame('example.com', $dto->domain);
        self::assertSame($lastChecked, $dto->lastChecked);
        self::assertSame('v=DMARC1; p=none', $dto->dmarcRecord);
        self::assertSame(ProtectionLevel::MODERATE, $dto->protectionLevel);
        self::assertSame($createdAt, $dto->createdAt);
        self::assertTrue($dto->isConfiguredCorrectly);
    }

    public function testLoadThrowsWhenDomainIdIsMissing(): void
    {
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setName('Example User');
        $user->setSharedPostboxIdentifierToken('shared-token');

        $mapper = new DomainEntityToApiMapper();

        $this->expectException(\LogicException::class);
        $mapper->load(new Domain($user, 'example.com'), DomainApi::class, []);
    }

    private function setEntityId(object $entity, Uuid $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }
}
