<?php

declare(strict_types=1);

namespace App\Helper;

final readonly class Date
{
    public static function createDatePeriod(int $days, bool $previousPeriod = false): \DatePeriod
    {
        // Both periods are whole days ending at midnight. The previous one ends
        // the day before the current one starts, so the two never overlap and
        // a trend compares the same number of days.
        $end = new \DateTimeImmutable('yesterday');
        if ($previousPeriod) {
            $end = $end->sub(new \DateInterval('P'.$days.'D'));
        }
        $start = $end->sub(new \DateInterval('P'.($days - 1).'D'));
        $interval = new \DateInterval('P1D');

        return new \DatePeriod($start, $interval, $end->add($interval));
    }
}
