<?php

declare(strict_types=1);

namespace App\DTO\Output\User\WeeklyOverview;

use App\Enum\User\WeeklyFindingType;

/**
 * One thing in the weekly overview that is worth the reader's attention.
 */
final readonly class Finding
{
    public function __construct(
        public WeeklyFindingType $type,
        public DomainSummary $domain,
        // Messages the finding is about, and what receivers did with them.
        // For a quiet domain, the messages of the week before.
        public int $messages = 0,
        public int $delivered = 0,
        public int $quarantined = 0,
        public int $rejected = 0,
        public int $sourceCount = 0,
        public ?SourceSummary $topSource = null,
        // The domain an unaligned service DKIM-signed the mail with.
        public ?string $signingDomain = null,
    ) {}
}
