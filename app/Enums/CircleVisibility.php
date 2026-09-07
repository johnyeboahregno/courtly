<?php

declare(strict_types=1);

namespace App\Enums;

enum CircleVisibility: string
{
    case PUBLIC = 'PUBLIC';
    case PRIVATE = 'PRIVATE';
}
