<?php

declare(strict_types=1);

namespace App\DTO\Input\DMARC;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class DashboardSettings
{
    public function __construct(
        #[Assert\Choice(choices: [7, 14, 30])]
        public int $periodDays = 7,
    ) {}
}
