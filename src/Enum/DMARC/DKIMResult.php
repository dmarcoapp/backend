<?php

declare(strict_types=1);

namespace App\Enum\DMARC;

enum DKIMResult: string
{
    case NONE = 'none';
    case FAIL = 'fail';
    case PASS = 'pass';
    case NEUTRAL = 'neutral';
    case POLICY = 'policy';
    case TEMPERROR = 'temperror';
    case PERMERROR = 'permerror';
    case UNKNOWN = 'unknown';
}
