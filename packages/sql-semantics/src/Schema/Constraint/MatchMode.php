<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Constraint;

/**
 * MatchMode alternatives.
 *
 * @visibility public
 */
enum MatchMode: string
{
    case Simple = 'simple';
    case Full = 'full';
    case Partial = 'partial';
}
