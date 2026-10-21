<?php

declare(strict_types=1);

namespace App\DTO\Output\User\WeeklyOverview;

/**
 * Message counts from aggregate reports. Every count is a number of messages,
 * not of report rows.
 */
final readonly class TrafficTotals
{
    public function __construct(
        public int $messages = 0,
        // Aligned on DKIM or SPF, so DMARC passed.
        public int $passed = 0,
        // Failed DMARC and was delivered anyway, usually because of p=none.
        public int $failedDelivered = 0,
        // Rejected or quarantined by the receiver.
        public int $blocked = 0,
    ) {}

    public function add(self $other): self
    {
        return new self(
            messages: $this->messages + $other->messages,
            passed: $this->passed + $other->passed,
            failedDelivered: $this->failedDelivered + $other->failedDelivered,
            blocked: $this->blocked + $other->blocked,
        );
    }

    /**
     * Share of messages that passed DMARC, between 0 and 1, or null when there
     * were no messages to judge.
     */
    public function passRate(): ?float
    {
        return $this->messages > 0 ? $this->passed / $this->messages : null;
    }
}
