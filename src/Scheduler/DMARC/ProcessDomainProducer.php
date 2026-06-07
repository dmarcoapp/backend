<?php

declare(strict_types=1);

namespace App\Scheduler\DMARC;

use App\Message\DMARC\ProcessDomain;
use App\Repository\DMARC\DomainRepository;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;
use Symfony\Component\Uid\Uuid;

#[AsPeriodicTask(frequency: '1 day', jitter: 3600)]
final readonly class ProcessDomainProducer
{
    public function __construct(
        private DomainRepository $domainRepository,
        private MessageBusInterface $messageBus,
    ) {}

    public function __invoke(): void
    {
        $domains = $this->domainRepository->getDomainsForRegularProcessing();

        foreach ($domains as $domain) {
            $domainId = $domain->getId();
            if (!$domainId instanceof Uuid) {
                continue;
            }

            $this->messageBus->dispatch(
                new ProcessDomain($domainId->toRfc4122()),
            );
        }
    }
}
