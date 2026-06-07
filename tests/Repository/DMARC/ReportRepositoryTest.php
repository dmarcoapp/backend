<?php

declare(strict_types=1);

namespace App\Tests\Repository\DMARC;

use App\DTO\Input\Common\Pagination;
use App\Entity\DMARC\IpInfo;
use App\Entity\DMARC\Report;
use App\Entity\DMARC\ReportRecord;
use App\Entity\Email\Email;
use App\Entity\User\User;
use App\Enum\DMARC\DispositionType;
use App\Enum\DMARC\DKIMAlign;
use App\Enum\DMARC\SPFAlign;
use App\Enum\DMARC\SPFResult;
use App\Repository\DMARC\ReportRepository;
use App\Tests\Api\ApiIntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @internal
 */
#[CoversClass(ReportRepository::class)]
final class ReportRepositoryTest extends ApiIntegrationTestCase
{
    public function testReportRepositoryAggregatesAndFiltersData(): void
    {
        $user = $this->createVerifiedUser('report-repo-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');
        $repository = self::getContainer()->get(ReportRepository::class);

        $report1 = $this->createReportForUser(
            $user,
            domain: 'example.com',
            begin: new \DateTimeImmutable('2024-01-01 00:00:00', new \DateTimeZone('UTC')),
            end: new \DateTimeImmutable('2024-01-02 00:00:00', new \DateTimeZone('UTC')),
            reportingOrganization: 'Example Reporter',
            sumCount: 3,
            createdAt: new \DateTimeImmutable('2024-01-01 12:00:00', new \DateTimeZone('UTC')),
        );
        $this->addReportRecord($report1, DispositionType::NONE);
        $this->addReportRecord($report1, DispositionType::QUARANTINE);

        $report2 = $this->createReportForUser(
            $user,
            domain: 'other.com',
            begin: new \DateTimeImmutable('2024-01-05 00:00:00', new \DateTimeZone('UTC')),
            end: new \DateTimeImmutable('2024-01-06 00:00:00', new \DateTimeZone('UTC')),
            reportingOrganization: 'Example Reporter',
            sumCount: 2,
            createdAt: new \DateTimeImmutable('2024-01-05 12:00:00', new \DateTimeZone('UTC')),
        );
        $this->addReportRecord($report2, DispositionType::NONE);

        $unprocessed = $this->createReportForUser(
            $user,
            domain: 'pending.com',
            begin: null,
            end: null,
            reportingOrganization: 'Pending Reporter',
            sumCount: null,
            createdAt: new \DateTimeImmutable('2024-02-01 12:00:00', new \DateTimeZone('UTC')),
            isProcessed: false,
        );

        $this->entityManager->flush();

        $paginated = $repository->getReportsForUserPaginated(
            $user,
            new Pagination(page: 1, pageSize: 10),
        );
        self::assertCount(2, $paginated);

        $period = new \DatePeriod(
            new \DateTimeImmutable('2024-01-01 00:00:00', new \DateTimeZone('UTC')),
            new \DateInterval('P1D'),
            new \DateTimeImmutable('2024-01-07 00:00:00', new \DateTimeZone('UTC')),
        );

        self::assertSame(2, $repository->getReportCountInDatePeriod($period, $user));
        self::assertSame(5, $repository->getSumCountInDatePeriod($period, $user));
        self::assertEqualsWithDelta(2 / 3, $repository->getPassRate($period, $user), 0.0001);
        self::assertSame(1, $repository->getThreatCount($period, $user));
        self::assertSame(
            [
                [
                    'count' => 1,
                    'sourceIp' => '192.0.2.1',
                    'sourceIpInfo' => [
                        'orgAbuseEmail' => 'abuse@example.com',
                        'orgCountry' => 'HU',
                        'orgName' => 'Example Org',
                        'orgTechEmail' => 'tech@example.com',
                    ],
                ],
            ],
            $repository->getOffendersInDatePeriod($period, $user),
        );
        self::assertSame(
            [
                [
                    'count' => 3,
                    'sourceIp' => '192.0.2.1',
                    'sourceIpInfo' => [
                        'orgAbuseEmail' => 'abuse@example.com',
                        'orgCountry' => 'HU',
                        'orgName' => 'Example Org',
                        'orgTechEmail' => 'tech@example.com',
                    ],
                ],
            ],
            $repository->getTopSendersInDatePeriod($period, $user),
        );
        self::assertSame(
            [
                [
                    'count' => 1,
                    'country' => 'HU',
                ],
            ],
            $repository->getSourceCountryDistributionInDatePeriod($period, $user),
        );
        self::assertSame(
            [
                [
                    'count' => 2,
                    'reportingOrganization' => 'Example Reporter',
                ],
            ],
            $repository->getTopReportingOrganizationsInDatePeriod($period, $user),
        );

        $unprocessedIds = $repository->getUnprocessedReportIds();
        self::assertNotEmpty($unprocessedIds);

        $deleted = $repository->deleteReportsForUserOlderThan(
            $user,
            new \DateTimeImmutable('2024-01-03 00:00:00', new \DateTimeZone('UTC')),
        );
        self::assertSame(1, $deleted);
    }

    private function createReportForUser(
        User $user,
        string $domain,
        ?\DateTimeImmutable $begin,
        ?\DateTimeImmutable $end,
        ?string $reportingOrganization,
        ?int $sumCount,
        \DateTimeImmutable $createdAt,
        bool $isProcessed = true,
    ): Report {
        $email = new Email();
        $email
            ->setCreatedAt($createdAt)
            ->setFromAddress('sender@example.com')
            ->setToAddress('recipient@example.com')
            ->setMessageId('<message@example.com>')
            ->setAttachmentBucket('bucket')
            ->setAttachmentKey('path/report.xml')
            ->setAttachmentFilename('report.xml')
            ->setAttachmentContentType('application/xml')
            ->setOwner($user)
        ;

        $report = new Report();
        $report
            ->setEmail($email)
            ->setDomain($domain)
            ->setBeginDate($begin)
            ->setEndDate($end)
            ->setReportingOrganization($reportingOrganization)
            ->setSumCount($sumCount)
            ->setIsProcessed($isProcessed)
        ;
        $email->setReport($report);

        $this->entityManager->persist($email);
        $this->entityManager->persist($report);

        return $report;
    }

    private function addReportRecord(Report $report, DispositionType $disposition): void
    {
        $sourceIpInfo = null;
        if (DispositionType::QUARANTINE === $disposition) {
            $sourceIpInfo = (new IpInfo())
                ->setOrgAbuseEmail('abuse@example.com')
                ->setOrgCountry('HU')
                ->setOrgName('Example Org')
                ->setOrgTechEmail('tech@example.com')
            ;
        }

        $record = new ReportRecord();
        $record
            ->setReport($report)
            ->setSourceIp('192.0.2.1')
            ->setSourceIpInfo($sourceIpInfo)
            ->setCount(1)
            ->setDisposition($disposition)
            ->setDkimAlign(DKIMAlign::PASS)
            ->setSpfAlign(SPFAlign::PASS)
            ->setDkimAuth('pass')
            ->setSpfAuth(SPFResult::PASS)
            ->setSpfDomain('example.com')
        ;

        $report->addRecord($record);
        $this->entityManager->persist($record);
    }
}
