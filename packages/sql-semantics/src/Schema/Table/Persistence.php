<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Table;

/**
 * Persistence alternatives.
 *
 * @visibility public
 */
enum Persistence: string
{
    case Permanent = 'permanent';
    case Temporary = 'temporary';
    case Unlogged = 'unlogged';
}
