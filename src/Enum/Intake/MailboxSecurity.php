<?php

declare(strict_types=1);

namespace App\Enum\Intake;

/**
 * How a mailbox is reached. There is no unencrypted choice: the connection
 * carries the mailbox password.
 */
enum MailboxSecurity: string
{
    // TLS from the first byte, usually on port 993.
    case TLS = 'tls';

    // STARTTLS, usually on port 143. A server that does not take it up is not
    // talked to at all.
    case STARTTLS = 'starttls';

    public function defaultPort(): int
    {
        return match ($this) {
            self::TLS => 993,
            self::STARTTLS => 143,
        };
    }
}
