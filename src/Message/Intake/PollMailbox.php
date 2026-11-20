<?php

declare(strict_types=1);

namespace App\Message\Intake;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async_low')]
final readonly class PollMailbox
{
    public function __construct(
        public string $mailboxId,
    ) {}
}
