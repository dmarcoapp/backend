<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Platform\PlatformSettings;
use App\Message\Email\InboundEmail;
use App\Service\Email\AggregateReportPostboxResolver;
use App\Service\Email\InboundReportEmailWebhookSignatureVerifier;
use App\Service\Email\ReportAttachmentDecoder;
use App\Service\Platform\PlatformSettingsProvider;
use App\Service\Security\SecretBox;
use App\Tests\Support\PlatformSettingsTestFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 *
 * @coversNothing
 */
final class WebhookEndpointTest extends ApiTestCase
{
    private const string SECRET = 'test-gateway-secret';

    private ?PlatformSettings $settings = null;

    // Not a 404, which tells the gateway to discard the report: it keeps the
    // report and tries again instead.
    public function testWebhookAsksTheGatewayToWaitWhileApiIntakeIsOff(): void
    {
        $this->settings = self::settings()->setApiIntakeEnabled(false);

        self::assertSame(503, $this->postSignedWithKnownRecipient($this->validWebhookPayload())->getStatusCode());
    }

    public function testWebhookAsksTheGatewayToWaitWhileTheWebhookIsOff(): void
    {
        $this->settings = self::settings()->setWebhookEnabled(false);

        self::assertSame(503, $this->postSignedWithKnownRecipient($this->validWebhookPayload())->getStatusCode());
    }

    public function testWebhookAsksTheGatewayToWaitWithoutASecret(): void
    {
        $this->settings = self::settings()->setEncryptedWebhookSecret(null);

        self::assertSame(503, $this->postSignedWithKnownRecipient($this->validWebhookPayload())->getStatusCode());
    }

    public function testWebhookRejectsMissingHeaders(): void
    {
        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/intake/webhook',
            payload: $this->validWebhookPayload(),
        );

