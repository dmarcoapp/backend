<?php

declare(strict_types=1);

namespace App\Tests\Service\DMARC;

use App\Service\DMARC\ReportXMLProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * @internal
 */
#[CoversClass(ReportXMLProcessor::class)]
final class ReportXMLProcessorTest extends TestCase
{
    public function testValidateRejectsInvalidXml(): void
    {
        $processor = new ReportXMLProcessor(new NullLogger(), $this->schemaSource(), $this->rfc9990SchemaSource());

        self::assertFalse($processor->validate('<feedback>'));
    }

    public function testValidateRejectsInvalidSourceIp(): void
    {
        $processor = new ReportXMLProcessor(new NullLogger(), $this->schemaSource(), $this->rfc9990SchemaSource());

        $invalidXml = $this->validXml('999.999.999.999');

        self::assertFalse($processor->validate($invalidXml));
    }

    public function testValidateAcceptsXmlWithoutNamespaceAndVendorSpecificNodes(): void
    {
        $processor = new ReportXMLProcessor(new NullLogger(), $this->schemaSource(), $this->rfc9990SchemaSource());

        self::assertTrue($processor->validate($this->validXmlWithVendorExtras()));
    }

    public function testValidateAcceptsSpfBeforeDkim(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        $processor = new ReportXMLProcessor($logger, $this->schemaSource(), $this->rfc9990SchemaSource());

        self::assertTrue($processor->validate($this->xmlWithAuthResults(<<<'XML'
<spf>
  <domain>example.com</domain>
  <result>pass</result>
</spf>
<dkim>
  <domain>example.com</domain>
  <selector>selector</selector>
  <result>pass</result>
</dkim>
XML)));
    }

    public function testValidateAcceptsDkimResultsSpreadAroundSpf(): void
    {
        $processor = new ReportXMLProcessor(new NullLogger(), $this->schemaSource(), $this->rfc9990SchemaSource());

        self::assertTrue($processor->validate($this->xmlWithAuthResults(<<<'XML'
<dkim>
  <domain>example.com</domain>
  <result>pass</result>
</dkim>
<spf>
  <domain>example.com</domain>
  <result>pass</result>
</spf>
<dkim>
  <domain>other.example</domain>
  <result>fail</result>
</dkim>
<spf>
  <domain>other.example</domain>
  <result>fail</result>
</spf>
XML)));
    }

    public function testValidateStillRejectsAuthResultsWithoutSpf(): void
    {
        $processor = new ReportXMLProcessor(new NullLogger(), $this->schemaSource(), $this->rfc9990SchemaSource());

        self::assertFalse($processor->validate($this->xmlWithAuthResults(<<<'XML'
<dkim>
  <domain>example.com</domain>
  <result>pass</result>
</dkim>
XML)));
    }

    public function testValidateStillRejectsUnknownElementInAuthResults(): void
    {
        $processor = new ReportXMLProcessor(new NullLogger(), $this->schemaSource(), $this->rfc9990SchemaSource());

        self::assertFalse($processor->validate($this->xmlWithAuthResults(<<<'XML'
<spf>
  <domain>example.com</domain>
  <result>pass</result>
</spf>
<arc>
  <result>pass</result>
</arc>
<dkim>
  <domain>example.com</domain>
  <result>pass</result>
</dkim>
XML)));
    }

    public function testDeserializeSpfBeforeDkimMatchesCorrectOrder(): void
    {
        $processor = new ReportXMLProcessor(new NullLogger(), $this->schemaSource(), $this->rfc9990SchemaSource());

        $spfFirst = $this->xmlWithAuthResults(<<<'XML'
<spf>
  <domain>example.com</domain>
  <result>pass</result>
</spf>
<dkim>
  <domain>example.com</domain>
  <selector>selector</selector>
  <result>pass</result>
</dkim>
XML);

        self::assertEquals(
            $processor->deserialize($this->validXml())->getRecord(),
            $processor->deserialize($spfFirst)->getRecord(),
        );
    }

