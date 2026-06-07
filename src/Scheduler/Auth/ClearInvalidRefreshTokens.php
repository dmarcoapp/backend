<?php

declare(strict_types=1);

namespace App\Scheduler\Auth;

use Symfony\Component\Process\Process;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

#[AsPeriodicTask(frequency: '1 day', jitter: 3600)]
final readonly class ClearInvalidRefreshTokens
{
    public function __invoke(): void
    {
        $process = new Process(['bin/console', 'gesdinet:jwt:clear']);
        $process->run();
    }
}
