<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Operator;

use SqlSemantics\Platform\Sqlite\Rules\Expression\Precedence;

/**
 * The binary operators of SQLite expressions, one case per spelling.
 *
 * Source: https://sqlite.org/lang_expr.html#operators_and_parse_affecting_attributes.
 *
 * @visibility public
 * @example Reading the operator of an expression
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 1 + 2');
 *     $query->statement->columns[0]->expression->operator // => \SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\BinaryOperator::Add
 */
enum BinaryOperator: string
{
    case Or = 'OR';
    case And = 'AND';
    case Equal = '=';
    case DoubleEqual = '==';
    case NotEqual = '<>';
    case BangEqual = '!=';
    case Is = 'IS';
    case IsNot = 'IS NOT';
    case IsDistinctFrom = 'IS DISTINCT FROM';
    case IsNotDistinctFrom = 'IS NOT DISTINCT FROM';
    case Less = '<';
    case LessOrEqual = '<=';
    case Greater = '>';
    case GreaterOrEqual = '>=';
    case BitAnd = '&';
    case BitOr = '|';
    case ShiftLeft = '<<';
    case ShiftRight = '>>';
    case Add = '+';
    case Subtract = '-';
    case Multiply = '*';
    case Divide = '/';
    case Modulo = '%';
    case Concat = '||';
    case Extract = '->';
    case ExtractValue = '->>';

    /**
     * Answers the binding level of the operator; a higher level binds tighter.
     */
    public function level(): int
    {
        return match ($this) {
            self::Or => Precedence::DISJUNCTION,
            self::And => Precedence::CONJUNCTION,
            self::Equal, self::DoubleEqual, self::NotEqual, self::BangEqual, self::Is, self::IsNot, self::IsDistinctFrom, self::IsNotDistinctFrom => Precedence::EQUALITY,
            self::Less, self::LessOrEqual, self::Greater, self::GreaterOrEqual => Precedence::COMPARISON,
            self::BitAnd, self::BitOr, self::ShiftLeft, self::ShiftRight => Precedence::BITWISE,
            self::Add, self::Subtract => Precedence::ADDITIVE,
            self::Multiply, self::Divide, self::Modulo => Precedence::MULTIPLICATIVE,
            self::Concat, self::Extract, self::ExtractValue => Precedence::CONCATENATION,
        };
    }

    /**
     * Tells whether the operator is written with keywords instead of punctuation.
     */
    public function worded(): bool
    {
        return in_array($this, [self::Or, self::And, self::Is, self::IsNot, self::IsDistinctFrom, self::IsNotDistinctFrom], true);
    }
}
