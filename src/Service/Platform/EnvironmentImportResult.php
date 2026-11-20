<?php

declare(strict_types=1);

namespace App\Service\Platform;

final readonly class EnvironmentImportResult
{
    /**
     * @param list<string> $imported taken over from the environment just now
     * @param list<string> $kept     already set in the database, so the environment value is ignored
     * @param list<string> $invalid  set in the environment to something that cannot be taken over
     */
    public function __construct(
        public bool $legacyMode,
        public array $imported = [],
        public array $kept = [],
        public array $invalid = [],
    ) {}

    /**
     * The variables the database answers for now, which can go from .env.
     *
     * @return list<string>
     */
    public function settled(): array
    {
        $settled = [...$this->imported, ...$this->kept];
        sort($settled);

        return $settled;
    }
}