    public function testDeserializeReturnsFeedbackDto(): void
    {
        $processor = new ReportXMLProcessor(new NullLogger(), $this->schemaSource(), $this->rfc9990SchemaSource());
        $feedback = $processor->deserialize($this->validXml());

        self::assertSame('Example', $feedback->getReportMetadata()->getOrgName());
        self::assertSame('org@example.com', $feedback->getReportMetadata()->getEmail());
        self::assertSame('example.com', $feedback->getPolicyPublished()->getDomain());
        self::assertSame('192.0.2.1', $feedback->getRecord()[0]->getRow()->getSourceIp());
    }

    public function testValidateReturnsFalseAndLogsWhenSchemaValidationFails(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with('XML schema validation failed.', self::isArray());

        $processor = new ReportXMLProcessor($logger, $this->schemaSource(), $this->rfc9990SchemaSource());
        $invalidBySchema = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<feedback>
  <policy_published>
    <domain>example.com</domain>
    <adkim>r</adkim>
    <aspf>r</aspf>
    <p>none</p>
    <sp>none</sp>
    <pct>100</pct>
  </policy_published>
</feedback>
XML;

        self::assertFalse($processor->validate($invalidBySchema));
    }

    public function testValidateAcceptsRfc9990Report(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        $processor = new ReportXMLProcessor($logger, $this->schemaSource(), $this->rfc9990SchemaSource());

        self::assertTrue($processor->validate($this->rfc9990Xml()));
    }

    public function testValidateAcceptsRfc9990RecordWithoutSpf(): void
    {
        $processor = new ReportXMLProcessor(new NullLogger(), $this->schemaSource(), $this->rfc9990SchemaSource());

        self::assertTrue($processor->validate($this->rfc9990Xml(<<<'XML'
<dkim>
  <domain>example.com</domain>
  <selector>abc123</selector>
  <result>pass</result>
</dkim>
XML)));
    }

    public function testValidateAcceptsRfc9990SpfBeforeDkim(): void
    {
        $processor = new ReportXMLProcessor(new NullLogger(), $this->schemaSource(), $this->rfc9990SchemaSource());

        self::assertTrue($processor->validate($this->rfc9990Xml(<<<'XML'
<spf>
  <domain>example.com</domain>
  <result>pass</result>
</spf>
<dkim>
  <domain>example.com</domain>
  <selector>abc123</selector>
  <result>pass</result>
</dkim>
XML)));
    }

    public function testValidateRejectsRfc9990ReportWithTwoSpfResults(): void
    {
        $processor = new ReportXMLProcessor(new NullLogger(), $this->schemaSource(), $this->rfc9990SchemaSource());

        self::assertFalse($processor->validate($this->rfc9990Xml(<<<'XML'
<spf>
  <domain>example.com</domain>
  <result>pass</result>
</spf>
<spf>
  <domain>other.example</domain>
  <result>fail</result>
</spf>
XML)));
    }

    public function testValidateAcceptsRfc9990DkimResultWithoutSelector(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        $processor = new ReportXMLProcessor($logger, $this->schemaSource(), $this->rfc9990SchemaSource());
        $xml = $this->rfc9990Xml(<<<'XML'
<dkim>
  <domain>example.com</domain>
  <result>pass</result>
</dkim>
<dkim>
  <domain>other.example</domain>
  <selector>abc123</selector>
  <result>fail</result>
</dkim>
XML);

        self::assertTrue($processor->validate($xml));

        $dkim = $processor->deserialize($xml)->getRecord()[0]->getAuthResults()->getDkim() ?? [];
        self::assertCount(2, $dkim);
        self::assertNull($dkim[0]->getSelector());
        self::assertSame('abc123', $dkim[1]->getSelector());
    }

    public function testValidateAcceptsPctInRfc9990Report(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        $processor = new ReportXMLProcessor($logger, $this->schemaSource(), $this->rfc9990SchemaSource());

        $withPct = str_replace('<np>none</np>', '<np>none</np><pct>100</pct>', $this->rfc9990Xml());

        self::assertTrue($processor->validate($withPct));
    }

