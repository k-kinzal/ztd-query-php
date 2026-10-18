<?php

declare(strict_types=1);

namespace App\Enums;

enum ReportCategory: string
{
    case Spam = 'spam';
    case Harassment = 'harassment';
    case Privacy = 'privacy';
    case Copyright = 'copyright';
    case Other = 'other';
}
