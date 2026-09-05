<?php

declare(strict_types=1);

namespace App\Enums;

enum TournamentFormat: string
{
    case ROUND_ROBIN = 'round_robin';
    case LADDER = 'ladder';
}
