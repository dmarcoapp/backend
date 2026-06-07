<?php

declare(strict_types=1);

namespace App\Enum\Common;

enum SortDirection: string
{
    case ASC = 'asc';
    case DESC = 'desc';
}
