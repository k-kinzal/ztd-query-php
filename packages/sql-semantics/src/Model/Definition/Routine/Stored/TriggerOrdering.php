<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Stored;

/**
 * Places a new trigger directly after or before an existing trigger with the same timing and event.
 * @visibility public
 * @example Reading the requested position
 *     \SqlSemantics\Model\Definition\Routine\Stored\TriggerOrdering::Follows->value // => 'FOLLOWS'
 */
enum TriggerOrdering: string
{
    case Follows = 'FOLLOWS';
    case Precedes = 'PRECEDES';
}
