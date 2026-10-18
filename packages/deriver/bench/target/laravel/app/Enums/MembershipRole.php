<?php

declare(strict_types=1);

namespace App\Enums;

enum MembershipRole: string
{
    case Owner = 'owner';
    case Moderator = 'moderator';
    case Member = 'member';
}
