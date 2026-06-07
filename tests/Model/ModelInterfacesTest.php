<?php

declare(strict_types=1);

namespace App\Tests\Model;

use App\Entity\EntityInterface;
use App\Entity\FilterableInterface;
use App\Entity\SortableInterface;
use App\Service\IpLookup\IpLookupInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNothing]
final class ModelInterfacesTest extends TestCase
{
    public function testInterfacesExist(): void
    {
        self::assertTrue(interface_exists(EntityInterface::class));
        self::assertTrue(interface_exists(FilterableInterface::class));
        self::assertTrue(interface_exists(SortableInterface::class));
        self::assertTrue(interface_exists(IpLookupInterface::class));
    }
}
