<?php

declare(strict_types=1);

namespace App\Message\DMARC;

use Symfony\Component\Messenger\Attribute\AsMessage;

// Deletes the reports older than the retention period.
#[AsMessage('async_low')]
final readonly class DeleteExpiredReports {}
