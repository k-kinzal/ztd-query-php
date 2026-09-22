<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Operator;

/**
 * Closed UnaryOperator alternatives.
 * @visibility public
 */
enum UnaryOperator: string
{
    case Positive = '+';
    case Negative = '-';
    case Not = 'NOT';
    case BitNot = '~';
    case IsNull = 'IS NULL';
    case IsNotNull = 'IS NOT NULL';
    case Binary = 'BINARY';
}
