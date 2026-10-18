<?php

declare(strict_types=1);

namespace App\Enum;

enum BoardVisibility: string
{
    case Public = 'public';
    case Members = 'members';
    case Staff = 'staff';
}
