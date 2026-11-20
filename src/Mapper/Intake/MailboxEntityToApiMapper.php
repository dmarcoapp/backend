<?php

declare(strict_types=1);

namespace App\Mapper\Intake;

use App\DTO\Output\Intake\MailboxApi;
use App\Entity\Intake\Mailbox;
use App\Service\Intake\MailboxCredentials;
use Symfonycasts\MicroMapper\AsMapper;
use Symfonycasts\MicroMapper\MapperInterface;

#[AsMapper(from: Mailbox::class, to: MailboxApi::class)]
final readonly class MailboxEntityToApiMapper implements MapperInterface
{
    public function __construct(
        private MailboxCredentials $credentials,
    ) {}

    #[\Override]
    public function load(object $from, string $toClass, array $context): MailboxApi
    {
        assert($from instanceof Mailbox);

        return new MailboxApi();
    }

    #[\Override]
    public function populate(object $from, object $to, array $context): object
    {
        $entity = $from;
        $dto = $to;

        assert($entity instanceof Mailbox);
        assert($dto instanceof MailboxApi);

        $dto->id = $entity->getId()->toRfc4122();
        $dto->name = $entity->getName();
        $dto->host = $entity->getHost();
        $dto->port = $entity->getPort();
        $dto->security = $entity->getSecurity()->value;
        $dto->username = $entity->getUsername();
        $dto->authMethod = $entity->getAuthMethod()->value;
        $dto->passwordUnreadable = !$this->credentials->isReadable($entity);
        $dto->folder = $entity->getFolder();
        $dto->reportAddress = $entity->getReportAddress();
        $dto->afterImport = $entity->getAfterImport()->value;
        $dto->moveToFolder = $entity->getMoveToFolder();
        $dto->enabled = $entity->isEnabled();
        $dto->pollIntervalMinutes = $entity->getPollIntervalMinutes();
        $dto->firstImportDays = $entity->getFirstImportDays();
        $dto->lastPolledAt = $entity->getLastPolledAt();
        $dto->lastSuccessAt = $entity->getLastSuccessAt();
        $dto->lastError = $entity->getLastError();
        $dto->consecutiveFailures = $entity->getConsecutiveFailures();
        $dto->createdAt = $entity->getCreatedAt();

        return $dto;
    }
}
