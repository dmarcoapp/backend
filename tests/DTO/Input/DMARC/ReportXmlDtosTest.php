<?php

declare(strict_types=1);

namespace App\Tests\DTO\Input\DMARC;

use App\DTO\Input\DMARC\ReportXML\AuthResults;
use App\DTO\Input\DMARC\ReportXML\DateRange;
use App\DTO\Input\DMARC\ReportXML\Dkim;
use App\DTO\Input\DMARC\ReportXML\Identifiers;
use App\DTO\Input\DMARC\ReportXML\PolicyEvaluated;
use App\DTO\Input\DMARC\ReportXML\PolicyPublished;
use App\DTO\Input\DMARC\ReportXML\Record;
use App\DTO\Input\DMARC\ReportXML\ReportMetadata;
use App\DTO\Input\DMARC\ReportXML\Row;
use App\DTO\Input\DMARC\ReportXML\Spf;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AuthResults::class)]
#[CoversClass(DateRange::class)]
#[CoversClass(Dkim::class)]
#[CoversClass(Identifiers::class)]
#[CoversClass(PolicyEvaluated::class)]
#[CoversClass(PolicyPublished::class)]
#[CoversClass(Record::class)]
#[CoversClass(ReportMetadata::class)]
#[CoversClass(Row::class)]
#[CoversClass(Spf::class)]
final class ReportXmlDtosTest extends TestCase
{
    public function testDkimAndSpfExposeFields(): void
    {
        $dkim = new Dkim('example.com', 'pass', 'selector');
        $spf = new Spf('example.com', 'pass');

        self::assertSame('example.com', $dkim->getDomain());
        self::assertSame('pass', $dkim->getResult());
        self::assertSame('selector', $dkim->getSelector());

        self::assertSame('example.com', $spf->getDomain());
        self::assertSame('pass', $spf->getResult());
    }

    public function testAuthResultsExposeArrays(): void
    {
        $dkim = new Dkim('example.com', 'pass', null);
        $spf = new Spf('example.com', 'pass');

        $auth = new AuthResults([$dkim], [$spf]);

        self::assertCount(1, $auth->getDkim() ?? []);
        self::assertCount(1, $auth->getSpf());
    }

    public function testDateRangeExposeBounds(): void
    {
        $range = new DateRange(10, 20);

        self::assertSame(10, $range->getBegin());
        self::assertSame(20, $range->getEnd());
    }

    public function testReportMetadataAndIdentifiersExposeFields(): void
    {
        $range = new DateRange(10, 20);
        $metadata = new ReportMetadata('Org', 'org@example.com', 'extra', 'report-1', $range);
        $identifiers = new Identifiers('example.com');

        self::assertSame('Org', $metadata->getOrgName());
        self::assertSame('org@example.com', $metadata->getEmail());
        self::assertSame('report-1', $metadata->getReportId());
        self::assertSame($range, $metadata->getDateRange());
        self::assertSame('extra', $metadata->getExtraContactInfo());
        self::assertSame('example.com', $identifiers->getHeaderFrom());
    }

    public function testPolicyEvaluatedAndRowExposeFields(): void
    {
        $policy = new PolicyEvaluated('none', 'pass', 'fail');
        $row = new Row('192.0.2.1', 5, $policy);

        self::assertSame('none', $policy->getDisposition());
        self::assertSame('pass', $policy->getDkim());
        self::assertSame('fail', $policy->getSpf());

        self::assertSame('192.0.2.1', $row->getSourceIp());
        self::assertSame(5, $row->getCount());
        self::assertSame($policy, $row->getPolicyEvaluated());
    }

    public function testPolicyPublishedExposeFields(): void
    {
        $policy = new PolicyPublished('example.com', 'r', 's', 'none', 'quarantine', 100, 'reject');

        self::assertSame('example.com', $policy->getDomain());
        self::assertSame('r', $policy->getAdkim());
        self::assertSame('s', $policy->getAspf());
        self::assertSame('none', $policy->getP());
        self::assertSame('quarantine', $policy->getSp());
        self::assertSame(100, $policy->getPct());
        self::assertSame('reject', $policy->getNp());
    }

    public function testRecordExposeFields(): void
    {
        $policy = new PolicyEvaluated('none', 'pass', 'pass');
        $row = new Row('192.0.2.1', 1, $policy);
        $identifiers = new Identifiers('example.com');
        $authResults = new AuthResults(null, [new Spf('example.com', 'pass')]);

        $record = new Record($row, $identifiers, $authResults);

        self::assertSame($row, $record->getRow());
        self::assertSame($identifiers, $record->getIdentifiers());
        self::assertSame($authResults, $record->getAuthResults());
    }
}
