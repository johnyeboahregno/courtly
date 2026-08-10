<?php

declare(strict_types=1);

namespace App\Enums;

enum MatchStatus: string
{
    case CREATED = 'CREATED';
    case PLAYING = 'PLAYING';
    case COMPLETED = 'COMPLETED';
}
