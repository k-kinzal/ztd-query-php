<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Table;

/**
 * RowFormat alternatives.
 *
 * @visibility public
 */
enum RowFormat: string
{
    case Default = 'default';
    case Dynamic = 'dynamic';
    case Fixed = 'fixed';
    case Compressed = 'compressed';
    case Redundant = 'redundant';
    case Compact = 'compact';
}
