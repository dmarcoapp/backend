<?php

declare(strict_types=1);

namespace App\DTO\Output\Intake;

final readonly class IntakeAddressApi
{
    public function __construct(
        public string $address,
        public string $kind,
        public string $label,
    ) {}
}
