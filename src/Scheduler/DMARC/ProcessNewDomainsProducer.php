<?php

declare(strict_types=1);

namespace App\Scheduler\DMARC;

use App\Message\DMARC\ProcessNewDomains;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

#[AsPeriodicTask(frequency: '1 hour', jitter: 60)]
final readonly class ProcessNewDomainsProducer
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {}

    public function __invoke(): void
    {
        $this->messageBus->dispatch(new ProcessNewDomains());
    }
}
