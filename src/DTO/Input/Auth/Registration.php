<?php

declare(strict_types=1);

namespace App\DTO\Input\Auth;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class Registration
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        #[Assert\Email]
        public string $email,
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $name,
        #[Assert\NotBlank]
        #[Assert\Length(min: 8, max: 255)]
        #[Assert\PasswordStrength(minScore: Assert\PasswordStrength::STRENGTH_MEDIUM)]
        #[Assert\NotCompromisedPassword]
        #[\SensitiveParameter]
        public string $password,
    ) {}
}
