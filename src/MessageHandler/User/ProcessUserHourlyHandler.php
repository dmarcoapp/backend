<?php

declare(strict_types=1);

namespace App\MessageHandler\User;

use App\Entity\User\User;
use App\Message\DMARC\ProcessDomain;
use App\Message\User\ProcessUserHourly;
use App\Repository\DMARC\DomainRepository;
use App\Repository\User\UserRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class ProcessUserHourlyHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private DomainRepository $domainRepository,
        private MessageBusInterface $messageBus,
    ) {}

    public function __invoke(ProcessUserHourly $message): void
    {
        $user = $this->userRepository->find($message->userId);

        if (!$user) {
            return;
        }

        $this->processDomains($user);
    }

    public function processDomains(User $user): void
    {
        $domains = $this->domainRepository->getUncheckedDomainsForUser($user);

        foreach ($domains as $domain) {
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
