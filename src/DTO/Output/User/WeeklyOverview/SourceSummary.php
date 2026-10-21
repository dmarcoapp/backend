<?php

declare(strict_types=1);

namespace App\DTO\Output\User\WeeklyOverview;

final readonly class SourceSummary
{
    public function __construct(
        public string $ip,
        public ?string $orgName,
        public ?string $orgCountry,
        public int $messages,
    ) {}
}
