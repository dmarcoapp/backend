<?php

declare(strict_types=1);

namespace App\DTO\Input\User;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

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
        #[Assert\Length(max: 255)]
        #[Assert\Callback([self::class, 'validateCurrentPassword'])]
        #[\SensitiveParameter]
        public ?string $currentPassword = null,
    ) {}

    public static function validateCurrentPassword(mixed $value, ExecutionContextInterface $context, mixed $payload): void
    {
        $profile = $context->getObject();

        if (!$profile instanceof self || null === $profile->password) {
            return;
        }

        if (is_string($value) && '' !== trim($value)) {
            return;
        }

        $context->buildViolation('The current password is required to set a new one.')
            ->addViolation()
        ;
    }
}
