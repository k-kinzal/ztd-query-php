<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Temporal;

/**
 * Whether an interval is added to or subtracted from a temporal operand.
 * @visibility public
 * @example Selecting subtraction
 *     \SqlSemantics\Model\Scalar\Temporal\ShiftDirection::Subtract->value // => 'DATE_SUB'
 */
enum ShiftDirection: string
{
    case Add = 'DATE_ADD';
    case Subtract = 'DATE_SUB';
}
