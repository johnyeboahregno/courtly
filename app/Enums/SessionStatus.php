<?php

declare(strict_types=1);

namespace App\Enums;

enum SessionStatus: string
{
    case UPCOMING = 'UPCOMING';
    case ACTIVE = 'ACTIVE';
    case PAUSED = 'PAUSED';
    case FINISHED = 'FINISHED';
}
