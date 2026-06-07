<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User\User;
use App\Service\Email\AggregateReportPostboxAddressProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class AggregateReportPostboxAddressProviderTest extends TestCase
{
    public function testGetAddressForUser(): void
    {
        $user = new User();
        $user->setSharedPostboxIdentifierToken('token123');

        $provider = new AggregateReportPostboxAddressProvider('aggregate.example.test');

        self::assertSame('token123@aggregate.example.test', $provider->getAddressForUser($user));
    }
}
