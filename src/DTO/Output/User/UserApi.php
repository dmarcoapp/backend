<?php

declare(strict_types=1);

namespace App\DTO\Output\User;

use Symfony\Component\Uid\Uuid;

final class UserApi
{
    public Uuid $id;
    public ?string $email;
    public ?string $name;
    public ?string $sharedAggregatePostboxAddress;

    public function __construct(Uuid $id)
    {
        $this->id = $id;
    }
}
