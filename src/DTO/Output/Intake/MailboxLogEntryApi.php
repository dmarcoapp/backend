<?php

declare(strict_types=1);

namespace App\DTO\Output\Intake;

final readonly class MailboxLogEntryApi
{
    /**
     * @param array<string, null|scalar> $context
     */
    public function __construct(
        public string $level,
        public string $message,
        public array $context,
        public \DateTimeImmutable $createdAt,
    ) {}
}
