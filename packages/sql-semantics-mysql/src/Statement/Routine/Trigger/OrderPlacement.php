<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Trigger;

/**
 * Whether a trigger runs after (FOLLOWS) or before (PRECEDES) another trigger of the same table, event and time.
 *
 * Each case holds its keyword.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-trigger.html.
 *
 * @visibility public
 * @example Reading the keyword of a placement
 *     \SqlSemantics\Platform\MySql\Statement\Routine\Trigger\OrderPlacement::Precedes->value // => 'PRECEDES'
 */
enum OrderPlacement: string
{
    case Follows = 'FOLLOWS';
    case Precedes = 'PRECEDES';
}
