<?php

declare(strict_types=1);

namespace App\Enum\DMARC;

enum DispositionType: string
{
    case NONE = 'none';
    case QUARANTINE = 'quarantine';
    case REJECT = 'reject';
    case UNKNOWN = 'unknown';
}
