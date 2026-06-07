<?php

declare(strict_types=1);

namespace App\MessageHandler\Email;

use App\Entity\DMARC\Domain;
use App\Entity\DMARC\Report;
use App\Entity\Email\Email;
use App\Entity\User\User;
use App\Helper\Int32;
use App\Message\DMARC\ProcessReport;
use App\Message\Email\ProcessEmail;
use App\Repository\DMARC\DomainRepository;
use App\Repository\DMARC\ReportRepository;
use App\Repository\Email\EmailRepository;
use App\Service\DMARC\ReportXMLProcessor;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\String\ByteString;

#[AsMessageHandler]
final readonly class ProcessEmailHandler
{
    private const array SUPPORTED_EXTENSIONS = ['xml'];
    private const int USER_LOCK_NAMESPACE = 204958833;

    public function __construct(
        private EmailRepository $emailRepository,
        private DomainRepository $domainRepository,
        private ReportRepository $reportRepository,
        private ReportXMLProcessor $reportXMLProcessor,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
        #[Autowire(service: 'flysystem.adapter.mail_attachments.storage.s3')]
        private AwsS3V3Adapter $remoteMailAttachmentsStorage,
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
        $attachmentKey = $email->getAttachmentKey();
        if (null === $attachmentKey) {
            throw new UnrecoverableMessageHandlingException('Email attachment key is missing.');
        }

        $extension = new ByteString($attachmentFilename)->afterLast('.')->toString();
        if (!in_array($extension, self::SUPPORTED_EXTENSIONS, true)) {
            $this->logger->error('Unsupported attachment extension.', [
                'extension' => $extension,
                'emailId' => $emailId->toRfc4122(),
            ]);

            return;
        }

        $owner = $email->getOwner();
        if (null === $owner) {
            throw new \LogicException('Email owner is missing.');
        }

        if (!$this->remoteMailAttachmentsStorage->fileExists($attachmentKey)) {
            throw new UnrecoverableMessageHandlingException('Email attachment not found.');
        }

        $xmlContent = $this->remoteMailAttachmentsStorage->read($attachmentKey);

        if (empty($xmlContent)) {
            $this->logger->error('Empty XML content.', [
                'emailId' => $emailId->toRfc4122(),
            ]);

            return;
        }

        if (!str_starts_with(ltrim($xmlContent), '<?xml')) {
            $this->logger->error('Invalid XML content (Dumb check).', [
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

        $reportDomain = $feedback->getPolicyPublished()->getDomain();
        $this->lockUserProcessing($owner);

        try {
            $existingDomain = $this->domainRepository->findOneBy([
                'user' => $owner,
                'domain' => $reportDomain,
            ]);

            if (null === $existingDomain) {
                $this->entityManager->persist(new Domain($owner, $reportDomain));
            }
        } finally {
            $this->unlockUserProcessing($owner);
        }

        $report = $this->createReportForEmail($email, $xmlContent);
        $reportId = $report->getId();
        if (null === $reportId) {
            throw new \LogicException('Report ID is missing.');
        }

        $this->messageBus->dispatch(
            new ProcessReport(reportId: $reportId->toRfc4122())
        );
    }

    private function createReportForEmail(Email $email, string $xmlContent): Report
    {
        $report = $this->reportRepository->findOneBy(['email' => $email]);
        if (!$report) {
            $report = new Report()
                ->setEmail($email)
            ;
            $this->entityManager->persist($report);
        }

        $report->setRawXML($xmlContent);

        $this->entityManager->flush();

        return $report;
    }

    private function lockUserProcessing(User $owner): void
    {
        $ownerId = $owner->getId();
        if (null === $ownerId) {
            throw new \LogicException('User ID is missing for report processing lock.');
        }

        $connection = $this->entityManager->getConnection();
        if (!$this->isPostgreSql($connection)) {
            return;
        }

        $connection->executeQuery(
            'SELECT pg_advisory_lock(:namespace, :userKey)',
            [
                'namespace' => self::USER_LOCK_NAMESPACE,
                'userKey' => Int32::toSigned(crc32($ownerId->toRfc4122())),
            ]
        );
    }

    private function unlockUserProcessing(User $owner): void
    {
        $ownerId = $owner->getId();
        if (null === $ownerId) {
            return;
        }

        $connection = $this->entityManager->getConnection();
        if (!$this->isPostgreSql($connection)) {
            return;
        }

        $connection->executeQuery(
            'SELECT pg_advisory_unlock(:namespace, :userKey)',
            [
                'namespace' => self::USER_LOCK_NAMESPACE,
                'userKey' => Int32::toSigned(crc32($ownerId->toRfc4122())),
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
