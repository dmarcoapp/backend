<?php

declare(strict_types=1);

namespace App\Enum\Auth;

enum AuthLogAction: string
{
    case LOGIN_SUCCESS = 'login_success';
    case LOGIN_FAILURE = 'login_failure';
}
