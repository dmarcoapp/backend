<?php

declare(strict_types=1);

namespace App\Message\DMARC;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async')]
final readonly class ProcessReport
{
    public function __construct(
        public string $reportId,
    ) {}
}
