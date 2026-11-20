<?php

declare(strict_types=1);

namespace App\Service\Intake;

final class PollResult
{
    public int $looked = 0;
    public int $imported = 0;
    public int $alreadyReceived = 0;
    public int $skipped = 0;
    public bool $more = false;

    /**
     * Another run was reading the mailbox, so this one did nothing.
     */
    public bool $busy = false;
    public ?string $error = null;

    public function succeeded(): bool
    {
        return null === $this->error;
    }
}
