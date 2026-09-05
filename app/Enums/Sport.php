<?php

declare(strict_types=1);

namespace App\Enums;

enum Sport: string
{
    case BADMINTON = 'badminton';
    case TENNIS = 'tennis';
    case PICKLEBALL = 'pickleball';
    case PADEL = 'padel';
    case SQUASH = 'squash';
}
