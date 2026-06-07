<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Message\Email\InboundEmail;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 *
 * @coversNothing
 */
final class WebhookEndpointTest extends ApiTestCase
{
    public function testWebhookRejectsMissingHeaders(): void
    {
        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/webhook/inbound_report_email',
            payload: $this->validWebhookPayload(),
        );

        self::assertSame(400, $response->getStatusCode());
    }

    public function testWebhookRejectsInvalidSignature(): void
    {
        $timestamp = '1700000000';

        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/webhook/inbound_report_email',
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
        $timestamp = '1700000000';
        $secret = (string) ($_SERVER['APP_INBOUND_REPORT_EMAIL_WEBHOOK_SECRET'] ?? getenv('APP_INBOUND_REPORT_EMAIL_WEBHOOK_SECRET'));
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/webhook/inbound_report_email',
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
        $timestamp = '1700000000';
        $secret = (string) ($_SERVER['APP_INBOUND_REPORT_EMAIL_WEBHOOK_SECRET'] ?? getenv('APP_INBOUND_REPORT_EMAIL_WEBHOOK_SECRET'));
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
            },
        );

        self::assertSame(200, $response->getStatusCode());
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
                    'id' => '11111111-1111-4111-8111-111111111111',
                    'bucket' => 'bucket',
                    'key' => 'path/report.xml',
                    'filename' => 'report.xml',
                    'content_type' => 'application/xml',
                ],
            ],
        ];
    }
}
