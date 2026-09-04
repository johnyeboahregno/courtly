<?php

declare(strict_types=1);

namespace App\Enums;

enum SessionType: string
{
    case CASUAL = 'casual';
    case TOURNAMENT = 'tournament';
}
