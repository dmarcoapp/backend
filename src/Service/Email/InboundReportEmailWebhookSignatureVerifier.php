<?php

declare(strict_types=1);

namespace App\Service\Email;

final readonly class InboundReportEmailWebhookSignatureVerifier
{
    public static function verify(
        string $bodyString,
        string $timestamp,
        string $signatureHeader,
        string $secret
    ): bool {
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
}
