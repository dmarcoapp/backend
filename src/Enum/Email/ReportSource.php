<?php

declare(strict_types=1);

namespace App\Enum\Email;

/**
 * How a report reached DMARCo.
 */
enum ReportSource: string
{
    // Through the signed webhook of a mail gateway, such as DMARCo's own.
    case GATEWAY = 'gateway';

    // Uploaded with an API key.
    case API = 'api';

    // Fetched from a mailbox over IMAP.
    case IMAP = 'imap';
}
