<?php

declare(strict_types=1);

namespace App\Message\Email;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async')]
final readonly class ProcessEmail
{
    public function __construct(
        public string $emailId,
    ) {}
}
