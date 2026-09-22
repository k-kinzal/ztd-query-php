<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Conditional;

/**
 * Chooses the largest or smallest non-NULL argument in PostgreSQL.
 * @visibility public
 * @example Selecting the comparison direction
 *     \SqlSemantics\Model\Scalar\Conditional\ExtremumKind::Greatest->value // => 'GREATEST'
 */
enum ExtremumKind: string
{
    case Greatest = 'GREATEST';
    case Least = 'LEAST';
}
