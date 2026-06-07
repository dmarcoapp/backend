<?php

declare(strict_types=1);

namespace App\DTO\Output\User;

use Symfony\Component\Uid\Uuid;

final class BlocklistEntryApi
{
    public Uuid $id;
    public ?string $pattern = null;
    public ?\DateTimeInterface $createdAt = null;

    public function __construct(Uuid $id)
    {
        $this->id = $id;
    }
}
