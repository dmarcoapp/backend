<?php

declare(strict_types=1);

namespace App\Helper;

final readonly class Trend
{
    public static function calculate(?float $current, ?float $previous): float
    {
        if (null === $current || null === $previous) {
            return 0;
        }

        if (0.0 == $previous) {
            return 0;
        }

        return (($current - $previous) / $previous) * 100;
    }
}
