<?php

declare(strict_types=1);

namespace App\Message\Email;

use App\DTO\Input\Email\Email;
use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async')]
final readonly class InboundEmail
{
    public function __construct(
        public Email $email,
    ) {}
}
