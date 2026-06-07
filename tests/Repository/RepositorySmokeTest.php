<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Repository\User\AuthLogRepository;
use App\Tests\Api\ApiIntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @internal
 */
#[CoversClass(AuthLogRepository::class)]
final class RepositorySmokeTest extends ApiIntegrationTestCase
{
    public function testRepositoriesResolveFromContainer(): void
    {
        self::assertInstanceOf(AuthLogRepository::class, self::getContainer()->get(AuthLogRepository::class));
    }
}
