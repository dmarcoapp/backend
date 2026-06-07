<?php

declare(strict_types=1);

namespace App\DTO\Input\User;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class TwoFactorAppEnable
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 6, max: 6)]
        #[Assert\Regex('/^\d{6}$/')]
        public string $code,
    ) {}
}
