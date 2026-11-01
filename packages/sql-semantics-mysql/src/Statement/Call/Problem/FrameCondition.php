<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Problem;

/**
 * A fixed relationship among a frame's written operands.
 *
 * @visibility public
 * @example Describing a fixed offset domain
 *     \SqlSemantics\Platform\MySql\Statement\Call\Problem\FrameCondition::Offset->value // => 'a ROWS offset literal must be an unsigned 64-bit integer'
 */
enum FrameCondition: string
{
    case RowsInterval = 'INTERVAL can only be used with RANGE frames';
    case RangeOrder = 'an offset RANGE frame requires exactly one ordering expression';
    case Offset = 'a ROWS offset literal must be an unsigned 64-bit integer';
}
