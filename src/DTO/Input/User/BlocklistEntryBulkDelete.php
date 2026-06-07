<?php

declare(strict_types=1);

namespace App\DTO\Input\User;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class BlocklistEntryBulkDelete
{
    /**
     * @param list<string> $ids
     */
    public function __construct(
        #[Assert\Count(min: 1)]
        #[Assert\All([
            new Assert\NotBlank(),
            new Assert\Uuid(),
        ])]
        public array $ids = [],
    ) {}
}
