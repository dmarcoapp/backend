<?php

declare(strict_types=1);

namespace App\Scheduler\User;

use App\Message\User\SendWeeklyOverview;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

// At 06:00 on Tuesday. The overview covers the week up to Sunday, and the
// reports for a day arrive during the next one, so Monday is too early.
#[AsCronTask(expression: '0 6 * * 2', timezone: 'UTC')]
final readonly class SendWeeklyOverviewProducer
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {}

    public function __invoke(): void
    {
        $this->messageBus->dispatch(new SendWeeklyOverview());
    }
}
