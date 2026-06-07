<?php

declare(strict_types=1);

namespace App\Helper;

final readonly class Int32
{
    public static function toSigned(int $value): int
    {
        if ($value <= 0x7FFFFFFF) {
            return $value;
        }

        return $value - 0x100000000;
    }
}
