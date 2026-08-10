<?php

declare(strict_types=1);

namespace App\Enums;

enum RatingStatus: string
{
    case PROVISIONAL = 'PROVISIONAL';
    case ESTABLISHED = 'ESTABLISHED';
}
