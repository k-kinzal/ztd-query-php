<?php

declare(strict_types=1);

namespace SqlSemantics\Type\Identity;

/**
 * The declared time zone interpretation of a temporal type.
 * @visibility public
 */
enum TimeZoneMode: string
{
    case Unspecified = '';
    case Without = 'WITHOUT TIME ZONE';
    case With = 'WITH TIME ZONE';
}
