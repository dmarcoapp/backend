<?php

declare(strict_types=1);

namespace App\Helper;

final readonly class Date
{
    public static function createDatePeriod(int $days, bool $previousPeriod = false): \DatePeriod
    {
        $end = new \DateTimeImmutable(
            $previousPeriod
                ? '-'.$days.'days'
                : 'yesterday'
        );
        $start = $end->sub(new \DateInterval('P'.($days - 1).'D'));
        $interval = new \DateInterval('P1D');

        return new \DatePeriod($start, $interval, $end->add($interval));
    }
}
