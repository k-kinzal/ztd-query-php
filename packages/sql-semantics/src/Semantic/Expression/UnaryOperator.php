<?php

declare(strict_types=1);

namespace SqlSemantics\Semantic\Expression;

/**
 * Semantic unary operations.
 * @example Reading semantic relationships
 *     \SqlSemantics\Semantic\Expression\UnaryOperator::IsNull->value // => 'IS NULL'
 *
 * @visibility public
 */
enum UnaryOperator: string
{
    case Positive = '+';
    case Negative = '-';
    case Not = 'NOT';
    case IsNull = 'IS NULL';
    case IsNotNull = 'IS NOT NULL';
}
