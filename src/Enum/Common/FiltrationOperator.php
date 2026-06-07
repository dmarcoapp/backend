<?php

declare(strict_types=1);

namespace App\Enum\Common;

enum FiltrationOperator: string
{
    case EQUALS = 'eq';
    case NOT_EQUALS = 'neq';
    case CONTAINS = 'contains';
    case NOT_CONTAINS = 'not_contains';
    case IN = 'in';
    case NOT_IN = 'not_in';
    case LOWER_THAN = 'lt';
    case LOWER_EQUALS = 'lte';
    case GREATER_THAN = 'gt';
    case GREATER_EQUALS = 'gte';
}
