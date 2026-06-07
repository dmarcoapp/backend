<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\User\User;
use App\Repository\User\BlocklistEntryRepository;

final readonly class BlocklistMatcher
{
    public function __construct(
        private BlocklistEntryRepository $blocklistEntryRepository,
    ) {}

    public function isBlocked(User $user, string $fromAddress): bool
    {
        $normalizedAddress = self::normalizeValue($fromAddress);
        if ('' === $normalizedAddress) {
            return false;
        }

        foreach ($this->blocklistEntryRepository->getBlocklistEntriesForUser($user) as $entry) {
            $normalizedPattern = self::normalizeValue($entry->getPattern());
            if ('' === $normalizedPattern) {
                continue;
            }

            $regex = $this->patternToRegex($normalizedPattern);
            if (1 === preg_match($regex, $normalizedAddress)) {
                return true;
            }
        }

        return false;
    }

    public static function normalizeValue(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    private function patternToRegex(string $pattern): string
    {
        $escaped = preg_quote($pattern, '~');
        $regexBody = str_replace('\*', '.*', $escaped);

        return '~^'.$regexBody.'$~';
    }
}
