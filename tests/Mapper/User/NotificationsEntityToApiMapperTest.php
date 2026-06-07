<?php

declare(strict_types=1);

namespace App\Tests\Mapper\User;

use App\DTO\Output\User\NotificationsApi;
use App\Entity\User\User;
use App\Mapper\User\NotificationsEntityToApiMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(NotificationsEntityToApiMapper::class)]
final class NotificationsEntityToApiMapperTest extends TestCase
{
    public function testLoadAndPopulateMapsNotificationFields(): void
    {
        $user = new User();
        $user
            ->setName('Example User')
            ->setEmail('user@example.com')
            ->setSharedPostboxIdentifierToken('shared-token')
            ->setUnusualNewLoginNotificationEnabled(true)
            ->setWeeklyOverviewNotificationEnabled(false)
        ;

        $mapper = new NotificationsEntityToApiMapper();

        $dto = $mapper->load($user, NotificationsApi::class, []);
        $mapped = $mapper->populate($user, $dto, []);

        self::assertSame($dto, $mapped);
        self::assertTrue($dto->unusualNewLoginNotificationEnabled);
        self::assertFalse($dto->weeklyOverviewNotificationEnabled);
    }
}
