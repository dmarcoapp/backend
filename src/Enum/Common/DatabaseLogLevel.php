<?php

declare(strict_types=1);

namespace App\Enum\Common;

enum DatabaseLogLevel: string
{
    case DEBUG = 'debug';
    case INFO = 'info';
    case ERROR = 'error';
}
