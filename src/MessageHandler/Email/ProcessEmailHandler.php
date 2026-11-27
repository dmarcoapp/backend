<?php

declare(strict_types=1);

namespace App\MessageHandler\Email;

use App\DTO\Input\DMARC\ReportXML\ReportMetadata;
use App\Entity\DMARC\Domain;
use App\Entity\DMARC\Report;
use App\Entity\Email\Email;
use App\Helper\Int32;
use App\Message\DMARC\ProcessReport;
use App\Message\Email\ProcessEmail;
use App\Repository\DMARC\DomainRepository;
use App\Repository\DMARC\ReportRepository;
use App\Repository\Email\EmailRepository;
use App\Service\DMARC\ReportXMLProcessor;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\String\ByteString;

#[AsMessageHandler]
final readonly class ProcessEmailHandler
{
    private const array SUPPORTED_EXTENSIONS = ['xml'];
    private const int DOMAIN_LOCK_NAMESPACE = 204958833;

    public function __construct(
        private EmailRepository $emailRepository,
        private DomainRepository $domainRepository,
        private ReportRepository $reportRepository,
        private ReportXMLProcessor $reportXMLProcessor,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {}

    /**
     * @throws \Throwable
     */
    public function __invoke(ProcessEmail $message): void
    {
        $email = $this->emailRepository->find($message->emailId);
        if (!$email) {
            throw new UnrecoverableMessageHandlingException('Email not found.');
        }
        $emailId = $email->getId();
        if (null === $emailId) {
            throw new UnrecoverableMessageHandlingException('Email ID is missing.');
        }
        $attachmentFilename = $email->getAttachmentFilename();
        if (null === $attachmentFilename) {
            throw new UnrecoverableMessageHandlingException('Email attachment filename is missing.');
        }

        $extension = new ByteString($attachmentFilename)->afterLast('.')->toString();
        if (!in_array($extension, self::SUPPORTED_EXTENSIONS, true)) {
            $this->logger->error('Unsupported attachment extension.', [
                'extension' => $extension,
                'emailId' => $emailId->toRfc4122(),
            ]);

            return;
        }

        $xmlContent = $email->getAttachmentContent();
        if (null === $xmlContent) {
            // The report took the XML over in an earlier run, which then failed
            // before it handed the report on. Handing it on is all that is left.
            $report = $email->getReport();
            $reportId = $report?->getId();
            if (null === $reportId || null === $report?->getRawXML()) {
                throw new UnrecoverableMessageHandlingException('Email attachment content is missing.');
            }
            $this->messageBus->dispatch(new ProcessReport(reportId: $reportId->toRfc4122()));

            return;
        }

        if ('' === $xmlContent) {
            $this->logger->error('Empty XML content.', [
                'emailId' => $emailId->toRfc4122(),
            ]);

            return;
        }

        if (false === $this->reportXMLProcessor->validate($xmlContent)) {
            $this->logger->error('DMARC Report XML is not valid.', [
                'emailId' => $emailId->toRfc4122(),
            ]);

            return;
        }

        try {
            $feedback = $this->reportXMLProcessor->deserialize($xmlContent);
        } catch (ExceptionInterface $e) {
            throw new UnrecoverableMessageHandlingException('Failed to deserialize report XML.', previous: $e);
        }

        $metadata = $feedback->getReportMetadata();
        $reportDomain = $feedback->getPolicyPublished()->getDomain();
        $beginDate = \DateTimeImmutable::createFromFormat('U', (string) $metadata->getDateRange()->getBegin());
        if (false === $beginDate) {
            throw new UnrecoverableMessageHandlingException('Report begin date is invalid.');
        }

        // One report at a time for each domain, so that two copies of the same
        // report, or two first reports for a new domain, cannot both pass the
        // checks below before either of them is saved.
        $this->lockDomain($reportDomain);

        try {
            $copyOf = $this->reportRepository->findOneBy([
                'beginDate' => $beginDate,
                'domain' => $reportDomain,
                'reportId' => $metadata->getReportId(),
                'reportingOrganization' => $metadata->getOrgName(),
            ]);
            if (null !== $copyOf && $copyOf->getEmail() !== $email) {
                $this->logger->info('Report already received, dropping the copy.', [
                    'emailId' => $emailId->toRfc4122(),
                    'reportId' => $copyOf->getId()?->toRfc4122(),
                ]);
                $this->entityManager->remove($email);
                $this->entityManager->flush();

                return;
            }

            $domain = $this->domainRepository->findOneBy(['domain' => $reportDomain]);
            if (null === $domain) {
                $domain = new Domain($reportDomain);
                $this->entityManager->persist($domain);
            }
            $domain->recordReportReceived($email->getCreatedAt() ?? new \DateTimeImmutable());

            $report = $this->createReportForEmail($email, $xmlContent, $metadata, $reportDomain, $beginDate);
        } finally {
            $this->unlockDomain($reportDomain);
        }

        $reportId = $report->getId();
        if (null === $reportId) {
            throw new \LogicException('Report ID is missing.');
        }

        $this->messageBus->dispatch(
            new ProcessReport(reportId: $reportId->toRfc4122())
        );
    }

    private function createReportForEmail(
        Email $email,
        string $xmlContent,
        ReportMetadata $metadata,
        string $reportDomain,
        \DateTimeImmutable $beginDate,
    ): Report {
        $report = $this->reportRepository->findOneBy(['email' => $email]);
        if (!$report) {
            $report = new Report()
                ->setEmail($email)
            ;
            $this->entityManager->persist($report);
        }

        // What tells one report from another is known from here on, so a copy
        // that arrives before this one is processed is still recognized.
        $report
            ->setRawXML($xmlContent)
            ->setReportingOrganization($metadata->getOrgName())
            ->setReportId($metadata->getReportId())
            ->setDomain($reportDomain)
            ->setBeginDate($beginDate)
        ;
        // The report holds the XML from here on, so the email lets go of its copy.
        $email->setAttachmentContent(null);

        $this->entityManager->flush();

        return $report;
    }

    private function lockDomain(string $domain): void
    {
        $connection = $this->entityManager->getConnection();
        if (!$this->isPostgreSql($connection)) {
            return;
        }

        $connection->executeQuery(
            'SELECT pg_advisory_lock(:namespace, :domainKey)',
            [
                'domainKey' => Int32::toSigned(crc32($domain)),
                'namespace' => self::DOMAIN_LOCK_NAMESPACE,
            ]
        );
    }

    private function unlockDomain(string $domain): void
    {
        $connection = $this->entityManager->getConnection();
        if (!$this->isPostgreSql($connection)) {
            return;
        }

        $connection->executeQuery(
            'SELECT pg_advisory_unlock(:namespace, :domainKey)',
            [
                'domainKey' => Int32::toSigned(crc32($domain)),
                'namespace' => self::DOMAIN_LOCK_NAMESPACE,
            ]
        );
    }

    private function isPostgreSql(Connection $connection): bool
    {
        try {
            return 'postgresql' === $connection->getDatabasePlatform()->getName();
        } catch (\Throwable) {
            return false;
        }
    }
}
