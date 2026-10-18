<?php

declare(strict_types=1);

namespace App\Enum;

enum MembershipRole: string
{
    case Owner = 'owner';
    case Moderator = 'moderator';
    case Member = 'member';
}
