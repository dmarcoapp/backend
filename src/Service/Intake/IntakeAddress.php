<?php

declare(strict_types=1);

namespace App\Service\Intake;

final readonly class IntakeAddress
{
    public const string GATEWAY = 'gateway';
    public const string MAILBOX = 'mailbox';

    public function __construct(
        public string $address,
        public string $kind,
        public string $label,
    ) {}
}
