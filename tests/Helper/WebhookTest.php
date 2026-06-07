<?php

declare(strict_types=1);

namespace App\Tests\Helper;

use App\Service\Email\InboundReportEmailWebhookSignatureVerifier;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class WebhookTest extends TestCase
{
    public function testVerifySignatureAcceptsValidSignature(): void
    {
        $body = '{"status":"ok"}';
        $timestamp = '1700000000';
        $secret = 'test-secret';

        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);
        $header = 'sha256='.$signature;

        self::assertTrue(InboundReportEmailWebhookSignatureVerifier::verify($body, $timestamp, $header, $secret));
    }

    public function testVerifySignatureRejectsUnsupportedAlgorithm(): void
    {
        $body = '{}';
        $timestamp = '1700000000';
        $secret = 'test-secret';

        $signature = hash_hmac('sha1', $timestamp.'.'.$body, $secret);
        $header = 'sha1='.$signature;

        self::assertFalse(InboundReportEmailWebhookSignatureVerifier::verify($body, $timestamp, $header, $secret));
    }

    public function testVerifySignatureRejectsMalformedHeader(): void
    {
        self::assertFalse(InboundReportEmailWebhookSignatureVerifier::verify('x', '1700000000', 'sha256', 'secret'));
    }

    public function testVerifySignatureRejectsMismatchedSignature(): void
    {
        $header = 'sha256=deadbeef';

        self::assertFalse(InboundReportEmailWebhookSignatureVerifier::verify('x', '1700000000', $header, 'secret'));
    }
}
