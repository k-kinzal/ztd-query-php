<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Expression;

/**
 * SQLite's built-in binary computations and their NULL behavior.
 * @visibility public
 * @example Distinguishing null-safe equality from ordinary equality
 *     \SqlSemantics\Statement\Expression\SqliteBinaryOperator::Is->neverNull() // => true
 *     \SqlSemantics\Statement\Expression\SqliteBinaryOperator::Equal->neverNull() // => false
 */
enum SqliteBinaryOperator: string
{
    case Add = '+';
    case Subtract = '-';
    case Multiply = '*';
    case Divide = '/';
    case Remainder = '%';
    case BitwiseAnd = '&';
    case BitwiseOr = '|';
    case ShiftLeft = '<<';
    case ShiftRight = '>>';
    case Concatenate = '||';
    case Less = '<';
    case Greater = '>';
    case LessOrEqual = '<=';
    case GreaterOrEqual = '>=';
    case Equal = '=';
    case DoubleEqual = '==';
    case NotEqual = '!=';
    case AngleNotEqual = '<>';
    case Is = 'IS';
    case IsNot = 'IS NOT';
    case DistinctFrom = 'IS DISTINCT FROM';
    case NotDistinctFrom = 'IS NOT DISTINCT FROM';
    case And = 'AND';
    case Or = 'OR';

    /**
     * NULL-safe comparisons always produce a truth value.
     */
    public function neverNull(): bool
    {
        return in_array($this, [self::Is, self::IsNot, self::DistinctFrom, self::NotDistinctFrom], true);
    }

    /**
     * Numeric arithmetic may use integer or real storage depending on values and overflow.
     */
    public function arithmetic(): bool
    {
        return in_array($this, [self::Add, self::Subtract, self::Multiply, self::Divide, self::Remainder], true);
    }

    /**
     * Logical operations can return a non-NULL result even when one input is NULL.
     */
    public function logical(): bool
    {
        return $this === self::And || $this === self::Or;
    }
}
