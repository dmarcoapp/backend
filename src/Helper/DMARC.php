<?php

declare(strict_types=1);

namespace App\Helper;

use App\Enum\DMARC\Domain\ProtectionLevel;

final readonly class DMARC
{
    public static function dmarcProtectionLevel(string $record): ProtectionLevel
    {
        $tags = [
            'p' => 'none',
            'adkim' => 'r',
            'aspf' => 'r',
            'pct' => 100,
            'fo' => '0',
        ];

        foreach (explode(';', $record) as $part) {
            $part = trim($part);
            if (!$part || !str_contains($part, '=')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $part, 2));
            $key = strtolower($key);

            if (array_key_exists($key, $tags)) {
                $tags[$key] = strtolower($value);
            }
        }

        $pct = is_numeric($tags['pct']) ? (int) $tags['pct'] : 100;

        if ('reject' === $tags['p'] && 100 === $pct) { // TODO && $tags['adkim'] === 's' && $tags['aspf'] === 's'
            return ProtectionLevel::STRONG;
        }

        if ('quarantine' === $tags['p'] && $pct >= 50) {
            return ProtectionLevel::MODERATE;
        }

        return ProtectionLevel::WEAK;
    }

    public static function checkConfiguration(string $record, string $email): bool
    {
        $norm = static function (string $s): string {
            $s = trim($s);
            $s = preg_replace('/^mailto:\s*/i', '', $s) ?? '';
            $s = explode('!', $s, 2)[0];  // mailto:a@b!10m
            $s = explode('?', $s, 2)[0];  // mailto:a@b?x=y

            return strtolower(trim($s));
        };

        $email = $norm($email);
        if ('' === $email) {
            return false;
        }

        if (!preg_match('/(?:^|;)\s*rua\s*=\s*([^;]*)/i', $record, $m)) {
            return false;
        }
        $ruaValue = (string) ($m[1] ?? '');

        foreach (preg_split('/\s*,\s*/', trim($ruaValue)) as $item) {
            if (0 !== stripos(ltrim($item), 'mailto:')) {
                continue;
            }
            if ($norm($item) === $email) {
                return true;
            }
        }

        return false;
    }
}
