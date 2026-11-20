<?php

declare(strict_types=1);

namespace App\Enum\Intake;

/**
 * How DMARCo signs in to a mailbox. OAuth, which Microsoft 365 requires and
 * Google prefers, is meant to join as another case, with its tokens kept in
 * the same encrypted credentials.
 */
enum MailboxAuthMethod: string
{
    case PASSWORD = 'password';
}
