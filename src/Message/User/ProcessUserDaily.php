<?php

declare(strict_types=1);

namespace App\Message\User;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async_low')]
final readonly class ProcessUserDaily
{
    public function __construct(
        public string $userId,
    ) {}
}
