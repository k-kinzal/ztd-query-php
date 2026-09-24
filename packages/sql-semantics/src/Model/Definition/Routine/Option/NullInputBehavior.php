<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Option;

/**
 * Whether a function is called when an argument is NULL; STRICT and RETURNS NULL ON NULL INPUT are synonyms.
 * @visibility public
 * @example Reading the SQL spelling of a strict function
 *     \SqlSemantics\Model\Definition\Routine\Option\NullInputBehavior::Strict->value // => 'STRICT'
 */
enum NullInputBehavior: string implements RoutineOption
{
    case Called = 'CALLED ON NULL INPUT';
    case Strict = 'STRICT';
}
