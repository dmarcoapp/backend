<?php

declare(strict_types=1);

namespace App\Service\Intake;

final readonly class MailboxTestResult
{
    /**
     * @param list<string> $folders the folders of the mailbox, the first ones
     */
    public function __construct(
        public bool $ok,
        public ?string $error = null,
        public ?int $messages = null,
        public array $folders = [],
    ) {}
}
