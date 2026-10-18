<?php

declare(strict_types=1);

namespace App\Enums;

enum TopicState: string
{
    case Pending = 'pending';
    case Published = 'published';
    case Locked = 'locked';
    case Hidden = 'hidden';
    case Archived = 'archived';
}
