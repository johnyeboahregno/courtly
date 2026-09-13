<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case SUPER_ADMIN = 'SUPER_ADMIN';
    case ADMIN = 'ADMIN';
    case ORGANISER = 'ORGANISER';
    case PLAYER = 'PLAYER';
}
