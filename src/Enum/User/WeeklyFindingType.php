<?php

declare(strict_types=1);

namespace App\Enum\User;

enum WeeklyFindingType: string
{
    // Mail DKIM-signed by some other domain, typically a sending service
    // without DKIM set up for the user's domain.
    case UNALIGNED_SERVICE = 'unaligned_service';
    // Mail that failed DMARC and reached receivers, because the domain's
    // policy does not stop it.
    case DELIVERED_FAILURES = 'delivered_failures';
    // A domain sent its first reports this week.
    case NEW_DOMAIN = 'new_domain';
    // A domain reported the week before and not this week.
    case QUIET_DOMAIN = 'quiet_domain';
}
