<?php

declare(strict_types=1);

namespace App\Enum\User;

enum TwoFactorMethod: string
{
    case EMAIL = 'email';
    case APP = 'app';
}
