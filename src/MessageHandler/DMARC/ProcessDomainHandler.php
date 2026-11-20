<?php

declare(strict_types=1);

namespace App\MessageHandler\DMARC;

use App\Helper\DMARC;
use App\Message\DMARC\ProcessDomain;
use App\Repository\DMARC\DomainRepository;
use App\Service\Intake\IntakeAddress;
use App\Service\Intake\IntakeAddresses;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ProcessDomainHandler
{
    public function __construct(
        private DomainRepository $domainRepository,
        private EntityManagerInterface $entityManager,
        private IntakeAddresses $intakeAddresses,
    ) {}

    public function __invoke(ProcessDomain $message): void
    {
        $domain = $this->domainRepository->find($message->domainId);
        if (!$domain) {
            return;
        }

        $domain->setLastChecked(new \DateTimeImmutable());

        $intakeAddresses = array_map(static fn (IntakeAddress $address): string => $address->address, $this->intakeAddresses->all());

        $dmarcHost = sprintf('_dmarc.%s', $domain->getDomain());

        try {
            $records = dns_get_record($dmarcHost, DNS_TXT);

            if (empty($records)) {
                return;
            }

            // TODO: save TTL and do not check until TTL expires
            foreach ($records as $record) {
                if (isset($record['txt']) && str_starts_with($record['txt'], 'v=DMARC1')) {
                    $domain->setDmarcRecord($record['txt']);
                    $domain->setProtectionLevel(DMARC::dmarcProtectionLevel($record['txt']));
                    $domain->setIsConfiguredCorrectly(array_any(
                        $intakeAddresses,
                        static fn (string $address): bool => DMARC::checkConfiguration($record['txt'], $address),
                    ));

                    break;
                }
            }
        } finally {
            $this->entityManager->flush();
        }
    }
}
