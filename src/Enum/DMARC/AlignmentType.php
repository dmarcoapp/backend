<?php

declare(strict_types=1);

namespace App\Enum\DMARC;

enum AlignmentType: string
{
    case RELAXED = 'r';
    case STRICT = 's';
}
