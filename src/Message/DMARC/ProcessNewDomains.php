<?php

declare(strict_types=1);

namespace App\Message\DMARC;

use Symfony\Component\Messenger\Attribute\AsMessage;

// Checks the DNS of the domains that have not been checked yet.
#[AsMessage('async_low')]
final readonly class ProcessNewDomains {}
