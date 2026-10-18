<?php

declare(strict_types=1);

namespace App\Enum;

enum ReportState: string
{
    case Open = 'open';
    case Dismissed = 'dismissed';
    case Upheld = 'upheld';
}
