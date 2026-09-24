<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Option;

/**
 * Whether a function reveals nothing about its arguments beyond its return value.
 * @visibility public
 * @example Reading the SQL spelling
 *     \SqlSemantics\Model\Definition\Routine\Option\LeakproofBehavior::NotLeakproof->value // => 'NOT LEAKPROOF'
 */
enum LeakproofBehavior: string implements RoutineOption
{
    case Leakproof = 'LEAKPROOF';
    case NotLeakproof = 'NOT LEAKPROOF';
}
