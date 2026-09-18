<?php

declare(strict_types=1);

namespace App\Service\Email;

final readonly class InboundReportEmailWebhookSignatureVerifier
{
    /**
     * How far the sender's clock may differ from ours. The signature covers the
     * timestamp, so without this bound a captured request stays replayable for
     * good. Matches the tolerance the sender's own development receiver applies.
     */
    public const int MAX_CLOCK_SKEW_SECONDS = 900;

    public static function verify(
        string $bodyString,
        string $timestamp,
        string $signatureHeader,
        string $secret,
        ?int $now = null,
    ): bool {
        if ('' === trim($secret)) {
            throw new \LogicException('APP_INBOUND_REPORT_EMAIL_WEBHOOK_SECRET must not be empty.');
        }

        if (!self::isFresh($timestamp, $now ?? time())) {
            return false;
        }

        if (!str_contains($signatureHeader, '=')) {
            return false;
        }

        [$algo, $receivedSignature] = explode('=', $signatureHeader, 2);

        $allowedAlgos = ['sha256'];

        if (!in_array($algo, $allowedAlgos, true)) {
            return false;
        }

        $signedPayload = $timestamp.'.'.$bodyString;

        $expectedSignature = hash_hmac($algo, $signedPayload, $secret);

        return hash_equals($expectedSignature, $receivedSignature);
    }

    private static function isFresh(string $timestamp, int $now): bool
    {
        $trimmed = trim($timestamp);

        if ('' === $trimmed || 1 !== preg_match('/^\d{1,19}$/', $trimmed)) {
            return false;
        }

        return abs($now - (int) $trimmed) <= self::MAX_CLOCK_SKEW_SECONDS;
    }
}
