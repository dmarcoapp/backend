<?php

declare(strict_types=1);

namespace App\DTO\Output\Platform;

final readonly class TestEmailResultApi
{
    public function __construct(
        public bool $sent,
        public string $recipient,
        public ?string $error = null,
    ) {}
}
