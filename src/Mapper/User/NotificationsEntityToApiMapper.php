<?php

declare(strict_types=1);

namespace App\Mapper\User;

use App\DTO\Output\User\NotificationsApi;
use App\Entity\User\User;
use Symfonycasts\MicroMapper\AsMapper;
use Symfonycasts\MicroMapper\MapperInterface;

#[AsMapper(from: User::class, to: NotificationsApi::class)]
final readonly class NotificationsEntityToApiMapper implements MapperInterface
{
    #[\Override]
    public function load(object $from, string $toClass, array $context): NotificationsApi
    {
        $entity = $from;

        assert($entity instanceof User);

        return new NotificationsApi();
    }

    #[\Override]
    public function populate(object $from, object $to, array $context): object
    {
        $entity = $from;
        $dto = $to;

        assert($entity instanceof User);
        assert($dto instanceof NotificationsApi);

        $dto->unusualNewLoginNotificationEnabled = $entity->isUnusualNewLoginNotificationEnabled();
        $dto->weeklyOverviewNotificationEnabled = $entity->isWeeklyOverviewNotificationEnabled();

        return $dto;
    }
}
