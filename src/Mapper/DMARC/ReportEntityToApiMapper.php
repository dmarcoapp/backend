<?php

declare(strict_types=1);

namespace App\Mapper\DMARC;

use App\DTO\Output\DMARC\ReportApi;
use App\Entity\DMARC\Report;
use App\Repository\DMARC\DomainRepository;
use Symfonycasts\MicroMapper\AsMapper;
use Symfonycasts\MicroMapper\MapperInterface;

#[AsMapper(from: Report::class, to: ReportApi::class)]
final readonly class ReportEntityToApiMapper implements MapperInterface
{
    public function __construct(
        private DomainRepository $domainRepository,
    ) {}

    #[\Override]
    public function load(object $from, string $toClass, array $context): ReportApi
    {
        $entity = $from;

        assert($entity instanceof Report);
        $reportId = $entity->getId();
        if (null === $reportId) {
            throw new \LogicException('Report ID is missing.');
        }

        return new ReportApi($reportId);
    }

    #[\Override]
    public function populate(object $from, object $to, array $context): object
    {
        $entity = $from;
        $dto = $to;

        assert($entity instanceof Report);
        assert($dto instanceof ReportApi);

        $email = $entity->getEmail();
        $owner = $email?->getOwner();
        $domainEntity = null;
        if (null !== $owner) {
            $domainEntity = $this->domainRepository->findOneBy([
                'domain' => $entity->getDomain(),
                'user' => $owner,
            ]);
        }

        $dto->receivedAt = $email?->getCreatedAt();
        $dto->fromAddress = $email?->getFromAddress();
        $dto->reportingOrganization = $entity->getReportingOrganization();
        $dto->reportingOrganizationEmail = $entity->getReportingOrganizationEmail();
        $dto->reportingOrganizationExtraContact = $entity->getReportingOrganizationExtraContact();
        $dto->reportId = $entity->getReportId();
        $dto->isVerified = $entity->isVerified();
        $dto->beginDate = $entity->getBeginDate();
        $dto->endDate = $entity->getEndDate();
        $dto->domain = $entity->getDomain();
        $dto->domainId = $domainEntity?->getId()?->toRfc4122();
        $dto->adkimPolicy = $entity->getAdkimPolicy();
        $dto->aspfPolicy = $entity->getAspfPolicy();
        $dto->pPolicy = $entity->getPPolicy();
        $dto->spPolicy = $entity->getSpPolicy();
        $dto->pctPolicy = $entity->getPctPolicy();
        $dto->npPolicy = $entity->getNpPolicy();
        $dto->sumCount = $entity->getSumCount();
        $dto->dmarcCompliance = $entity->getDmarcCompliance();
        $dto->spfCompliance = $entity->getSpfCompliance();
        $dto->dkimCompliance = $entity->getDkimCompliance();

        return $dto;
    }
}
