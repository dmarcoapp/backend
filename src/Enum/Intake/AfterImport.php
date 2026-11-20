<?php

declare(strict_types=1);

namespace App\Enum\Intake;

/**
 * What happens to a report email in its mailbox once DMARCo has stored the
 * report. Other mail in the mailbox is never touched.
 */
enum AfterImport: string
{
    // Marked as read.
    case MARK_SEEN = 'mark_seen';

    // Moved to another folder.
    case MOVE = 'move';

    // Marked as deleted. DMARCo never expunges, which would also remove other
    // mail marked as deleted, so the mail client or server does that.
    case DELETE = 'delete';

    // Left exactly as it was. The mailbox is opened read-only.
    case LEAVE = 'leave';
}
