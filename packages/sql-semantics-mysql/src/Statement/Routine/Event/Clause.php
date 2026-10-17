<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Event;

/**
 * The semantic role of an event schedule operand.
 *
 * @visibility public
 * @example Identifying the interval operand
 *     \SqlSemantics\Platform\MySql\Statement\Routine\Event\Clause::Interval->value // => 'INTERVAL'
 */
enum Clause: string
{
    case At = 'AT';
    case Interval = 'INTERVAL';
    case Starts = 'STARTS';
    case Ends = 'ENDS';
}
