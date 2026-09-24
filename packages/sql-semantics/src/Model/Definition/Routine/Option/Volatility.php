<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Option;

/**
 * Whether a function can change the database or return different results for the same arguments.
 * @visibility public
 * @example Reading the SQL spelling
 *     \SqlSemantics\Model\Definition\Routine\Option\Volatility::Immutable->value // => 'IMMUTABLE'
 */
enum Volatility: string implements RoutineOption
{
    case Immutable = 'IMMUTABLE';
    case Stable = 'STABLE';
    case Volatile = 'VOLATILE';
}
