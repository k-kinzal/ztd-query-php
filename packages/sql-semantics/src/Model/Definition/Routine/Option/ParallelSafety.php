<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Option;

/**
 * Whether a function can run in parallel mode, spelled as the PARALLEL attribute value.
 * @visibility public
 * @example Reading the SQL spelling
 *     \SqlSemantics\Model\Definition\Routine\Option\ParallelSafety::Restricted->value // => 'RESTRICTED'
 */
enum ParallelSafety: string implements RoutineOption
{
    case Safe = 'SAFE';
    case Restricted = 'RESTRICTED';
    case Unsafe = 'UNSAFE';
}
