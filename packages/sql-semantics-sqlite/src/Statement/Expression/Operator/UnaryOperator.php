<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Operator;

/**
 * The prefix operators of SQLite expressions.
 *
 * Source: https://sqlite.org/lang_expr.html#operators_and_parse_affecting_attributes.
 *
 * @visibility public
 * @example Reading the operator of a negation
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT NOT 1');
 *     $query->statement->columns[0]->expression->operator // => \SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\UnaryOperator::Not
 */
enum UnaryOperator: string
{
    case Not = 'NOT';
    case BitNot = '~';
    case Plus = '+';
    case Minus = '-';
}
