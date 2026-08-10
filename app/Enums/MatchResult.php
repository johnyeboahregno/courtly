<?php

declare(strict_types=1);

namespace App\Enums;

enum MatchResult: string
{
    case WIN = 'WIN';
    case LOSS = 'LOSS';
}
