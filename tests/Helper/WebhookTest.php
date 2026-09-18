<?php

declare(strict_types=1);

namespace App\Tests\Helper;

use App\Service\Email\InboundReportEmailWebhookSignatureVerifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class WebhookTest extends TestCase
{
    private const int NOW = 1700000000;

    public function testVerifySignatureAcceptsValidSignature(): void
    {
        $body = '{"status":"ok"}';
        $timestamp = '1700000000';
        $secret = 'test-secret';

        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);
        $header = 'sha256='.$signature;

        self::assertTrue(InboundReportEmailWebhookSignatureVerifier::verify($body, $timestamp, $header, $secret, self::NOW));
    }

    public function testVerifySignatureRejectsUnsupportedAlgorithm(): void
    {
        $body = '{}';
        $timestamp = '1700000000';
        $secret = 'test-secret';

        $signature = hash_hmac('sha1', $timestamp.'.'.$body, $secret);
        $header = 'sha1='.$signature;

        self::assertFalse(InboundReportEmailWebhookSignatureVerifier::verify($body, $timestamp, $header, $secret, self::NOW));
    }

    public function testVerifySignatureRejectsMalformedHeader(): void
    {
        self::assertFalse(InboundReportEmailWebhookSignatureVerifier::verify('x', '1700000000', 'sha256', 'secret', self::NOW));
    }

    public function testVerifySignatureRejectsEmptySecret(): void
    {
        $this->expectException(\LogicException::class);

        InboundReportEmailWebhookSignatureVerifier::verify('x', '1700000000', 'sha256=deadbeef', '', self::NOW);
    }

    public function testVerifySignatureRejectsMismatchedSignature(): void
    {
        $header = 'sha256=deadbeef';

        self::assertFalse(InboundReportEmailWebhookSignatureVerifier::verify('x', '1700000000', $header, 'secret', self::NOW));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function timestampProvider(): iterable
    {
        $skew = InboundReportEmailWebhookSignatureVerifier::MAX_CLOCK_SKEW_SECONDS;

        yield 'current' => [(string) self::NOW, true];
        yield 'at the edge of the past' => [(string) (self::NOW - $skew), true];
        yield 'at the edge of the future' => [(string) (self::NOW + $skew), true];
        yield 'replayed from beyond the window' => [(string) (self::NOW - $skew - 1), false];
        yield 'dated beyond the window' => [(string) (self::NOW + $skew + 1), false];
        yield 'not a number' => ['not-a-timestamp', false];
        yield 'empty' => ['', false];
        yield 'negative' => ['-1700000000', false];
    }

    #[DataProvider('timestampProvider')]
    public function testVerifySignatureAcceptsOnlyFreshTimestamps(string $timestamp, bool $expected): void
    {
        $body = '{"status":"ok"}';
        $secret = 'test-secret';
        $header = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        self::assertSame(
            $expected,
            InboundReportEmailWebhookSignatureVerifier::verify($body, $timestamp, $header, $secret, self::NOW),
        );
    }
}
