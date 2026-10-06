<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Operator;

use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;

/**
 * The binary arithmetic and bit operators of the bit_expr level.
 *
 * `%` and MOD are one operator and are written `%`. Each case holds the
 * written operator.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/arithmetic-functions.html,
 * https://dev.mysql.com/doc/refman/8.4/en/bit-functions.html.
 *
 * @visibility public
 * @example Reading the operator of an integer division
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE a DIV 2');
 *     $query->statement->where->operator // => \SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator::IntegerDivide
 */
enum ArithmeticOperator: string
{
    case BitOr = '|';
    case BitAnd = '&';
    case ShiftLeft = '<<';
    case ShiftRight = '>>';
    case Plus = '+';
    case Minus = '-';
    case Multiply = '*';
    case Divide = '/';
    case Modulo = '%';
    case IntegerDivide = 'DIV';
    case BitXor = '^';

    /**
     * Answers the binding level of the operator on the scale of MYSQL-PRECEDENCE-001.
     */
    public function level(): int
    {
        return match ($this) {
            self::BitOr => Precedence::BIT_EXPR,
            self::BitAnd => Precedence::BIT_AND,
            self::ShiftLeft, self::ShiftRight => Precedence::SHIFT,
            self::Plus, self::Minus => Precedence::ADDITIVE,
            self::Multiply, self::Divide, self::Modulo, self::IntegerDivide => Precedence::MULTIPLICATIVE,
            self::BitXor => Precedence::BIT_XOR,
        };
    }

    /**
     * Tells whether the operator works on the bits of its operands.
     */
    public function bitwise(): bool
    {
        return match ($this) {
            self::BitOr, self::BitAnd, self::ShiftLeft, self::ShiftRight, self::BitXor => true,
            self::Plus, self::Minus, self::Multiply, self::Divide, self::Modulo, self::IntegerDivide => false,
        };
    }
}
