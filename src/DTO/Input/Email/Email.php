<?php

declare(strict_types=1);

namespace App\DTO\Input\Email;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class Email
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public ?string $email_id = null,
        #[Assert\NotNull]
        public ?\DateTimeImmutable $created_at = null,
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public ?string $from = null,
        #[Assert\Count(min: 1, max: 10)]
        #[Assert\All(constraints: [
            new Assert\NotBlank(),
            new Assert\Email(),
            new Assert\Length(max: 255),
        ])]
        public ?array $to = null,
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public ?string $message_id = null,
        #[Assert\Count(min: 1, max: 10)]
        #[Assert\All(constraints: [
            new Assert\Collection(
                fields: [
                    'id' => new Assert\Required(constraints: [
                        new Assert\NotBlank(),
                        new Assert\Uuid(),
                    ]),
                    'bucket' => new Assert\Required(constraints: [
                        new Assert\NotBlank(),
                        new Assert\Length(max: 255),
                    ]),
                    'key' => new Assert\Required(constraints: [
                        new Assert\NotBlank(),
                        new Assert\Length(max: 255),
                    ]),
                    'filename' => new Assert\Required(constraints: [
                        new Assert\NotBlank(),
                        new Assert\Length(max: 255),
                    ]),
                    'content_type' => new Assert\Required(constraints: [
                        new Assert\NotBlank(),
                        new Assert\Length(max: 255),
                    ]),
                ]
            ),
        ])]
        public ?array $attachments = null,
    ) {}
}
