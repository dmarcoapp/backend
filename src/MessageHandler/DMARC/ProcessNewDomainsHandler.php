<?php

declare(strict_types=1);

namespace App\MessageHandler\DMARC;

use App\Message\DMARC\ProcessDomain;
use App\Message\DMARC\ProcessNewDomains;
use App\Repository\DMARC\DomainRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

// The message carries nothing, so it is named here rather than taken as an argument.
#[AsMessageHandler(handles: ProcessNewDomains::class)]
final readonly class ProcessNewDomainsHandler
{
    public function __construct(
        private DomainRepository $domainRepository,
        private MessageBusInterface $messageBus,
    ) {}

    public function __invoke(): void
    {
        foreach ($this->domainRepository->getUncheckedDomains() as $domain) {
            $domainId = $domain->getId();
            if (!$domainId instanceof Uuid) {
                continue;
            }

            $this->messageBus->dispatch(
                new ProcessDomain($domainId->toRfc4122())
            );
        }
    }
}
