<?php

declare(strict_types=1);

namespace App\Enums;

enum CourtStatus: string
{
    case AVAILABLE = 'AVAILABLE';
    case PLAYING = 'PLAYING';
}