    public function testValidateStillRejectsUnknownElementInRfc9990PolicyPublished(): void
    {
        $processor = new ReportXMLProcessor(new NullLogger(), $this->schemaSource(), $this->rfc9990SchemaSource());

        $withRf = str_replace('<np>none</np>', '<np>none</np><rf>afrf</rf>', $this->rfc9990Xml());

        self::assertFalse($processor->validate($withRf));
    }

    public function testDeserializeRfc9990Report(): void
    {
        $processor = new ReportXMLProcessor(new NullLogger(), $this->schemaSource(), $this->rfc9990SchemaSource());
        $feedback = $processor->deserialize($this->rfc9990Xml());

        $metadata = $feedback->getReportMetadata();
        self::assertSame('Sample Reporter', $metadata->getOrgName());
        self::assertSame('https://example-reporter.com/dmarc', $metadata->getExtraContactInfo());
        self::assertSame('3v98abbp8ya9n3va8yr8oa3ya', $metadata->getReportId());
        self::assertSame(302832000, $metadata->getDateRange()->getBegin());

        $policy = $feedback->getPolicyPublished();
        self::assertSame('example.com', $policy->getDomain());
        self::assertSame('quarantine', $policy->getP());
        self::assertSame('none', $policy->getNp());
        self::assertNull($policy->getAdkim());
        self::assertNull($policy->getPct());

        $record = $feedback->getRecord()[0];
        self::assertSame('192.0.2.123', $record->getRow()->getSourceIp());
        self::assertSame(123, $record->getRow()->getCount());
        self::assertSame('pass', $record->getRow()->getPolicyEvaluated()->getDisposition());
        self::assertSame('example.com', $record->getIdentifiers()->getHeaderFrom());

        $dkim = $record->getAuthResults()->getDkim() ?? [];
        self::assertCount(1, $dkim);
        self::assertSame('abc123', $dkim[0]->getSelector());
        self::assertSame('pass', $dkim[0]->getResult());

        $spf = $record->getAuthResults()->getSpf();
        self::assertCount(1, $spf);
        self::assertSame('example.com', $spf[0]->getDomain());
        self::assertSame('fail', $spf[0]->getResult());
    }

    public function testDeserializeRfc9990RecordWithoutSpf(): void
    {
        $processor = new ReportXMLProcessor(new NullLogger(), $this->schemaSource(), $this->rfc9990SchemaSource());

        $feedback = $processor->deserialize($this->rfc9990Xml(<<<'XML'
<dkim>
  <domain>example.com</domain>
  <selector>abc123</selector>
  <result>pass</result>
</dkim>
XML));

        self::assertSame([], $feedback->getRecord()[0]->getAuthResults()->getSpf());
    }

    private function schemaSource(): string
    {
        $path = \dirname(__DIR__, 3).'/config/dmarc/rua.xsd';

        return (string) file_get_contents($path);
    }

    private function rfc9990SchemaSource(): string
    {
        $path = \dirname(__DIR__, 3).'/config/dmarc/rfc9990.xsd';

        return (string) file_get_contents($path);
    }

    private function validXml(string $sourceIp = '192.0.2.1'): string
    {
        return $this->xmlWithAuthResults(<<<'XML'
<dkim>
  <domain>example.com</domain>
  <selector>selector</selector>
  <result>pass</result>
</dkim>
<spf>
  <domain>example.com</domain>
  <result>pass</result>
</spf>
XML, $sourceIp);
    }

