<?php

declare(strict_types=1);

namespace App\Enum\Email;

/**
 * How a report reached DMARCo.
 */
enum ReportSource: string
{
    // Fetched from a mailbox over IMAP.
    case MAILBOX = 'mailbox';

    // Uploaded to the upload endpoint with an API key.
    case UPLOAD = 'upload';

    // Posted to the webhook by a mail gateway, such as dmarcoapp/mail-inbound.
    case WEBHOOK = 'webhook';
}
