<?php

declare(strict_types=1);

namespace App\DTO\Output\Intake;

final readonly class MailboxTestResultApi
{
    /**
     * @param list<string> $folders
     */
    public function __construct(
        public bool $ok,
        public ?string $error,
        public ?int $messages,
        public array $folders,
    ) {}
}
