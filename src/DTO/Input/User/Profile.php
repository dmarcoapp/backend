<?php

declare(strict_types=1);

namespace App\DTO\Input\User;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class Profile
{
    public function __construct(
        #[Assert\Length(min: 1, max: 255)]
        public ?string $name = null,
        #[Assert\Length(min: 8, max: 255)]
        #[Assert\PasswordStrength(minScore: Assert\PasswordStrength::STRENGTH_MEDIUM)]
        #[Assert\NotCompromisedPassword]
        #[\SensitiveParameter]
        public ?string $password = null,
    ) {}
}
