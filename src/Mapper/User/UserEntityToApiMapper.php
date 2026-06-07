<?php

declare(strict_types=1);

namespace App\Mapper\User;

use App\DTO\Output\User\UserApi;
use App\Entity\User\User;
use App\Service\Email\AggregateReportPostboxAddressProvider;
use Symfonycasts\MicroMapper\AsMapper;
use Symfonycasts\MicroMapper\MapperInterface;

#[AsMapper(from: User::class, to: UserApi::class)]
final readonly class UserEntityToApiMapper implements MapperInterface
{
    public function __construct(
        private AggregateReportPostboxAddressProvider $aggregateReportPostboxAddressProvider,
    ) {}

    #[\Override]
    public function load(object $from, string $toClass, array $context): UserApi
    {
        $entity = $from;

        assert($entity instanceof User);
        $userId = $entity->getId();
        if (null === $userId) {
            throw new \LogicException('User ID is missing.');
        }

        return new UserApi($userId);
    }

    #[\Override]
    public function populate(object $from, object $to, array $context): object
    {
        $entity = $from;
        $dto = $to;

        assert($entity instanceof User);
        assert($dto instanceof UserApi);

        $dto->email = $entity->getEmail();
        $dto->name = $entity->getName();
        $dto->sharedAggregatePostboxAddress = $this->aggregateReportPostboxAddressProvider->getAddressForUser($entity);

        return $dto;
    }
}
