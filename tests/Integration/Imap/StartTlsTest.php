<?php

declare(strict_types=1);

namespace App\Tests\Integration\Imap;

use App\Entity\Intake\Mailbox;
use App\Enum\Intake\MailboxSecurity;
use App\Service\Intake\MailboxConnector;
use App\Service\Intake\MailboxCredentials;
use App\Service\Security\SecretBox;
use App\Tests\Support\PlatformSettingsTestFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * STARTTLS against a fake server: the password must never travel before TLS
 * is up with a certificate DMARCo trusts. The GreenMail tests cover TLS from
 * the first byte, which leaves this path to these.
 *
 * @internal
 */
#[CoversClass(MailboxConnector::class)]
final class StartTlsTest extends TestCase
{
    private string $certificate = '';

    protected function setUp(): void
    {
        // A certificate nobody trusts, made for this test alone.
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($key);
        $request = openssl_csr_new(['commonName' => '127.0.0.1'], $key);
        self::assertNotFalse($request);
        $certificate = openssl_csr_sign($request, null, $key, 1);
        self::assertNotFalse($certificate);

        openssl_x509_export($certificate, $certificatePem);
        openssl_pkey_export($key, $keyPem);
        $this->certificate = (string) tempnam(sys_get_temp_dir(), 'dmarco-starttls-');
        file_put_contents($this->certificate, $certificatePem.$keyPem);
    }

    protected function tearDown(): void
    {
        @unlink($this->certificate);
    }

    public function testTheCertificateIsCheckedBeforeThePasswordIsSent(): void
    {
        $commands = $this->connectTo('offer');

        self::assertContains('STARTTLS', $commands);
        self::assertContains('HANDSHAKE FAILED', $commands);
        self::assertNotContains('LOGIN', $commands);
        self::assertNotContains('AUTHENTICATE', $commands);
    }

    public function testAServerWithoutStartTlsNeverGetsThePassword(): void
    {
        $commands = $this->connectTo('refuse');

        self::assertContains('STARTTLS', $commands);
        self::assertNotContains('LOGIN', $commands);
        self::assertNotContains('AUTHENTICATE', $commands);
    }

    /**
     * Connects to a fresh fake server, which has to fail, and returns the
     * commands the server received.
     *
     * @return list<string>
     */
    private function connectTo(string $mode): array
    {
        $process = proc_open(
            [PHP_BINARY, __DIR__.'/starttls-server.php', $mode, $this->certificate],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        self::assertIsResource($process);

        try {
            $port = (int) trim((string) fgets($pipes[1]));
            self::assertGreaterThan(0, $port, 'The fake server did not start.');

            $secretBox = new SecretBox(PlatformSettingsTestFactory::KEY);
            $credentials = new MailboxCredentials($secretBox);
            $mailbox = new Mailbox('Fake', '127.0.0.1', MailboxSecurity::STARTTLS, 'user@example.test');
            $mailbox->setPort($port);
            $credentials->setPassword($mailbox, 'the-password');

            $failed = false;
            // The failed handshake warns as well as failing, and the warning is
            // not what this is about.
            set_error_handler(static fn (): bool => true, E_WARNING);

            try {
                new MailboxConnector($credentials)->connect($mailbox);
            } catch (\Throwable) {
                $failed = true;
            } finally {
                restore_error_handler();
            }
            self::assertTrue($failed, 'Signing in should have failed.');

            return array_values(array_filter(array_map('trim', explode("\n", (string) stream_get_contents($pipes[1])))));
        } finally {
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            proc_terminate($process);
            proc_close($process);
        }
    }
}