    private function xmlWithAuthResults(string $authResults, string $sourceIp = '192.0.2.1'): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<feedback>
  <report_metadata>
    <org_name>Example</org_name>
    <email>org@example.com</email>
    <report_id>test-report</report_id>
    <date_range>
      <begin>1700000000</begin>
      <end>1700086400</end>
    </date_range>
  </report_metadata>
  <policy_published>
    <domain>example.com</domain>
    <adkim>r</adkim>
    <aspf>r</aspf>
    <p>none</p>
    <sp>none</sp>
    <pct>100</pct>
  </policy_published>
  <record>
    <row>
      <source_ip>{$sourceIp}</source_ip>
      <count>1</count>
      <policy_evaluated>
        <disposition>none</disposition>
        <dkim>fail</dkim>
        <spf>fail</spf>
      </policy_evaluated>
    </row>
    <identifiers>
      <header_from>example.com</header_from>
    </identifiers>
    <auth_results>
{$authResults}
    </auth_results>
  </record>
</feedback>
XML;
    }

    private function validXmlWithVendorExtras(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<feedback>
  <version>2.0</version>
  <report_metadata>
    <org_name>Example</org_name>
    <email>org@example.com</email>
    <report_id>test-report</report_id>
    <date_range>
      <begin>1700000000</begin>
      <end>1700086400</end>
    </date_range>
  </report_metadata>
  <policy_published>
    <domain>example.com</domain>
    <adkim>r</adkim>
    <aspf>r</aspf>
    <p>none</p>
    <sp>none</sp>
    <pct>100</pct>
    <fo>1</fo>
  </policy_published>
  <record>
    <row>
      <source_ip>192.0.2.1</source_ip>
      <count>1</count>
      <policy_evaluated>
        <disposition>none</disposition>
        <dkim>fail</dkim>
        <spf>fail</spf>
      </policy_evaluated>
    </row>
    <identifiers>
      <header_from>example.com</header_from>
      <envelope_from>bounce@example.com</envelope_from>
    </identifiers>
    <auth_results>
      <dkim>
        <domain>example.com</domain>
        <selector>selector</selector>
        <result>pass</result>
      </dkim>
      <spf>
        <domain>example.com</domain>
        <result>pass</result>
        <scope>mfrom</scope>
      </spf>
    </auth_results>
  </record>
</feedback>
XML;
    }

    /**
     * The sample report from RFC 9990, Appendix B, with the optional
     * elements that RFC 7489 does not have.
     */
    private function rfc9990Xml(?string $authResults = null): string
    {
        $authResults ??= <<<'XML'
<dkim>
  <domain>example.com</domain>
  <result>pass</result>
  <selector>abc123</selector>
  <human_result lang="en">signature verified</human_result>
</dkim>
<spf>
  <domain>example.com</domain>
  <scope>mfrom</scope>
  <result>fail</result>
  <human_result>no matching mechanism</human_result>
</spf>
XML;

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<feedback xmlns="urn:ietf:params:xml:ns:dmarc-2.0">
  <version>1.0</version>
  <report_metadata>
    <org_name>Sample Reporter</org_name>
    <email>report_sender@example-reporter.com</email>
    <extra_contact_info lang="en">https://example-reporter.com/dmarc</extra_contact_info>
    <report_id>3v98abbp8ya9n3va8yr8oa3ya</report_id>
    <date_range>
      <begin>302832000</begin>
      <end>302918399</end>
    </date_range>
    <generator>Example DMARC Aggregate Reporter v1.2</generator>
  </report_metadata>
  <policy_published>
    <domain>example.com</domain>
    <p>quarantine</p>
    <sp>none</sp>
    <np>none</np>
    <testing>n</testing>
    <discovery_method>treewalk</discovery_method>
  </policy_published>
  <extension>
    <note xmlns="https://example-reporter.com/dmarc-extension">sample</note>
  </extension>
  <record>
    <row>
      <source_ip>192.0.2.123</source_ip>
      <count>123</count>
      <policy_evaluated>
        <disposition>pass</disposition>
        <dkim>pass</dkim>
        <spf>fail</spf>
        <reason>
          <type>policy_test_mode</type>
          <comment lang="en">t=y in the DMARC record</comment>
        </reason>
      </policy_evaluated>
    </row>
    <identifiers>
      <envelope_from>example.com</envelope_from>
      <header_from>example.com</header_from>
    </identifiers>
    <auth_results>
{$authResults}
    </auth_results>
  </record>
</feedback>
XML;
    }
}
