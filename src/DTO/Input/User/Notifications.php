<?php

declare(strict_types=1);

namespace App\DTO\Input\User;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class Notifications
{
    public function __construct(
        #[Assert\Type(type: 'bool')]
        public ?bool $unusualNewLoginNotificationEnabled = null,
        #[Assert\Type(type: 'bool')]
        public ?bool $weeklyOverviewNotificationEnabled = null,
    ) {}
}
