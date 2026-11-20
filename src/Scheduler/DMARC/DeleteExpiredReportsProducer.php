<?php

declare(strict_types=1);

namespace App\Scheduler\DMARC;

use App\Message\DMARC\DeleteExpiredReports;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCronTask(expression: '0 0 * * *', timezone: 'UTC')]
final readonly class DeleteExpiredReportsProducer
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {}

    public function __invoke(): void
    {
        $this->messageBus->dispatch(new DeleteExpiredReports());
    }
}
