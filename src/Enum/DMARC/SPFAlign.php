<?php

declare(strict_types=1);

namespace App\Enum\DMARC;

enum SPFAlign: string
{
    case FAIL = 'fail';
    case PASS = 'pass';
    case UNKNOWN = 'unknown';
}
