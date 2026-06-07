<?php

declare(strict_types=1);

namespace App\DTO\Input\Auth;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RequestVerificationResend
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        #[Assert\Email]
        public string $email,
    ) {}
}
