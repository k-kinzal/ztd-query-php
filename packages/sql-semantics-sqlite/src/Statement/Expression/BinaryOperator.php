<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression;

/**
 * The binary operators of SQLite expressions.
 *
 * Source: https://sqlite.org/lang_expr.html#operators_and_parse_affecting_attributes.
 *
 * @visibility public
 * @example Reading the operator of an expression
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 1 + 2');
 *     $query->statement->columns[0]->expression->operator // => \SqlSemantics\Platform\Sqlite\Statement\Expression\BinaryOperator::Add
 */
enum BinaryOperator: string
{
    case Add = '+';
    case Subtract = '-';
    case Multiply = '*';
    case Divide = '/';
    case Modulo = '%';
    case Equal = '=';
    case DoubleEqual = '==';
    case NotEqual = '<>';
    case BangEqual = '!=';
    case Less = '<';
    case LessOrEqual = '<=';
    case Greater = '>';
    case GreaterOrEqual = '>=';
    case And = 'AND';
    case Or = 'OR';

    /**
     * Tells whether the operator yields a truth value.
     */
    public function logical(): bool
    {
        return !in_array($this, [self::Add, self::Subtract, self::Multiply, self::Divide, self::Modulo], true);
    }
}
