<?php

declare(strict_types=1);

namespace App\Tests\Api\Intake;

use App\Entity\Email\Email;
use App\Enum\Email\ReportSource;
use App\Repository\Intake\ApiIntakeKeyRepository;
use App\Repository\Platform\PlatformSettingsRepository;
use App\Service\Intake\ApiIntakeKeys;
use App\Tests\Api\ApiIntegrationTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @internal
 *
 * @coversNothing
 */
final class ReportUploadTest extends ApiIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The report is stored, and processing it, which looks IP addresses up
        // over the network, is not this test's business.
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));
        self::getContainer()->set(MessageBusInterface::class, $bus);

        $this->removeReportData();
        $settings = self::getContainer()->get(PlatformSettingsRepository::class)->get();
        $settings->setApiIntakeEnabled(true)->setUploadEndpointEnabled(true);
        $this->entityManager->flush();
    }

    public function testTakesAReportInEveryFormItArrivesIn(): void
    {
        $token = $this->token();

        $plain = $this->upload($this->report('plain'), $token);
        self::assertSame(202, $plain->getStatusCode(), (string) $plain->getContent());
        self::assertSame('{"status":"accepted"}', $plain->getContent());

        self::assertSame(202, $this->upload((string) gzencode($this->report('gzip')), $token)->getStatusCode());
        self::assertSame(202, $this->upload($this->zip($this->report('zip')), $token)->getStatusCode());

        $this->entityManager->clear();
        $emails = $this->entityManager->getRepository(Email::class)->findBy(['source' => ReportSource::UPLOAD]);
        self::assertCount(3, $emails);
        self::assertNotNull($emails[0]->getApiKey());
    }

    public function testTheSameFileTwiceIsStoredOnce(): void
    {
        $token = $this->token();
        $report = $this->report('twice');

        self::assertSame(202, $this->upload($report, $token)->getStatusCode());

        $again = $this->upload($report, $token);
        self::assertSame(200, $again->getStatusCode());
        self::assertSame('{"status":"already_received"}', $again->getContent());
    }

    public function testRefusesWithoutAValidKey(): void
    {
        $this->token();

        $response = $this->upload($this->report('no-key'), null);
        self::assertSame(401, $response->getStatusCode());
        self::assertSame('Bearer', $response->headers->get('WWW-Authenticate'));

        self::assertSame(401, $this->upload($this->report('bad-key'), 'dmarco_abcdefghijklmnop_'.str_repeat('A', 40))->getStatusCode());
    }

    public function testARevokedKeyNoLongerWorks(): void
    {
        $this->createAccount('revoke-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');
        [$key, $token] = self::getContainer()->get(ApiIntakeKeys::class)->create('Revoked');
        self::getContainer()->get(ApiIntakeKeyRepository::class)->delete($key);

        self::assertSame(401, $this->upload($this->report('revoked'), $token)->getStatusCode());
    }

    public function testIsGoneWhileApiIntakeIsOff(): void
    {
        $token = $this->token();
        $settings = self::getContainer()->get(PlatformSettingsRepository::class)->get();
        $settings->setApiIntakeEnabled(false);
        $this->entityManager->flush();

        self::assertSame(404, $this->upload($this->report('off'), $token)->getStatusCode());
    }

    public function testIsGoneWhileTheUploadEndpointIsOff(): void
    {
        $token = $this->token();
        $settings = self::getContainer()->get(PlatformSettingsRepository::class)->get();
        $settings->setUploadEndpointEnabled(false);
        $this->entityManager->flush();

        self::assertSame(404, $this->upload($this->report('endpoint-off'), $token)->getStatusCode());
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function refusedFileProvider(): iterable
    {
        yield 'not a report file' => ["%PDF-1.7\nnot a report", 422];
        yield 'XML that is not a report' => ['<?xml version="1.0"?><html><body>hello</body></html>', 422];
        yield 'a report with a document type' => ['<?xml version="1.0"?><!DOCTYPE feedback [<!ENTITY x SYSTEM "file:///etc/passwd">]><feedback>&x;</feedback>', 422];
        yield 'a gzip bomb' => [(string) gzencode('<feedback>'.str_repeat(' ', 9 * 1024 * 1024).'</feedback>', 9), 422];
        yield 'larger than the limit' => [str_repeat('<', 10 * 1024 * 1024 + 1), 413];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('refusedFileProvider')]
    public function testRefusesAFileThatIsNoReport(string $content, int $status): void
    {
        $response = $this->upload($content, $this->token());

        self::assertSame($status, $response->getStatusCode(), (string) $response->getContent());
        self::assertArrayHasKey('error', json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testThrottlesBeforeTheKeyIsChecked(): void
    {
        $response = $this->upload($this->report('throttled'), null, function (ContainerInterface $container): void {
            $container->set('limiter.report_intake', $this->mockRejectedLimiterFactory());
        });

        self::assertSame(429, $response->getStatusCode());
    }

    private function token(): string
    {
        $this->createAccount('uploader-'.uniqid('', true).'@example.com', 'Str0ngPassw0rd!@#');

        return self::getContainer()->get(ApiIntakeKeys::class)->create('Uploader')[1];
    }

    private function upload(string $content, ?string $token, ?callable $configureContainer = null): Response
    {
        if (!self::$booted) {
            self::bootKernel();
        }

        $container = self::getContainer();
        if (null !== $configureContainer) {
            $configureContainer($container);
        }

        $request = Request::create('/v1/intake/reports', 'POST', content: $content);
        $request->headers->set('Content-Type', 'application/octet-stream');
        if (null !== $token) {
            $request->headers->set('Authorization', 'Bearer '.$token);
        }

        return self::$kernel->handle($request);
    }

    private function report(string $id): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<feedback>
  <report_metadata>
    <org_name>example.net</org_name>
    <email>noreply@example.net</email>
    <report_id>upload-{$id}</report_id>
    <date_range><begin>1700000000</begin><end>1700086400</end></date_range>
  </report_metadata>
  <policy_published><domain>example.com</domain><adkim>r</adkim><aspf>r</aspf><p>none</p><sp>none</sp><pct>100</pct></policy_published>
  <record>
    <row><source_ip>192.0.2.1</source_ip><count>1</count><policy_evaluated><disposition>none</disposition><dkim>pass</dkim><spf>pass</spf></policy_evaluated></row>
    <identifiers><header_from>example.com</header_from></identifiers>
    <auth_results><dkim><domain>example.com</domain><result>pass</result></dkim><spf><domain>example.com</domain><result>pass</result></spf></auth_results>
  </record>
</feedback>
XML;
    }

    private function zip(string $xml): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'dmarco-test-');
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::OVERWRITE);
        $zip->addFromString('report.xml', $xml);
        $zip->close();
        $content = (string) file_get_contents($path);
        unlink($path);

        return $content;
    }
}
