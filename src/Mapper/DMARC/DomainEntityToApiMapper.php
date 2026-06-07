<?php

declare(strict_types=1);

namespace App\Mapper\DMARC;

use App\DTO\Output\DMARC\DomainApi;
use App\Entity\DMARC\Domain;
use Symfonycasts\MicroMapper\AsMapper;
use Symfonycasts\MicroMapper\MapperInterface;

#[AsMapper(from: Domain::class, to: DomainApi::class)]
final readonly class DomainEntityToApiMapper implements MapperInterface
{
    #[\Override]
    public function load(object $from, string $toClass, array $context): DomainApi
    {
        $entity = $from;

        assert($entity instanceof Domain);
        $domainId = $entity->getId();
        if (null === $domainId) {
            throw new \LogicException('Domain ID is missing.');
        }

        return new DomainApi($domainId);
    }

    #[\Override]
    public function populate(object $from, object $to, array $context): object
    {
        $entity = $from;
        $dto = $to;

        assert($entity instanceof Domain);
        assert($dto instanceof DomainApi);

        $dto->domain = $entity->getDomain();
        $dto->lastChecked = $entity->getLastChecked();
        $dto->dmarcRecord = $entity->getDmarcRecord();
        $dto->isConfiguredCorrectly = $entity->isConfiguredCorrectly();
        $dto->protectionLevel = $entity->getProtectionLevel();
        $dto->createdAt = $entity->getCreatedAt();

        return $dto;
    }
}
