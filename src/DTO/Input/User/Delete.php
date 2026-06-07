<?php

declare(strict_types=1);

namespace App\DTO\Input\User;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class Delete
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        #[Assert\Email]
        public string $email,
    ) {}
}
