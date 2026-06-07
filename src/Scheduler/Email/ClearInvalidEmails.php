<?php

declare(strict_types=1);

namespace App\Scheduler\Email;

use App\Repository\Email\EmailRepository;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

#[AsPeriodicTask(frequency: '1 day', jitter: 3600)]
final readonly class ClearInvalidEmails
{
    public function __construct(private EmailRepository $repository) {}

    public function __invoke(): void
    {
        $ids = $this->repository->getJunkEmailIdsToDelete();

        foreach (array_chunk($ids, 100) as $chunk) {
            $this->repository->deleteByIds($chunk);
        }
    }
}
