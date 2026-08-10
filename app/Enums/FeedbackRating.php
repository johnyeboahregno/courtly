<?php

declare(strict_types=1);

namespace App\Enums;

enum FeedbackRating: string
{
    case POOR = 'POOR';
    case GOOD = 'GOOD';
    case GREAT = 'GREAT';
}
