<?php

declare(strict_types=1);

namespace App\DTO\Output\Common;

final readonly class PaginationApi
{
    public function __construct(
        public iterable $items,
        public PaginationMetaApi $meta,
    ) {}
}
