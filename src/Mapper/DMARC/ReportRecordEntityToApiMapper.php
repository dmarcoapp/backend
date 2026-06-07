<?php

declare(strict_types=1);

namespace App\Mapper\DMARC;

use App\DTO\Output\DMARC\IpInfoApi;
use App\DTO\Output\DMARC\ReportRecordApi;
use App\Entity\DMARC\ReportRecord;
use Symfonycasts\MicroMapper\AsMapper;
use Symfonycasts\MicroMapper\MapperInterface;
use Symfonycasts\MicroMapper\MicroMapperInterface;

#[AsMapper(from: ReportRecord::class, to: ReportRecordApi::class)]
final readonly class ReportRecordEntityToApiMapper implements MapperInterface
{
    public function __construct(
        private MicroMapperInterface $microMapper,
    ) {}

    #[\Override]
    public function load(object $from, string $toClass, array $context): ReportRecordApi
    {
        $entity = $from;

        assert($entity instanceof ReportRecord);
        $recordId = $entity->getId();
        if (null === $recordId) {
            throw new \LogicException('Report record ID is missing.');
        }

        return new ReportRecordApi($recordId);
    }

    #[\Override]
    public function populate(object $from, object $to, array $context): object
    {
        $entity = $from;
        $dto = $to;

        assert($entity instanceof ReportRecord);
        assert($dto instanceof ReportRecordApi);

        $report = $entity->getReport();
        $dto->reportId = $report?->getId();
        $dto->sourceIp = $entity->getSourceIp();
        $sourceIpInfo = $entity->getSourceIpInfo();
        $dto->sourceIpInfo = null === $sourceIpInfo ? null : $this->microMapper->map($sourceIpInfo, IpInfoApi::class);
        $dto->count = $entity->getCount();
        $dto->disposition = $entity->getDisposition();
        $dto->dkimAlign = $entity->getDkimAlign();
        $dto->spfAlign = $entity->getSpfAlign();
        $dto->dkimAuth = $entity->getDkimAuth();
        $dto->dkimDomain = $entity->getDkimDomain();
        $dto->dkimSelector = $entity->getDkimSelector();
        $dto->spfAuth = $entity->getSpfAuth();
        $dto->spfDomain = $entity->getSpfDomain();

        return $dto;
    }
}
