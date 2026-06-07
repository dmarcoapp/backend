<?php

declare(strict_types=1);

namespace App\Enum\DMARC\Domain;

enum ProtectionLevel: string
{
    case WEAK = 'weak';
    case MODERATE = 'moderate';
    case STRONG = 'strong';
}
