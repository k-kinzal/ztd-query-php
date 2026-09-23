<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Spatial;

/**
 * Mutually exclusive handling of an existing spatial reference system.
 * @visibility public
 * @example Selecting replacement semantics
 *     \SqlSemantics\Model\Definition\Spatial\CreationPolicy::Replace->value // => 'OR REPLACE'
 */
enum CreationPolicy: string
{
    case RequireNew = '';
    case IfNotExists = 'IF NOT EXISTS';
    case Replace = 'OR REPLACE';
}
