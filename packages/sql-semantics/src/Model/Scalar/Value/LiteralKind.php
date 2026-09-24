<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Value;

/**
 * Closed LiteralKind alternatives.
 * @visibility public
 * @example Reading the literal category
 *     \SqlSemantics\Model\Scalar\Value\LiteralKind::BitString->value // => 'bit-string'
 */
enum LiteralKind: string
{
    case Number = 'number';
    case Text = 'text';
    case Boolean = 'boolean';
    case Null = 'null';
    case BitString = 'bit-string';
    case Binary = 'binary';
    case Date = 'date';
    case Time = 'time';
    case Timestamp = 'timestamp';
    case Interval = 'interval';
}
