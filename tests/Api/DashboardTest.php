<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\DMARC\Domain;
use App\Entity\DMARC\IpInfo;
use App\Entity\DMARC\Report;
use App\Entity\DMARC\ReportRecord;
use App\Entity\Email\Email;
use App\Enum\DMARC\DispositionType;
use App\Enum\DMARC\DKIMAlign;
use App\Enum\DMARC\SPFAlign;
use App\Enum\DMARC\SPFResult;

/**
 * @internal
 *
 * @coversNothing
 */
final class DashboardTest extends ApiIntegrationTestCase
{
    public function testDashboardReturnsMetricsAndCaches(): void
    {
        $user = $this->createVerifiedUser('dash@example.com', 'Str0ngPassw0rd!@#');

        $domain = new Domain($user, 'example.com');
        $domain->setLastChecked(new \DateTimeImmutable());
        $this->entityManager->persist($domain);

        $email = new Email();
        $email
            ->setCreatedAt(new \DateTimeImmutable('-1 day'))
            ->setFromAddress('reporter@example.com')
            ->setToAddress('inbound@example.com')
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
            ->setRawXML('<xml/>')
            ->setIsProcessed(true)
            ->setBeginDate(new \DateTimeImmutable('-2 days'))
            ->setEndDate(new \DateTimeImmutable('-1 day'))
            ->setDomain('example.com')
            ->setSumCount(5)
            ->setDmarcCompliance(1.0)
            ->setSpfCompliance(1.0)
            ->setDkimCompliance(1.0)
        ;

        $email->setReport($report);

        $this->entityManager->persist($email);
        $this->entityManager->persist($report);
        $this->entityManager->flush();

        $token = $this->login('dash@example.com', 'Str0ngPassw0rd!@#');

        $firstResponse = $this->requestJson(
            method: 'GET',
            uri: '/v1/user/dashboard?periodDays=7',
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(200, $firstResponse->getStatusCode());
        $firstPayload = json_decode((string) $firstResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

        foreach (['reportCount', 'messageCount', 'passRate', 'compliance', 'newDomainCount', 'threatCountByDate', 'topOffenders', 'topSenders', 'sourceCountryDistribution', 'topReportingOrganizations'] as $key) {
            self::assertArrayHasKey($key, $firstPayload);
        }

        $secondResponse = $this->requestJson(
            method: 'GET',
            uri: '/v1/user/dashboard?periodDays=7',
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(200, $secondResponse->getStatusCode());
        self::assertSame((string) $firstResponse->getContent(), (string) $secondResponse->getContent());
    }

    public function testDashboardRejectsInvalidPeriod(): void
    {
        $this->createVerifiedUser('dash2@example.com', 'Str0ngPassw0rd!@#');

        $token = $this->login('dash2@example.com', 'Str0ngPassw0rd!@#');

        $response = $this->requestJson(
            method: 'GET',
            uri: '/v1/user/dashboard?periodDays=5',
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testDashboardMetricsAreUserSpecific(): void
    {
        $userWithData = $this->createVerifiedUser('dash3@example.com', 'Str0ngPassw0rd!@#');
        $this->createVerifiedUser('dash4@example.com', 'Str0ngPassw0rd!@#');

        $domain = new Domain($userWithData, 'example.com');
        $domain->setLastChecked(new \DateTimeImmutable());
        $this->entityManager->persist($domain);

        $email = new Email();
        $email
            ->setCreatedAt(new \DateTimeImmutable('-1 day'))
            ->setFromAddress('reporter@example.com')
            ->setToAddress('inbound@example.com')
            ->setMessageId('<message@example.com>')
            ->setAttachmentBucket('bucket')
            ->setAttachmentKey('path/report.xml')
            ->setAttachmentFilename('report.xml')
            ->setAttachmentContentType('application/xml')
            ->setOwner($userWithData)
        ;

        $report = new Report();
        $report
            ->setEmail($email)
            ->setRawXML('<xml/>')
            ->setIsProcessed(true)
            ->setBeginDate(new \DateTimeImmutable('-2 days'))
            ->setEndDate(new \DateTimeImmutable('-1 day'))
            ->setDomain('example.com')
            ->setSumCount(5)
            ->setDmarcCompliance(1.0)
            ->setSpfCompliance(1.0)
            ->setDkimCompliance(1.0)
        ;

        $email->setReport($report);

        $this->entityManager->persist($email);
        $this->entityManager->persist($report);
        $this->entityManager->flush();

        $tokenWithData = $this->login('dash3@example.com', 'Str0ngPassw0rd!@#');
        $tokenNoData = $this->login('dash4@example.com', 'Str0ngPassw0rd!@#');

        $withDataResponse = $this->requestJson(
            method: 'GET',
            uri: '/v1/user/dashboard?periodDays=7',
            headers: $this->authorizeHeaders($tokenWithData),
        );

        self::assertSame(200, $withDataResponse->getStatusCode());
        $withDataPayload = json_decode((string) $withDataResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(1, $withDataPayload['reportCount'] ?? null);
        self::assertSame(5, $withDataPayload['messageCount'] ?? null);

        $noDataResponse = $this->requestJson(
            method: 'GET',
            uri: '/v1/user/dashboard?periodDays=7',
            headers: $this->authorizeHeaders($tokenNoData),
        );

        self::assertSame(200, $noDataResponse->getStatusCode());
        $noDataPayload = json_decode((string) $noDataResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(0, $noDataPayload['reportCount'] ?? null);
    }

    public function testDashboardThreatCountByDateCountsQuarantineAndReject(): void
    {
        $user = $this->createVerifiedUser('dash5@example.com', 'Str0ngPassw0rd!@#');

        $domain = new Domain($user, 'example.com');
        $domain->setLastChecked(new \DateTimeImmutable());
        $this->entityManager->persist($domain);

        $email = new Email();
        $email
            ->setCreatedAt(new \DateTimeImmutable('-1 day'))
            ->setFromAddress('reporter@example.com')
            ->setToAddress('inbound@example.com')
            ->setMessageId('<message@example.com>')
            ->setAttachmentBucket('bucket')
            ->setAttachmentKey('path/report.xml')
            ->setAttachmentFilename('report.xml')
            ->setAttachmentContentType('application/xml')
            ->setOwner($user)
        ;

        $beginDate = new \DateTimeImmutable('-2 days');
        $report = new Report();
        $report
            ->setEmail($email)
            ->setRawXML('<xml/>')
            ->setIsProcessed(true)
            ->setBeginDate($beginDate)
            ->setEndDate(new \DateTimeImmutable('-1 day'))
            ->setDomain('example.com')
            ->setSumCount(10)
            ->setDmarcCompliance(1.0)
            ->setSpfCompliance(1.0)
            ->setDkimCompliance(1.0)
        ;

        $report->addRecord($this->createReportRecord($report, 3, DispositionType::QUARANTINE));
        $report->addRecord($this->createReportRecord($report, 2, DispositionType::REJECT));
        $report->addRecord($this->createReportRecord($report, 5, DispositionType::NONE));

        $email->setReport($report);

        $this->entityManager->persist($email);
        $this->entityManager->persist($report);
        $this->entityManager->flush();

        $token = $this->login('dash5@example.com', 'Str0ngPassw0rd!@#');

        $response = $this->requestJson(
            method: 'GET',
            uri: '/v1/user/dashboard?periodDays=7',
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $key = $beginDate->format('Y-m-d');
        self::assertSame(5, $payload['threatCountByDate'][$key] ?? null);
    }

    public function testDashboardTopMetricsContainExpectedValues(): void
    {
        $user = $this->createVerifiedUser('dash6@example.com', 'Str0ngPassw0rd!@#');

        $domain = new Domain($user, 'example.com');
        $domain->setLastChecked(new \DateTimeImmutable());
        $this->entityManager->persist($domain);

        $email = new Email();
        $email
            ->setCreatedAt(new \DateTimeImmutable('-1 day'))
            ->setFromAddress('reporter@example.com')
            ->setToAddress('inbound@example.com')
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
            ->setRawXML('<xml/>')
            ->setIsProcessed(true)
            ->setBeginDate(new \DateTimeImmutable('-2 days'))
            ->setEndDate(new \DateTimeImmutable('-1 day'))
            ->setDomain('example.com')
            ->setReportingOrganization('Example Reporter')
            ->setSumCount(10)
            ->setDmarcCompliance(1.0)
            ->setSpfCompliance(1.0)
            ->setDkimCompliance(1.0)
        ;

        $ipInfo = (new IpInfo())
            ->setOrgAbuseEmail('abuse@example.net')
            ->setOrgCountry('HU')
            ->setOrgName('Example Org')
            ->setOrgTechEmail('tech@example.net')
        ;

        $report->addRecord(
            $this->createReportRecord(
                report: $report,
                count: 4,
                disposition: DispositionType::QUARANTINE,
                sourceIp: '198.51.100.10',
                sourceIpInfo: $ipInfo,
            )
        );

        $senderIpInfo = (new IpInfo())
            ->setOrgAbuseEmail('sender-abuse@example.net')
            ->setOrgCountry('US')
            ->setOrgName('Sender Org')
            ->setOrgTechEmail('sender-tech@example.net')
        ;

        $report->addRecord(
            $this->createReportRecord(
                report: $report,
                count: 6,
                disposition: DispositionType::NONE,
                sourceIp: '198.51.100.20',
                sourceIpInfo: $senderIpInfo,
            )
        );

        $email->setReport($report);

        $this->entityManager->persist($email);
        $this->entityManager->persist($report);
        $this->entityManager->flush();

        $token = $this->login('dash6@example.com', 'Str0ngPassw0rd!@#');

        $response = $this->requestJson(
            method: 'GET',
            uri: '/v1/user/dashboard?periodDays=7',
            headers: $this->authorizeHeaders($token),
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('198.51.100.10', $payload['topOffenders'][0]['sourceIp'] ?? null);
        self::assertSame(4, $payload['topOffenders'][0]['count'] ?? null);
        self::assertSame('abuse@example.net', $payload['topOffenders'][0]['sourceIpInfo']['orgAbuseEmail'] ?? null);
        self::assertSame('HU', $payload['topOffenders'][0]['sourceIpInfo']['orgCountry'] ?? null);
        self::assertSame('Example Org', $payload['topOffenders'][0]['sourceIpInfo']['orgName'] ?? null);
        self::assertSame('tech@example.net', $payload['topOffenders'][0]['sourceIpInfo']['orgTechEmail'] ?? null);

        self::assertSame('198.51.100.20', $payload['topSenders'][0]['sourceIp'] ?? null);
        self::assertSame(6, $payload['topSenders'][0]['count'] ?? null);
        self::assertSame('sender-abuse@example.net', $payload['topSenders'][0]['sourceIpInfo']['orgAbuseEmail'] ?? null);
        self::assertSame('US', $payload['topSenders'][0]['sourceIpInfo']['orgCountry'] ?? null);
        self::assertSame('Sender Org', $payload['topSenders'][0]['sourceIpInfo']['orgName'] ?? null);
        self::assertSame('sender-tech@example.net', $payload['topSenders'][0]['sourceIpInfo']['orgTechEmail'] ?? null);

        self::assertSame(6, $payload['sourceCountryDistribution'][0]['count'] ?? null);
        self::assertSame('US', $payload['sourceCountryDistribution'][0]['country'] ?? null);
        self::assertSame(4, $payload['sourceCountryDistribution'][1]['count'] ?? null);
        self::assertSame('HU', $payload['sourceCountryDistribution'][1]['country'] ?? null);

        self::assertSame('Example Reporter', $payload['topReportingOrganizations'][0]['reportingOrganization'] ?? null);
        self::assertSame(1, $payload['topReportingOrganizations'][0]['count'] ?? null);
    }

    private function createReportRecord(
        Report $report,
        int $count,
        DispositionType $disposition,
        string $sourceIp = '192.0.2.1',
        ?IpInfo $sourceIpInfo = null,
    ): ReportRecord {
        $record = new ReportRecord();
        $record
            ->setReport($report)
            ->setSourceIp($sourceIp)
            ->setSourceIpInfo($sourceIpInfo)
            ->setCount($count)
            ->setDisposition($disposition)
            ->setDkimAlign(DKIMAlign::PASS)
            ->setSpfAlign(SPFAlign::PASS)
            ->setDkimAuth('pass')
            ->setDkimDomain('example.com')
            ->setDkimSelector('selector')
            ->setSpfAuth(SPFResult::PASS)
            ->setSpfDomain('example.com')
        ;

        $this->entityManager->persist($record);

        return $record;
    }
}
