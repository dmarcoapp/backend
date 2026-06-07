<?php

declare(strict_types=1);

namespace App\Tests;

use App\Kernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * @internal
 */
#[CoversClass(Kernel::class)]
final class KernelTest extends TestCase
{
    public function testKernelIsSymfonyKernel(): void
    {
        $kernel = new Kernel('test', true);

        self::assertInstanceOf(BaseKernel::class, $kernel);
    }
}
