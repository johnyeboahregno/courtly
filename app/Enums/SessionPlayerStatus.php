<?php

declare(strict_types=1);

namespace App\Enums;

enum SessionPlayerStatus: string
{
    case WAITING = 'WAITING';
    case PLAYING = 'PLAYING';
    case PAUSED = 'PAUSED';
    case LEFT = 'LEFT';
}
