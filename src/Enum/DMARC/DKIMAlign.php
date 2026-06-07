<?php

declare(strict_types=1);

namespace App\Enum\DMARC;

enum DKIMAlign: string
{
    case FAIL = 'fail';
    case PASS = 'pass';
    case UNKNOWN = 'unknown';
}
