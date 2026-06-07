<?php

declare(strict_types=1);

namespace App\MessageHandler\DMARC;

use App\DTO\Input\DMARC\ReportXML\Dkim;
use App\DTO\Input\DMARC\ReportXML\Feedback;
use App\DTO\Input\DMARC\ReportXML\Spf;
use App\Entity\DMARC\IpInfo;
use App\Entity\DMARC\ReportRecord;
use App\Enum\DMARC\AlignmentType;
use App\Enum\DMARC\DispositionType;
use App\Enum\DMARC\DKIMAlign;
use App\Enum\DMARC\SPFAlign;
use App\Enum\DMARC\SPFResult;
use App\Message\DMARC\ProcessReport;
use App\Repository\DMARC\ReportRepository;
use App\Service\DMARC\ReportXMLProcessor;
use App\Service\IpLookup\IpLookupInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

#[AsMessageHandler]
final readonly class ProcessReportHandler
{
    public function __construct(
        private ReportRepository $reportRepository,
        private EntityManagerInterface $entityManager,
        private ReportXMLProcessor $reportXMLProcessor,
        private IpLookupInterface $ipLookup,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(ProcessReport $message): void
    {
        $report = $this->reportRepository->find($message->reportId);

        if (!$report) {
            return;
        }

        if ($report->isProcessed()) {
            return;
        }

        $rawXml = $report->getRawXML();

        if (empty($rawXml)) {
            throw new \LogicException('Report XML should not be empty at this point.');
        }

        try {
            $feedbackDTO = $this->reportXMLProcessor->deserialize($rawXml);
        } catch (ExceptionInterface $e) {
            throw new UnrecoverableMessageHandlingException('Failed to deserialize report XML.', previous: $e);
        }

        $reportMetadataDTO = $feedbackDTO->getReportMetadata();
        $policyPublishedDTO = $feedbackDTO->getPolicyPublished();
        $adkimPolicy = $policyPublishedDTO->getAdkim();
        $aspfPolicy = $policyPublishedDTO->getAspf();
        $pPolicy = $policyPublishedDTO->getP() ?? DispositionType::UNKNOWN->value;

        $report
            ->setReportingOrganization($reportMetadataDTO->getOrgName())
            ->setReportingOrganizationEmail($reportMetadataDTO->getEmail())
            ->setReportingOrganizationExtraContact($reportMetadataDTO->getExtraContactInfo())
            ->setReportId($reportMetadataDTO->getReportId())
            ->setBeginDate(
                \DateTimeImmutable::createFromFormat('U', (string) $reportMetadataDTO->getDateRange()->getBegin())
            )
            ->setEndDate(
                \DateTimeImmutable::createFromFormat('U', (string) $reportMetadataDTO->getDateRange()->getEnd())
            )
            ->setDomain($policyPublishedDTO->getDomain())
            ->setAdkimPolicy(null === $adkimPolicy ? null : AlignmentType::tryFrom($adkimPolicy))
            ->setAspfPolicy(null === $aspfPolicy ? null : AlignmentType::tryFrom($aspfPolicy))
            ->setPPolicy(DispositionType::tryFrom($pPolicy) ?? DispositionType::UNKNOWN)
            ->setSpPolicy(!empty($policyPublishedDTO->getSp()) ? DispositionType::tryFrom($policyPublishedDTO->getSp()) : null)
            ->setPctPolicy($policyPublishedDTO->getPct())
            ->setNpPolicy(!empty($policyPublishedDTO->getNp()) ? DispositionType::tryFrom($policyPublishedDTO->getNp()) : null)
        ;

        $sumCount = 0;

        $dmarcPassCount = 0;
        $dmarcCount = 0;
        $spfPassCount = 0;
        $spfCount = 0;
        $dkimPassCount = 0;
        $dkimCount = 0;
        foreach ($feedbackDTO->getRecord() as $feedbackRecordDTO) {
            $rowDTO = $feedbackRecordDTO->getRow();
            $policyEvaluatedDTO = $rowDTO->getPolicyEvaluated();
            $dkimAuthResultDTO = $feedbackRecordDTO->getAuthResults()->getDkim() ?? [];
            $spfAuthResultDTO = $feedbackRecordDTO->getAuthResults()->getSpf();

            $sumCount += $rowDTO->getCount();

            $reportRecord = new ReportRecord()
                ->setSourceIp($rowDTO->getSourceIp())
                ->setCount($rowDTO->getCount())
                ->setDisposition(DispositionType::tryFrom($policyEvaluatedDTO->getDisposition()) ?? DispositionType::UNKNOWN)
                ->setDkimAlign(DKIMAlign::tryFrom($policyEvaluatedDTO->getDkim()) ?? DKIMAlign::UNKNOWN)
                ->setSpfAlign(SPFAlign::tryFrom($policyEvaluatedDTO->getSpf()) ?? SPFAlign::UNKNOWN)
                ->setDkimAuth( // TODO: DKIMResult enum or object ??
                    implode('/', array_map(fn (Dkim $dkim) => $dkim->getResult(), $dkimAuthResultDTO))
                )
                ->setDkimDomain(
                    implode('/', array_map(fn (Dkim $dkim) => $dkim->getDomain(), $dkimAuthResultDTO))
                )
                ->setDkimSelector(
                    implode('/', array_map(fn (Dkim $dkim) => $dkim->getSelector(), $dkimAuthResultDTO))
                )
                ->setSpfAuth(
                    SPFResult::tryFrom($spfAuthResultDTO[0]?->getResult() ?? 'unknown')
                )
                ->setSpfDomain(
                    implode('/', array_map(fn (Spf $spf) => $spf->getDomain(), $spfAuthResultDTO))
                )
            ;

            try {
                $lookupResult = $this->ipLookup->lookup($rowDTO->getSourceIp());
                if (!empty($lookupResult)) {
                    $ipInfo = new IpInfo()
                        ->setOrgName($lookupResult['name'] ?? null)
                        ->setOrgCountry($lookupResult['country'] ?? null)
                        ->setOrgAbuseEmail($lookupResult['email']['abuse'] ?? null)
                        ->setOrgTechEmail($lookupResult['email']['tech'] ?? null)
                    ;
                    $reportRecord->setSourceIpInfo($ipInfo);
                }
            } catch (\InvalidArgumentException $e) {
                $this->logger->error('Failed to lookup IP address.', [
                    'ip' => $rowDTO->getSourceIp(),
                    'error' => $e->getMessage(),
                ]);
            }

            $this->entityManager->persist($reportRecord);
            $report->addRecord($reportRecord);

            // Calculate compliance
            if (DispositionType::NONE === $reportRecord->getDisposition()) {
                ++$dmarcPassCount;
            }
            ++$dmarcCount;

            if (DKIMAlign::PASS === $reportRecord->getDkimAlign()) {
                ++$dkimPassCount;
            }
            ++$dkimCount;
            foreach (explode('/', $reportRecord->getDkimAuth() ?? '') as $dkimAuthResult) {
                if ('pass' === $dkimAuthResult) {
                    ++$dkimPassCount;
                }
                ++$dkimCount;
            }

            if (SPFAlign::PASS === $reportRecord->getSpfAlign()) {
                ++$spfPassCount;
            }
            if (SPFResult::PASS === $reportRecord->getSpfAuth()) {
                ++$spfPassCount;
            }
            $spfCount += 2;
        }

        $report
            ->setSumCount($sumCount)
            ->setIsProcessed(true)
            ->setDmarcCompliance($dmarcPassCount / $dmarcCount)
            ->setSpfCompliance($spfPassCount / $spfCount)
            ->setDkimCompliance($dkimPassCount / $dkimCount)
            ->setIsVerified($this->checkLegitimacy($feedbackDTO, $report->getEmail()?->getFromAddress() ?? ''))
        ;

        $this->entityManager->flush();
    }

    private function checkLegitimacy(Feedback $feedback, string $emailFromAddress): bool
    {
        $expectedOrgNamesForSender = match ($emailFromAddress) {
            'noreply-dmarc-support@google.com' => ['google.com'],
            'dmarcreport@microsoft.com' => ['Enterprise Outlook', 'Outlook.com'],
            'no-reply@us-1.mimecastreport.com' => ['Mimecast'],
            default => null,
        };

        $isReportMetadataMatchesActualSender = $feedback->getReportMetadata()->getEmail() === $emailFromAddress;

        $isReportMetadataMatchesExpectedOrgName = !$expectedOrgNamesForSender
            || in_array($feedback->getReportMetadata()->getOrgName(), $expectedOrgNamesForSender, true);

        return $isReportMetadataMatchesActualSender && $isReportMetadataMatchesExpectedOrgName;
    }
}
