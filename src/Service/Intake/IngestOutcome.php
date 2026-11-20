<?php

declare(strict_types=1);

namespace App\Service\Intake;

enum IngestOutcome: string
{
    case ACCEPTED = 'accepted';
    case ALREADY_RECEIVED = 'already_received';
    case BLOCKED = 'blocked';
}
