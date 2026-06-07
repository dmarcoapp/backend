<?php

declare(strict_types=1);

namespace App\Helper;

final readonly class Base32Codec
{
    private const string ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function encode(string $binary): string
    {
        if ('' === $binary) {
            return '';
        }

        $bits = '';
        $length = strlen($binary);
        for ($i = 0; $i < $length; ++$i) {
            $bits .= str_pad(decbin(ord($binary[$i])), 8, '0', STR_PAD_LEFT);
        }

        $output = '';
        $bitsLength = strlen($bits);
        for ($i = 0; $i < $bitsLength; $i += 5) {
            $chunk = substr($bits, $i, 5);
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }
            $index = (int) bindec($chunk);
            $output .= self::ALPHABET[$index];
        }

        return $output;
    }

    public static function decode(string $base32): string
    {
        $normalized = strtoupper($base32);
        $normalized = preg_replace('/[^A-Z2-7]/', '', $normalized) ?? '';
        if ('' === $normalized) {
            return '';
        }

        $bits = '';
        $length = strlen($normalized);
        for ($i = 0; $i < $length; ++$i) {
            $char = $normalized[$i];
            $index = (int) strpos(self::ALPHABET, $char);
            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        $output = '';
        $bitsLength = strlen($bits);
        for ($i = 0; $i + 8 <= $bitsLength; $i += 8) {
            $byte = substr($bits, $i, 8);
            $output .= chr(bindec($byte));
        }

        return $output;
    }
}
