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
        $processor = new ReportXMLProcessor(new NullLogger(), $this->schemaSource());

        self::assertFalse($processor->validate('<feedback>'));
    }

    public function testValidateRejectsInvalidSourceIp(): void
    {
        $processor = new ReportXMLProcessor(new NullLogger(), $this->schemaSource());

        $invalidXml = $this->validXml('999.999.999.999');

        self::assertFalse($processor->validate($invalidXml));
    }

    public function testValidateAcceptsXmlWithoutNamespaceAndVendorSpecificNodes(): void
    {
        $processor = new ReportXMLProcessor(new NullLogger(), $this->schemaSource());

        self::assertTrue($processor->validate($this->validXmlWithVendorExtras()));
    }

    public function testDeserializeReturnsFeedbackDto(): void
    {
        $processor = new ReportXMLProcessor(new NullLogger(), $this->schemaSource());
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

        $processor = new ReportXMLProcessor($logger, $this->schemaSource());
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

    private function schemaSource(): string
    {
        $path = \dirname(__DIR__, 3).'/config/dmarc/rua.xsd';

        return (string) file_get_contents($path);
    }

    private function validXml(string $sourceIp = '192.0.2.1'): string
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
      <dkim>
        <domain>example.com</domain>
        <selector>selector</selector>
        <result>pass</result>
      </dkim>
      <spf>
        <domain>example.com</domain>
        <result>pass</result>
      </spf>
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
}
