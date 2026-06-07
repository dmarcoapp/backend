<?php

declare(strict_types=1);

namespace App\Entity;

interface FilterableInterface extends EntityInterface
{
    public static function getAvailableFilterFields(): array;
}
