<?php

declare(strict_types=1);

namespace App\Enum\Platform;

enum SmtpSecurity: string
{
    // TLS from the first byte, usually on port 465.
    case TLS = 'tls';

    // Plain connection upgraded with STARTTLS, usually on port 587. The upgrade
    // is required, so a server that does not offer it is not used in the clear.
    case STARTTLS = 'starttls';

    // No encryption, for a relay on the same host or network.
    case NONE = 'none';

    public function defaultPort(): int
    {
        return match ($this) {
            self::TLS => 465,
            self::STARTTLS => 587,
            self::NONE => 25,
        };
    }
}
