<?php

declare(strict_types=1);

namespace App\Scheduler\Auth;

use App\Repository\User\AuthLogRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

#[AsPeriodicTask(frequency: '1 day', jitter: 3600)]
final readonly class ClearOldAuthLogs
{
    public function __construct(
        private AuthLogRepository $authLogRepository,
        #[Autowire(param: 'app.auth_log.retention_interval')]
        private string $retentionInterval,
    ) {}

    public function __invoke(): void
    {
        try {
            $threshold = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                ->modify('-'.$this->retentionInterval)
            ;
        } catch (\DateMalformedStringException $exception) {
            throw new \LogicException('Invalid auth log retention interval.', 0, $exception);
        }

        $this->authLogRepository->deleteOlderThan($threshold);
    }
}
