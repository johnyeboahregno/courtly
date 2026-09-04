<?php

declare(strict_types=1);

namespace App\Enums;

enum TournamentFixtureStatus: string
{
    case PENDING = 'PENDING';
    case PLAYING = 'PLAYING';
    case COMPLETED = 'COMPLETED';
    case BYE = 'BYE';
}
