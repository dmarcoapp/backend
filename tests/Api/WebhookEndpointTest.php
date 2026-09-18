<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\User\User;
use App\Message\Email\InboundEmail;
use App\Repository\User\UserRepository;
use App\Service\Email\AggregateReportPostboxResolver;
use App\Service\Email\InboundReportEmailWebhookSignatureVerifier;
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
        $timestamp = (string) time();

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
        $timestamp = (string) time();
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
        $timestamp = (string) time();
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
                $container->set(AggregateReportPostboxResolver::class, $this->resolverReturning(new User()));
            },
        );

        self::assertSame(200, $response->getStatusCode());
    }

    public function testWebhookRejectsStaleTimestamp(): void
    {
        $payload = $this->validWebhookPayload();
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) (time() - InboundReportEmailWebhookSignatureVerifier::MAX_CLOCK_SKEW_SECONDS - 60);
        $secret = (string) ($_SERVER['APP_INBOUND_REPORT_EMAIL_WEBHOOK_SECRET'] ?? getenv('APP_INBOUND_REPORT_EMAIL_WEBHOOK_SECRET'));
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        // Correctly signed, so only the age of the timestamp can reject it.
        $response = $this->requestJson(
            method: 'POST',
            uri: '/v1/webhook/inbound_report_email',
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
        $secret = (string) ($_SERVER['APP_INBOUND_REPORT_EMAIL_WEBHOOK_SECRET'] ?? getenv('APP_INBOUND_REPORT_EMAIL_WEBHOOK_SECRET'));
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        // A 404 is what tells the sender to drop the attachment it uploaded,
        // so nothing accumulates in the bucket for addresses nobody owns.
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

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
                $container->set(AggregateReportPostboxResolver::class, $this->resolverReturning(null));
            },
        );

        self::assertSame(404, $response->getStatusCode());
    }

    private function resolverReturning(?User $user): AggregateReportPostboxResolver
    {
        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn($user);

        return new AggregateReportPostboxResolver($userRepository);
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
