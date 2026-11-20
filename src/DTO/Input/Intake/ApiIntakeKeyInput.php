<?php

declare(strict_types=1);

namespace App\DTO\Input\Intake;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ApiIntakeKeyInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        #[Assert\Regex(pattern: '/^[^\x00-\x1F\x7F]*$/u', message: 'Remove the control characters.')]
        public ?string $name = null,
    ) {}
}
