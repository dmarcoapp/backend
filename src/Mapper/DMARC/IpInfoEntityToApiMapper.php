<?php

declare(strict_types=1);

namespace App\Mapper\DMARC;

use App\DTO\Output\DMARC\IpInfoApi;
use App\Entity\DMARC\IpInfo;
use Symfonycasts\MicroMapper\AsMapper;
use Symfonycasts\MicroMapper\MapperInterface;

#[AsMapper(from: IpInfo::class, to: IpInfoApi::class)]
final readonly class IpInfoEntityToApiMapper implements MapperInterface
{
    #[\Override]
    public function load(object $from, string $toClass, array $context): IpInfoApi
    {
        $entity = $from;

        assert($entity instanceof IpInfo);

        return new IpInfoApi();
    }

    #[\Override]
    public function populate(object $from, object $to, array $context): object
    {
        $entity = $from;
        $dto = $to;

        assert($entity instanceof IpInfo);
        assert($dto instanceof IpInfoApi);

        $dto->orgName = $entity->getOrgName();
        $dto->orgCountry = $entity->getOrgCountry();
        $dto->orgAbuseEmail = $entity->getOrgAbuseEmail();
        $dto->orgTechEmail = $entity->getOrgTechEmail();

        return $dto;
    }
}
