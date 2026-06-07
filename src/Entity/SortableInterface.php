<?php

declare(strict_types=1);

namespace App\Entity;

interface SortableInterface extends EntityInterface
{
    public static function getAvailableSortFields(): array;
}
