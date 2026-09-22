<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Operator;

/**
 * Unary operations with a fixed operand count and classified predicate behavior.
 * @example Inspecting a truth test
 *     \SqlSemantics\Model\Scalar\Operator\UnaryOperator::IsNotFalse->truthTest() // => true
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
    case IsTrue = 'IS TRUE';
    case IsNotTrue = 'IS NOT TRUE';
    case IsFalse = 'IS FALSE';
    case IsNotFalse = 'IS NOT FALSE';
    case IsUnknown = 'IS UNKNOWN';
    case IsNotUnknown = 'IS NOT UNKNOWN';
    case Binary = 'BINARY';

    /**
     * Identifies truth-state predicates that require a Boolean input in PostgreSQL.
     */
    public function truthTest(): bool
    {
        return in_array($this, [self::IsTrue, self::IsNotTrue, self::IsFalse, self::IsNotFalse, self::IsUnknown, self::IsNotUnknown], true);
    }

    /**
     * Identifies predicates written after their operand and returning a non-NULL result.
     */
    public function postfix(): bool
    {
        return $this->truthTest() || $this === self::IsNull || $this === self::IsNotNull;
    }
}
