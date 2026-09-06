<?php

declare(strict_types=1);

namespace App\Enums;

enum CircleJoinRequestStatus: string
{
    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case DECLINED = 'DECLINED';
}
