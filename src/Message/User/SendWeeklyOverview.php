<?php

declare(strict_types=1);

namespace App\Message\User;

use Symfony\Component\Messenger\Attribute\AsMessage;

// Sends the account the overview of the last full week.
#[AsMessage('async_low')]
final readonly class SendWeeklyOverview {}
