<?php

declare(strict_types=1);

namespace App\Enum\DMARC;

enum SPFResult: string
{
    case NONE = 'none';
    case FAIL = 'fail';
    case PASS = 'pass';
    case NEUTRAL = 'neutral';
    case SOFTFAIL = 'softfail';
    case TEMPERROR = 'temperror';
    case PERMERROR = 'permerror';
    case UNKNOWN = 'unknown';
}
