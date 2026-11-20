<?php

declare(strict_types=1);

namespace App\Scheduler\Intake;

use App\Repository\Intake\MailboxLogEntryRepository;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

#[AsPeriodicTask(frequency: '1 day', jitter: 3600)]
final readonly class ClearOldMailboxLogs
{
    public const string RETENTION = '-30 days';

    public function __construct(
        private MailboxLogEntryRepository $repository,
    ) {}

    public function __invoke(): void
    {
        $this->repository->deleteOlderThan(new \DateTimeImmutable(self::RETENTION, new \DateTimeZone('UTC')));
    }
}