        self::assertSame(400, $response->getStatusCode());
    }

    public function testWebhookRejectsInvalidSignature(): void
    {
        $timestamp = (string) time();

        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/intake/webhook',
            payload: $this->validWebhookPayload(),
            headers: [
                'x-timestamp' => $timestamp,
                'x-signature' => 'sha256=deadbeef',
            ],
        );

        self::assertSame(401, $response->getStatusCode());
    }

    public function testWebhookRejectsInvalidPayload(): void
    {
        $payload = $this->validWebhookPayload();
        unset($payload['email_id']);

        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $secret = self::SECRET;
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/intake/webhook',
            payload: $payload,
            headers: [
                'x-timestamp' => $timestamp,
                'x-signature' => 'sha256='.$signature,
            ],
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testWebhookAcceptsValidSignature(): void
    {
        $payload = $this->validWebhookPayload();
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $secret = self::SECRET;
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(InboundEmail::class))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message))
        ;

        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/intake/webhook',
            payload: $payload,
            headers: [
                'x-timestamp' => $timestamp,
                'x-signature' => 'sha256='.$signature,
            ],
            configureContainer: function (ContainerInterface $container) use ($messageBus): void {
                $container->set(MessageBusInterface::class, $messageBus);
                $container->set(AggregateReportPostboxResolver::class, $this->resolverFor('inbound'));
            },
        );

        self::assertSame(200, $response->getStatusCode());
    }

    public function testWebhookStillAnswersAtItsPathBefore05(): void
    {
        $payload = $this->validWebhookPayload();
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $secret = self::SECRET;
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(InboundEmail::class))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message))
        ;

        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/webhook/inbound_report_email',
            payload: $payload,
            headers: [
                'x-timestamp' => $timestamp,
                'x-signature' => 'sha256='.$signature,
            ],
            configureContainer: function (ContainerInterface $container) use ($messageBus): void {
                $container->set(MessageBusInterface::class, $messageBus);
                $container->set(AggregateReportPostboxResolver::class, $this->resolverFor('inbound'));
            },
        );

        self::assertSame(200, $response->getStatusCode());
    }

    public function testWebhookRejectsStaleTimestamp(): void
    {
        $payload = $this->validWebhookPayload();
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) (time() - InboundReportEmailWebhookSignatureVerifier::MAX_CLOCK_SKEW_SECONDS - 60);
        $secret = self::SECRET;
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        // Correctly signed, so only the age of the timestamp can reject it.
        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/intake/webhook',
            payload: $payload,
            headers: [
                'x-timestamp' => $timestamp,
                'x-signature' => 'sha256='.$signature,
            ],
        );

        self::assertSame(401, $response->getStatusCode());
    }

    public function testWebhookRejectsUnknownRecipient(): void
    {
        $payload = $this->validWebhookPayload();
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $secret = self::SECRET;
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        // A 404 tells the sender to discard the message instead of trying again.
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/intake/webhook',
            payload: $payload,
            headers: [
                'x-timestamp' => $timestamp,
                'x-signature' => 'sha256='.$signature,
            ],
            configureContainer: function (ContainerInterface $container) use ($messageBus): void {
                $container->set(MessageBusInterface::class, $messageBus);
                $container->set(AggregateReportPostboxResolver::class, $this->resolverFor('someone-else'));
            },
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testWebhookRefusesContentItCannotUnpack(): void
    {
        $payload = $this->validWebhookPayload();
        $payload['attachments'][0]['content'] = base64_encode('not gzip');

        self::assertSame(422, $this->postSignedWithKnownRecipient($payload)->getStatusCode());
    }

    public function testWebhookRefusesAReportOverTheSizeLimit(): void
    {
        $payload = $this->validWebhookPayload();
        $payload['attachments'][0]['content'] = base64_encode(
            (string) gzencode(str_repeat('a', ReportAttachmentDecoder::MAX_XML_BYTES + 1))
        );

        self::assertSame(422, $this->postSignedWithKnownRecipient($payload)->getStatusCode());
    }

    private function postSignedWithKnownRecipient(array $payload): Response
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $secret = self::SECRET;
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        // The report is refused while the sender still holds it, so nothing is
        // queued for a worker that could only fail on it.
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        return $this->requestJson(
            method: 'POST',
            uri: '/v1/intake/webhook',
            payload: $payload,
            headers: [
                'x-timestamp' => $timestamp,
                'x-signature' => 'sha256='.$signature,
            ],
            configureContainer: function (ContainerInterface $container) use ($messageBus): void {
                $container->set(MessageBusInterface::class, $messageBus);
                $container->set(AggregateReportPostboxResolver::class, $this->resolverFor('inbound'));
            },
        );
    }

    /**
     * Every request sees API intake on and the gateway secret set, unless a
     * test says otherwise.
     */
    #[\Override]
    protected function requestJson(
        string $method,
        string $uri,
        ?array $payload = null,
        array $headers = [],
        ?callable $configureContainer = null,
        bool $catch = true,
    ): Response {
        $settings = $this->settings ?? self::settings();

        return parent::requestJson($method, $uri, $payload, $headers, static function (ContainerInterface $container) use ($configureContainer, $settings): void {
            $container->set(PlatformSettingsProvider::class, PlatformSettingsTestFactory::provider($settings));
            if (null !== $configureContainer) {
                $configureContainer($container);
            }
        }, $catch);
    }

    private static function settings(): PlatformSettings
    {
        return new PlatformSettings('inbound')
            ->setApiIntakeEnabled(true)
            ->setWebhookEnabled(true)
            ->setEncryptedWebhookSecret(new SecretBox(PlatformSettingsTestFactory::KEY)->encrypt(self::SECRET, PlatformSettings::WEBHOOK_SECRET_CONTEXT))
        ;
    }

    /**
     * The payloads are addressed to inbound@example.com, so a token of
     * "inbound" makes that the report address.
     */
    private function resolverFor(string $token): AggregateReportPostboxResolver
    {
        return new AggregateReportPostboxResolver(PlatformSettingsTestFactory::provider(new PlatformSettings($token)));
    }

    private function validWebhookPayload(): array
    {
        return [
            'email_id' => 'msg_123',
            'created_at' => '2024-01-01T00:00:00+00:00',
            'from' => 'reporter@example.com',
            'to' => ['inbound@example.com'],
            'message_id' => '<message@example.com>',
            'attachments' => [
                [
                    'filename' => 'report.xml',
                    'content_type' => 'application/xml',
                    'content' => base64_encode((string) gzencode('<?xml version="1.0"?><feedback/>')),
                ],
            ],
        ];
    }
}
