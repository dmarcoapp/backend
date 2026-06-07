<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\DMARC\ReportXMLProcessor;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

/**
 * @internal
 *
 * @coversNothing
 */
final class ReportXMLProcessorTest extends TestCase
{
    public function testValidateRejectsInvalidXml(): void
    {
        $processor = new ReportXMLProcessor(new TestLogger(), $this->schemaPath());

        self::assertFalse($processor->validate('<not-xml'));
    }

    public function testValidateAcceptsValidXml(): void
    {
        $logger = new TestLogger();
        $processor = new ReportXMLProcessor($logger, $this->schemaPath());

        $result = $processor->validate($this->validXml());

        if (!$result) {
            self::fail(json_encode($logger->messages, JSON_THROW_ON_ERROR));
        }

        self::assertTrue($result);
    }

    public function testValidateRejectsInvalidSourceIp(): void
    {
        $processor = new ReportXMLProcessor(new TestLogger(), $this->schemaPath());

        self::assertFalse($processor->validate($this->validXml('999.999.999.999')));
    }

    public function testValidateAcceptsIpv6(): void
    {
        $processor = new ReportXMLProcessor(new TestLogger(), $this->schemaPath());

        self::assertTrue($processor->validate($this->validXml('2001:db8::1')));
    }

    public function testValidateStripsVendorSpecificElements(): void
    {
        $processor = new ReportXMLProcessor(new TestLogger(), $this->schemaPath());

        self::assertTrue($processor->validate($this->validXmlWithExtras()));
    }

    public function testDeserializeReturnsFeedback(): void
    {
        $processor = new ReportXMLProcessor(new TestLogger(), $this->schemaPath());

        $feedback = $processor->deserialize($this->validXml());

        self::assertSame('example.com', $feedback->getPolicyPublished()->getDomain());
        self::assertSame('org@example.com', $feedback->getReportMetadata()->getEmail());
        self::assertSame(1, $feedback->getRecord()[0]->getRow()->getCount());
    }

    private function schemaPath(): string
    {
        $path = \dirname(__DIR__, 2).'/config/dmarc/rua.xsd';

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

    private function validXmlWithExtras(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<feedback>
  <version>1.0</version>
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
      <envelope_from>mail.example.com</envelope_from>
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

final class TestLogger extends AbstractLogger
{
    public array $messages = [];

    #[\Override]
    public function log($level, $message, array $context = []): void
    {
        $this->messages[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
    }
}
