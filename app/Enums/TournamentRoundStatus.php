<?php

declare(strict_types=1);

namespace App\Enums;

enum TournamentRoundStatus: string
{
    case PENDING = 'PENDING';
    case ACTIVE = 'ACTIVE';
    case COMPLETED = 'COMPLETED';
}
