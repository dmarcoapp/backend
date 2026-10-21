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
        $user = $this->createAccount('report-repo-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');
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

    public function testWeeklyQueriesCountMessagesByOutcome(): void
    {
        $user = $this->createAccount('weekly-repo-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');
        $repository = self::getContainer()->get(ReportRepository::class);
        $utc = new \DateTimeZone('UTC');

        // Reporters do not agree on how to spell a domain.
        $older = $this->createReportForUser(
            $user,
            domain: 'Example.ORG',
            begin: new \DateTimeImmutable('2024-03-04 00:00:00', $utc),
            end: new \DateTimeImmutable('2024-03-04 23:59:59', $utc),
            reportingOrganization: 'Example Reporter',
            sumCount: 10,
            createdAt: new \DateTimeImmutable('2024-03-05 06:00:00', $utc),
        );
        $older->setPPolicy(DispositionType::NONE);
        $this->addRecordWithResults($older, '192.0.2.1', 10, DispositionType::NONE, DKIMAlign::PASS, SPFAlign::PASS, 'pass', 'example.org', SPFResult::PASS, 'example.org');

        $latest = $this->createReportForUser(
            $user,
            domain: 'example.org',
            begin: new \DateTimeImmutable('2024-03-05 00:00:00', $utc),
            end: new \DateTimeImmutable('2024-03-05 23:59:59', $utc),
            reportingOrganization: 'Example Reporter',
            sumCount: 160,
            createdAt: new \DateTimeImmutable('2024-03-06 06:00:00', $utc),
        );
        $latest->setPPolicy(DispositionType::QUARANTINE)->setPctPolicy(50);
        $this->addRecordWithResults($latest, '192.0.2.1', 100, DispositionType::NONE, DKIMAlign::PASS, SPFAlign::FAIL, 'pass', 'example.org', SPFResult::PASS, 'bounce.example.org');
        $this->addRecordWithResults($latest, '198.51.100.10', 40, DispositionType::NONE, DKIMAlign::FAIL, SPFAlign::FAIL, 'pass', 'mailer.example', SPFResult::PASS, 'mailer.example');
        $this->addRecordWithResults($latest, '203.0.113.5', 20, DispositionType::QUARANTINE, DKIMAlign::FAIL, SPFAlign::FAIL, '', '', SPFResult::FAIL, 'example.org');

        $other = $this->createReportForUser(
            $user,
            domain: 'example.com',
            begin: new \DateTimeImmutable('2024-03-05 00:00:00', $utc),
            end: new \DateTimeImmutable('2024-03-05 23:59:59', $utc),
            reportingOrganization: 'Example Reporter',
            sumCount: 5,
            createdAt: new \DateTimeImmutable('2024-03-06 06:00:00', $utc),
        );
        $other->setPPolicy(DispositionType::REJECT)->setSpPolicy(DispositionType::NONE);
        $this->addRecordWithResults($other, '203.0.113.6', 5, DispositionType::REJECT, DKIMAlign::FAIL, SPFAlign::FAIL, 'fail', 'example.com', SPFResult::FAIL, 'example.com');

        // A later report whose policy could not be read leaves the known one.
        $unreadable = $this->createReportForUser(
            $user,
            domain: 'example.com',
            begin: new \DateTimeImmutable('2024-03-06 00:00:00', $utc),
            end: new \DateTimeImmutable('2024-03-06 23:59:59', $utc),
            reportingOrganization: 'Example Reporter',
            sumCount: 0,
            createdAt: new \DateTimeImmutable('2024-03-07 06:00:00', $utc),
        );
        $unreadable->setPPolicy(DispositionType::UNKNOWN);

        $this->entityManager->flush();

        $period = new \DatePeriod(
            new \DateTimeImmutable('2024-03-04 00:00:00', $utc),
            new \DateInterval('P1D'),
            new \DateTimeImmutable('2024-03-11 00:00:00', $utc),
        );

        self::assertSame(
            [
                ['domain' => 'example.org', 'messages' => 170, 'passed' => 110, 'failedDelivered' => 40, 'blocked' => 20],
                ['domain' => 'example.com', 'messages' => 5, 'passed' => 0, 'failedDelivered' => 0, 'blocked' => 5],
            ],
            $repository->getTrafficByDomain($period, $user),
        );
        self::assertEqualsWithDelta(110 / 175, $repository->getPassRate($period, $user), 0.0001);

        $policies = $repository->getLatestPolicyByDomain($period, $user);
        ksort($policies);
        self::assertSame(
            [
                'example.com' => ['policy' => 'reject', 'percent' => null, 'subdomainPolicy' => 'none'],
                'example.org' => ['policy' => 'quarantine', 'percent' => 50, 'subdomainPolicy' => null],
            ],
            $policies,
        );

        $failing = $repository->getFailingSources($period, $user);
        self::assertCount(3, $failing);
        self::assertSame(
            [
                'domain' => 'example.org',
                'sourceIp' => '198.51.100.10',
                'orgName' => 'Example Org',
                'orgCountry' => 'HU',
                'dkimAuth' => 'pass',
                'dkimDomain' => 'mailer.example',
                'spfAuth' => 'pass',
                'spfDomain' => 'mailer.example',
                'messages' => 40,
                'delivered' => 40,
                'quarantined' => 0,
                'rejected' => 0,
            ],
            $failing[0],
        );
        self::assertSame(['203.0.113.5', '203.0.113.6'], [$failing[1]['sourceIp'], $failing[2]['sourceIp']]);
        self::assertSame([0, 20, 0], [$failing[1]['delivered'], $failing[1]['quarantined'], $failing[1]['rejected']]);
        self::assertSame([0, 0, 5], [$failing[2]['delivered'], $failing[2]['quarantined'], $failing[2]['rejected']]);

        // The dashboard's gauges weigh messages the way its pass rate does.
        self::assertEqualsWithDelta(
            ['dmarc' => 110 / 175, 'dkim' => 110 / 175, 'spf' => 10 / 175],
            $repository->getComplianceAverage($period, $user)[0],
            0.0001,
        );

        $firstReports = $repository->getFirstReportDateByDomain($user);
        ksort($firstReports);
        self::assertSame(['example.com', 'example.org'], array_keys($firstReports));
        self::assertSame('2024-03-04 00:00', $firstReports['example.org']->format('Y-m-d H:i'));
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

        // Mail that was quarantined failed DMARC, so it is aligned on neither.
        $passed = DispositionType::NONE === $disposition;

        $record = new ReportRecord();
        $record
            ->setReport($report)
            ->setSourceIp('192.0.2.1')
            ->setSourceIpInfo($sourceIpInfo)
            ->setCount(1)
            ->setDisposition($disposition)
            ->setDkimAlign($passed ? DKIMAlign::PASS : DKIMAlign::FAIL)
            ->setSpfAlign($passed ? SPFAlign::PASS : SPFAlign::FAIL)
            ->setDkimAuth($passed ? 'pass' : 'fail')
            ->setSpfAuth($passed ? SPFResult::PASS : SPFResult::FAIL)
            ->setSpfDomain('example.com')
        ;

        $report->addRecord($record);
        $this->entityManager->persist($record);
    }

    private function addRecordWithResults(
        Report $report,
        string $sourceIp,
        int $count,
        DispositionType $disposition,
        DKIMAlign $dkimAlign,
        SPFAlign $spfAlign,
        string $dkimAuth,
        string $dkimDomain,
        SPFResult $spfAuth,
        string $spfDomain,
    ): void {
        $record = new ReportRecord();
        $record
            ->setReport($report)
            ->setSourceIp($sourceIp)
            ->setSourceIpInfo(
                (new IpInfo())
                    ->setOrgName('Example Org')
                    ->setOrgCountry('HU')
            )
            ->setCount($count)
            ->setDisposition($disposition)
            ->setDkimAlign($dkimAlign)
            ->setSpfAlign($spfAlign)
            ->setDkimAuth($dkimAuth)
            ->setDkimDomain($dkimDomain)
            ->setSpfAuth($spfAuth)
            ->setSpfDomain($spfDomain)
        ;

        $report->addRecord($record);
        $this->entityManager->persist($record);
    }
}
