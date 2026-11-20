<?php

declare(strict_types=1);

namespace App\DTO\Output\Intake;

final readonly class ApiIntakeKeyApi
{
    public function __construct(
        public string $id,
        public string $name,
        public string $preview,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $lastUsedAt,
        // Only in the answer that made the key, and never again.
        public ?string $token = null,
    ) {}
}
