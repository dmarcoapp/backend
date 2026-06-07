<?php

declare(strict_types=1);

namespace App\Service\User\TwoFactor;

use App\Helper\Base32Codec;

final readonly class TwoFactorTotpService
{
    public function generateCode(string $base32Secret, int $period, int $digits, ?int $timestamp = null): string
    {
        $timestamp ??= time();
        $counter = (int) floor($timestamp / $period);
        $key = Base32Codec::decode($base32Secret);

        $binaryCounter = pack('N*', 0, $counter);
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $binary = substr($hash, $offset, 4);
        $unpacked = unpack('N', $binary);
        $value = ((int) ($unpacked[1] ?? 0)) & 0x7FFFFFFF;

        $modulo = 10 ** $digits;
        $code = (string) ($value % $modulo);

        return str_pad($code, $digits, '0', STR_PAD_LEFT);
    }

    public function verifyCode(string $base32Secret, string $code, int $period, int $digits, int $window = 1): bool
    {
        if (!preg_match('/^\d+$/', $code)) {
            return false;
        }

        $timestamp = time();
        for ($offset = -$window; $offset <= $window; ++$offset) {
            $checkTime = $timestamp + ($offset * $period);
            $expected = $this->generateCode($base32Secret, $period, $digits, $checkTime);
            if (hash_equals($expected, $code)) {
                return true;
            }
        }

        return false;
    }
}
